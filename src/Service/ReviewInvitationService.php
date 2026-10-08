<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Service;

use SilverShop\Model\Order;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Finds orders that are due a review invitation, sends the (tokenised) invitation email, and later a
 * reminder. Driven by {@link \SilverShop\Reviews\Task\SendReviewInvitationsTask} (cron); a queued-jobs
 * wrapper can call the same methods when symbiote/silverstripe-queuedjobs is installed.
 */
class ReviewInvitationService
{
    use Configurable;
    use Injectable;

    /**
     * Order statuses that make an order eligible for a review invitation.
     *
     * @var list<string>
     */
    private static array $trigger_statuses = ['Sent', 'Complete'];

    /**
     * Days to wait after the reference date (dispatch/paid/placed) before inviting.
     */
    private static int $delay_days = 14;

    private static bool $reminder_enabled = true;

    /**
     * Days after the first invitation to send a single reminder (if no response).
     */
    private static int $reminder_delay_days = 7;

    /**
     * Safety cap on invitations created per run.
     */
    private static int $max_per_run = 50;

    /**
     * Sender address; falls back to store-profile contact email, then the framework admin email.
     */
    private static string $from_email = '';

    /**
     * Run a full cycle: send new invitations, then reminders. Returns a counts summary.
     *
     * @return array{invited:int, reminded:int, candidates:int}
     */
    public function run(): array
    {
        $invited = $this->sendInvitations();
        $reminded = $this->sendReminders();

        return $invited + ['reminded' => $reminded];
    }

    /**
     * Create + send invitations for all currently-eligible orders.
     *
     * @return array{invited:int, candidates:int}
     */
    public function sendInvitations(): array
    {
        $eligible = $this->eligibleOrders();
        $invited = 0;
        foreach ($eligible as $order) {
            // Reuse a pending invitation from a previous (failed-send) run instead of creating a duplicate.
            $invitation = ReviewInvitation::get()->filter('OrderID', $order->ID)->first();
            if (!$invitation) {
                $invitation = ReviewInvitation::create();
                $invitation->OrderID = $order->ID;
                $invitation->Status = 'Pending';
                $invitation->write();
            }

            if ($this->send($invitation, false)) {
                $invitation->Status = 'Sent';
                $invitation->SentAt = DBDatetime::now()->getValue();
                $invitation->write();
                $invited++;
            }
        }

        return ['invited' => $invited, 'candidates' => count($eligible)];
    }

