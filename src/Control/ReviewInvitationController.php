<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Control;

use SilverShop\Page\Product;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverShop\Reviews\Service\ReviewInvitationService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Public landing page for a review invitation. Looks the invitation up by its token, lets the customer
 * leave a (verified) review for each purchased product, and handles unsubscribe.
 */
class ReviewInvitationController extends Controller
{
    private static string $url_segment = 'review';

    private static array $allowed_actions = [
        'index',
        'unsubscribe',
        'ReviewInvitationForm',
    ];

    private static array $url_handlers = [
        'ReviewInvitationForm' => 'ReviewInvitationForm',
        'unsubscribe/$Token!' => 'unsubscribe',
        '$Token!' => 'index',
        '' => 'index',
    ];

    protected ?ReviewInvitation $invitation = null;

    /**
     * Base link for this controller, so forms post to `review/<action>` (not the token URL).
     */
    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), 'review', $action);
    }

    public function index(HTTPRequest $request)
    {
        if (!$this->currentInvitation()) {
            return $this->httpError(404, 'This review link is not valid.');
        }

        return $this->renderPage();
    }

    public function unsubscribe(HTTPRequest $request)
    {
        $invitation = $this->currentInvitation();
        if ($invitation && $invitation->Status !== 'Unsubscribed') {
            $invitation->Status = 'Unsubscribed';
            $invitation->write();
        }

        return $this->renderPage();
    }

    public function ReviewInvitationForm(): ?Form
    {
        $invitation = $this->currentInvitation();
        if (!$invitation || in_array($invitation->Status, ['Unsubscribed', 'Responded'], true)) {
            return null;
        }

        $order = $invitation->Order();
        $fields = FieldList::create();
        foreach (ReviewInvitationService::create()->reviewableProducts($order) as $product) {
            $pid = (int) $product->ProductID;
            $fields->push(HeaderField::create("head_$pid", $product->Title, 3));
            $fields->push(
                DropdownField::create("Rating_$pid", _t(self::class . '.Rating', 'Rating'), array_combine(range(5, 1), range(5, 1)))
                    ->setEmptyString(_t(self::class . '.Skip', '(skip this product)'))
            );
            $fields->push(TextField::create("Title_$pid", _t(self::class . '.Title', 'Title')));
            $fields->push(TextareaField::create("Content_$pid", _t(self::class . '.Content', 'Your review'))->setRows(3));
        }
        $fields->push(HiddenField::create('Token', '', $invitation->Token));

        return Form::create(
            $this,
            'ReviewInvitationForm',
            $fields,
            FieldList::create(FormAction::create('doSubmitReviews', _t(self::class . '.Submit', 'Submit reviews')))
        );
    }

    public function doSubmitReviews(array $data, Form $form)
    {
        $invitation = $this->currentInvitation();
        if (!$invitation || in_array($invitation->Status, ['Unsubscribed', 'Responded'], true)) {
            return $this->httpError(404);
        }

        $order = $invitation->Order();
        $member = $order->Member();
        $email = $order->getLatestEmail();
        $moderation = (bool) Review::config()->get('moderation');
        $created = 0;

        foreach (ReviewInvitationService::create()->reviewableProducts($order) as $row) {
            $pid = (int) $row->ProductID;
            $rating = (int) ($data["Rating_$pid"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                continue;
            }

            $product = Product::get()->byID($pid);
            if (!$product) {
                continue;
            }

            // One verified review per product per order.
            $exists = Review::get()->filter([
                'OrderID' => $order->ID,
                'SubjectID' => $pid,
                'SubjectClass' => $product->ClassName,
            ])->exists();
            if ($exists) {
                continue;
            }

            $review = Review::create();
            $review->Rating = $rating;
            $review->Title = trim((string) ($data["Title_$pid"] ?? ''));
            $review->Content = trim((string) ($data["Content_$pid"] ?? ''));
            $review->AuthorName = ($member && $member->exists()) ? $member->getName() : (string) $email;
            $review->AuthorEmail = (string) $email;
            $review->Approved = !$moderation;
            $review->Verified = true; // came from an order invitation
            $review->SubjectID = $pid;
            $review->SubjectClass = $product->ClassName;
            $review->OrderID = $order->ID;
            if ($member && $member->exists()) {
                $review->MemberID = $member->ID;
            }
            $review->write();
            $created++;
        }

        if ($created > 0) {
            $invitation->Status = 'Responded';
            $invitation->RespondedAt = DBDatetime::now()->getValue();
            $invitation->write();
            $message = $moderation
                ? _t(self::class . '.ThanksModerated', 'Thank you! Your review(s) will appear once approved.')
                : _t(self::class . '.Thanks', 'Thank you for your review(s).');
            $form->sessionMessage($message, 'good');
        } else {
            $form->sessionMessage(_t(self::class . '.NothingToSave', 'Please rate at least one product.'), 'bad');
        }

        return $this->redirectBack();
    }

    protected function currentInvitation(): ?ReviewInvitation
    {
        if ($this->invitation) {
            return $this->invitation;
        }
        $token = $this->getRequest()->param('Token') ?: $this->getRequest()->requestVar('Token');
        if ($token) {
            $this->invitation = ReviewInvitation::get()->filter('Token', $token)->first();
        }

        return $this->invitation;
    }

    protected function renderPage()
    {
        $invitation = $this->currentInvitation();
        $order = $invitation->Order();

        return $this->customise([
            'SiteTitle' => ReviewInvitationService::create()->senderName(),
            'Invitation' => $invitation,
            'Order' => $order,
            'Products' => ReviewInvitationService::create()->reviewableProducts($order),
            'IsUnsubscribed' => $invitation->Status === 'Unsubscribed',
            'IsResponded' => $invitation->Status === 'Responded',
            'ReviewForm' => $this->ReviewInvitationForm(),
        ])->renderWith('SilverShop\\Reviews\\ReviewInvitationPage');
    }
}
