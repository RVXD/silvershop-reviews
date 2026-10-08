<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * A tokenised invitation to review the products in a placed order. One per order; the token backs a
 * public landing page where the customer can leave verified reviews for the items they bought.
 *
 * @property string $Token
 * @property string $Status
 * @property ?string $SentAt
 * @property ?string $RemindedAt
 * @property ?string $RespondedAt
 * @property int $OrderID
 * @method Order Order()
 */
class ReviewInvitation extends DataObject
{
    private static string $table_name = 'SilverShop_ReviewInvitation';

    private static string $singular_name = 'Review invitation';

    private static string $plural_name = 'Review invitations';

    private static array $db = [
        'Token' => 'Varchar(64)',
        'Status' => "Enum('Pending,Sent,Responded,Unsubscribed','Pending')",
        'SentAt' => 'Datetime',
        'RemindedAt' => 'Datetime',
        'RespondedAt' => 'Datetime',
    ];

    private static array $has_one = [
        'Order' => Order::class,
    ];

    private static array $indexes = [
        'Token' => ['type' => 'unique', 'columns' => ['Token']],
        'OrderID' => true,
    ];

    private static string $default_sort = 'Created DESC';

    private static array $summary_fields = [
        'Created.Nice' => 'Created',
        'OrderID' => 'Order',
        'Status' => 'Status',
        'SentAt.Nice' => 'Sent',
        'RespondedAt.Nice' => 'Responded',
    ];

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if (!$this->Token) {
            $this->Token = bin2hex(random_bytes(16));
        }
    }

    /**
     * Absolute URL of the review landing page for this invitation.
     */
    public function Link(): string
    {
        return Director::absoluteURL('review/' . $this->Token);
    }

    /**
     * Absolute URL that opts this order out of review invitations.
     */
    public function UnsubscribeLink(): string
    {
        return Director::absoluteURL('review/unsubscribe/' . $this->Token);
    }

    public function canView($member = null): bool
    {
        return $this->canModerate($member);
    }

    public function canEdit($member = null): bool
    {
        return $this->canModerate($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return $this->canModerate($member);
    }

    public function canDelete($member = null): bool
    {
        return $this->canModerate($member);
    }

    public function canModerate($member = null): bool
    {
        return Permission::check('CMS_ACCESS_SilverShop\Reviews\Admin\ReviewsAdmin', 'any', $member)
            || Permission::check('ADMIN', 'any', $member);
    }
}