    /**
     * Send a single reminder for invitations that are old enough and still unanswered.
     */
    public function sendReminders(): int
    {
        if (!static::config()->get('reminder_enabled')) {
            return 0;
        }
        // When a provider handles invitations, it also handles its own reminders.
        if (ReviewProviderRegistry::create()->forInvitations()) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) static::config()->get('reminder_delay_days') . ' days'));
        $due = ReviewInvitation::get()
            ->filter(['Status' => 'Sent'])
            ->filter('SentAt:LessThan', $cutoff)
            ->filter('RemindedAt', null)
            ->filter('RespondedAt', null);

        $reminded = 0;
        foreach ($due as $invitation) {
            if ($this->send($invitation, true)) {
                $invitation->RemindedAt = DBDatetime::now()->getValue();
                $invitation->write();
                $reminded++;
            }
        }

        return $reminded;
    }

    /**
     * Orders eligible for a (first) invitation right now.
     *
     * @return list<Order>
     */
    public function eligibleOrders(): array
    {
        $statuses = (array) static::config()->get('trigger_statuses');
        $delay = (int) static::config()->get('delay_days');
        $max = (int) static::config()->get('max_per_run');
        $cutoff = strtotime('-' . $delay . ' days');

        // Block only orders already dealt with; a Pending invitation (e.g. a failed send) stays retryable.
        $blocked = ReviewInvitation::get()
            ->filter('Status', ['Sent', 'Responded', 'Unsubscribed'])
            ->column('OrderID');

        $orders = Order::get()->filter('Status', $statuses);
        if (!empty($blocked)) {
            $orders = $orders->exclude('ID', $blocked);
        }

        $eligible = [];
        foreach ($orders as $order) {
            $ref = $this->referenceDate($order);
            if (!$ref || strtotime($ref) > $cutoff) {
                continue; // not old enough yet
            }
            if (!$order->getLatestEmail()) {
                continue;
            }
            if (!$this->reviewableProducts($order)->exists()) {
                continue;
            }
            $eligible[] = $order;
            if (count($eligible) >= $max) {
                break;
            }
        }

        return $eligible;
    }

    /**
     * The date an invitation delay is measured from: dispatch, else paid, else placed, else created.
     */
    public function referenceDate(Order $order): ?string
    {
        return $order->Dispatched ?: ($order->Paid ?: ($order->Placed ?: $order->Created));
    }

    /**
     * Distinct reviewable products in an order (as ArrayData rows: Title + Link).
     */
    public function reviewableProducts(Order $order): ArrayList
    {
        $list = ArrayList::create();
        $seen = [];
        foreach ($order->Items() as $item) {
            $product = $item->hasMethod('Product') ? $item->Product(true) : null;
            if (!$product || !$product->exists() || isset($seen[$product->ID])) {
                continue;
            }
            if (!$product->hasMethod('ApprovedReviews')) {
                continue; // not a reviewable product type
            }
            $seen[$product->ID] = true;
            $list->push(ArrayData::create([
                'ProductID' => $product->ID,
                'Title' => $product->getTitle(),
                'Link' => $product->AbsoluteLink(),
            ]));
        }

        return $list;
    }

    /**
     * Render the invitation email body (without sending) — used by the task's preview mode.
     */
    public function renderEmail(ReviewInvitation $invitation, bool $isReminder = false): DBHTMLText
    {
        return $this->emailData($invitation, $isReminder)->renderWith('SilverShop\\Reviews\\Email\\ReviewInvitationEmail');
    }

    protected function emailData(ReviewInvitation $invitation, bool $isReminder): ArrayData
    {
        $order = $invitation->Order();

        return ArrayData::create([
            'IsReminder' => $isReminder,
            'ShopName' => $this->senderName(),
            'CustomerName' => $order->getLatestEmail(),
            'Order' => $order,
            'Products' => $this->reviewableProducts($order),
            'ReviewLink' => $invitation->Link(),
            'UnsubscribeLink' => $invitation->UnsubscribeLink(),
        ]);
    }

    public function send(ReviewInvitation $invitation, bool $isReminder = false): bool
    {
        $order = $invitation->Order();
        if (!$order || !$order->exists() || !$order->getLatestEmail()) {
            return false;
        }

        // If an external provider handles invitations, hand the order off to it instead of our own email.
        $provider = ReviewProviderRegistry::create()->forInvitations();
        if ($provider) {
            return $provider->sendInvitation($order);
        }

        try {
            $this->buildEmail($invitation, $isReminder)->send();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function buildEmail(ReviewInvitation $invitation, bool $isReminder): Email
    {
        $order = $invitation->Order();
        $data = $this->emailData($invitation, $isReminder);

        $subject = $isReminder
            ? _t(self::class . '.ReminderSubject', 'A reminder to review your order from {shop}', ['shop' => $this->senderName()])
            : _t(self::class . '.Subject', 'How was your order from {shop}?', ['shop' => $this->senderName()]);

        return Email::create()
            ->setHTMLTemplate('SilverShop\\Reviews\\Email\\ReviewInvitationEmail')
            ->setData($data)
            ->setTo($order->getLatestEmail())
            ->setFrom($this->senderEmail(), $this->senderName())
            ->setSubject($subject);
    }

    public function senderEmail(): string
    {
        $configured = (string) static::config()->get('from_email');
        if ($configured) {
            return $configured;
        }

        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->hasField('ContactEmail') && $siteConfig->ContactEmail) {
            return (string) $siteConfig->ContactEmail;
        }

        $admin = Email::config()->get('admin_email');
        if (is_array($admin)) {
            $admin = array_key_first($admin) ?: '';
        }

        return $admin ?: ('no-reply@' . (parse_url(Director::absoluteBaseURL(), PHP_URL_HOST) ?: 'example.com'));
    }

    public function senderName(): string
    {
        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->hasMethod('StoreProfileName')) {
            $name = $siteConfig->StoreProfileName();
            if ($name) {
                return (string) $name;
            }
        }

        return (string) $siteConfig->Title;
    }
}
