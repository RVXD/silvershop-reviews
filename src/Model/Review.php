<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverShop\Model\Order;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A single review with a star rating, attached to any reviewable subject via a polymorphic relation.
 * In v1 the subject is always a Product; the polymorphic `Subject` relation leaves room for shop-level
 * (SiteConfig) and variation reviews later without a data migration.
 *
 * @property int    $Rating
 * @property string $Title
 * @property string $Content
 * @property string $AuthorName
 * @property string $AuthorEmail
 * @property bool   $Approved
 * @property bool   $Verified
 * @property int    $SubjectID
 * @property string $SubjectClass
 * @property int    $MemberID
 * @property int    $OrderID
 * @method DataObject Subject()
 * @method Member Member()
 * @method Order Order()
 */
class Review extends DataObject
{
    private static string $table_name = 'SilverShop_Review';

    private static string $singular_name = 'Review';

    private static string $plural_name = 'Reviews';

    /**
     * Master switch for the whole reviews feature (list + form + votes). Set false to hide it entirely.
     */
    private static bool $allow_reviews = true;

    /**
     * Reviews are hidden until approved by a moderator. Set false (config) to publish on submit.
     */
    private static bool $moderation = true;

    /**
     * Who may submit a review: 'anyone' | 'members' | 'verified'.
     */
    private static string $who_can_review = 'anyone';

    /**
     * Minimum seconds between submissions from one session (basic anti-spam throttle).
     */
    private static int $submit_throttle_seconds = 30;

    /**
     * Allow customers to vote reviews helpful / not helpful.
     */
    private static bool $allow_votes = true;

    /**
     * Allow photo uploads with reviews.
     */
    private static bool $allow_photos = true;

    /**
     * Maximum photos per review.
     */
    private static int $max_photos = 3;

    private static array $db = [
        'Rating' => 'Int',
        'Title' => 'Varchar(255)',
        'Content' => 'Text',
        'AuthorName' => 'Varchar(255)',
        'AuthorEmail' => 'Varchar(255)',
        'Approved' => 'Boolean',
        'Verified' => 'Boolean',
        'HelpfulUp' => 'Int',
        'HelpfulDown' => 'Int',
    ];

    private static array $has_one = [
        'Subject' => DataObject::class, // polymorphic: Product (v1), SiteConfig / Variation (later)
        'Member' => Member::class,
        'Order' => Order::class,
    ];

    private static array $has_many = [
        'Images' => ReviewImage::class . '.Review',
    ];

    private static array $cascade_deletes = [
        'Images',
    ];

    private static array $defaults = [
        'Rating' => 5,
        'Approved' => false,
        'Verified' => false,
    ];

    private static array $summary_fields = [
        'Created.Nice' => 'Date',
        'SubjectTitle' => 'Subject',
        'Rating' => 'Rating',
        'Title' => 'Title',
        'AuthorName' => 'Author',
        'Verified.Nice' => 'Verified',
        'Approved.Nice' => 'Approved',
    ];

    private static array $searchable_fields = [
        'Title',
        'Content',
        'AuthorName',
        'Approved',
        'Verified',
        'Rating',
    ];

    private static string $default_sort = 'Created DESC';

    private static array $field_labels = [
        'Created.Nice' => 'Date',
        'SubjectTitle' => 'Subject',
        'Verified.Nice' => 'Verified',
        'Approved.Nice' => 'Approved',
    ];

    /**
     * Title of the reviewed subject, for CMS columns (the subject may be any class).
     */
    public function getSubjectTitle(): string
    {
        $subject = $this->Subject();
        if ($subject && $subject->exists()) {
            return (string) $subject->getTitle();
        }

        return '';
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['SubjectID', 'SubjectClass', 'MemberID', 'OrderID']);

        $fields->replaceField('Rating', DropdownField::create(
            'Rating',
            $this->fieldLabel('Rating'),
            array_combine(range(1, 5), range(1, 5))
        ));

        if ($subjectField = $fields->dataFieldByName('Title')) {
            $subjectField->setDescription(_t(self::class . '.SubjectDesc', 'Reviewing: {subject}', [
                'subject' => $this->getSubjectTitle() ?: '—',
            ]));
        }

        if ($approved = $fields->dataFieldByName('Approved')) {
            $approved->setDescription(_t(self::class . '.ApprovedDesc', 'Only approved reviews are shown on the site.'));
        }
        if ($verified = $fields->dataFieldByName('Verified')) {
            $verified->setDescription(_t(self::class . '.VerifiedDesc', 'Set automatically when the reviewer purchased this product.'));
        }

        foreach (['HelpfulUp', 'HelpfulDown'] as $voteField) {
            $fields->dataFieldByName($voteField)?->setReadonly(true);
        }

        if ($this->exists()) {
            $fields->addFieldToTab('Root.Photos', GridField::create(
                'Images',
                _t(self::class . '.Photos', 'Photos'),
                $this->Images(),
                GridFieldConfig_RecordEditor::create()
            ));
        }

        return $fields;
    }

    /**
     * A plain-text star bar for templates and CMS, e.g. "★★★★☆".
     */
    public function getStarsString(): string
    {
        $rating = max(0, min(5, (int) $this->Rating));

        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }

    public function HasImages(): bool
    {
        return $this->Images()->exists();
    }

    /**
     * Net helpfulness (up minus down votes), for sorting/display.
     */
    public function getHelpfulScore(): int
    {
        return (int) $this->HelpfulUp - (int) $this->HelpfulDown;
    }

    public function VotesEnabled(): bool
    {
        return (bool) self::config()->get('allow_votes');
    }

    public function canView($member = null): bool
    {
        // Approved reviews are public; moderators can see everything.
        if ($this->Approved) {
            return true;
        }

        return $this->canModerate($member);
    }

    public function canEdit($member = null): bool
    {
        return $this->canModerate($member);
    }

    public function canDelete($member = null): bool
    {
        return $this->canModerate($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return $this->canModerate($member);
    }

    /**
     * Whether $member may moderate reviews (manage them in the CMS).
     */
    public function canModerate($member = null): bool
    {
        return Permission::check('CMS_ACCESS_SilverShop\Reviews\Admin\ReviewsAdmin', 'any', $member)
            || Permission::check('ADMIN', 'any', $member);
    }
}
