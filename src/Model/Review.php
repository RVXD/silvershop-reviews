<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverShop\Model\Order;
use SilverShop\Reviews\Control\ReviewVoteController;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\SecurityToken;

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
     * Collapse the "write a review" form on the product page behind a "Write a review" button (revealed with
     * one click). Progressive enhancement — without JavaScript the form is shown as usual.
     */
    private static bool $collapse_write_form = true;

    /**
     * Minimum seconds between submissions from one session (basic anti-spam throttle).
     */
    private static int $submit_throttle_seconds = 30;

    /**
     * Include the built-in hidden honeypot field on the public forms. Turn off if you rely solely on a
     * real spam protector (silverstripe/spamprotection).
     */
    private static bool $use_honeypot = true;

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

    /**
     * Maximum size per uploaded photo (PHP size string, e.g. '2m'). Caps anonymous uploads.
     */
    private static string $max_photo_size = '2m';

    /**
     * Reviews shown per page on the product page (0 = show all, no pagination).
     */
    private static int $reviews_per_page = 10;

    /**
     * Let reviewers add "plus" and "minus" points (pros/cons), one per line, shown as a +/- list.
     */
    private static bool $allow_review_points = true;

    /**
     * Maximum number of plus points (and, separately, minus points) kept per review.
     */
    private static int $max_points = 5;

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
        'Points' => ReviewPoint::class . '.Review',
    ];

    private static array $cascade_deletes = [
        'Images',
        'Points',
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

    /**
     * Whether plus/minus points are enabled (config).
     */
    public function PointsEnabled(): bool
    {
        return (bool) self::config()->get('allow_review_points');
    }

    public function HasPoints(): bool
    {
        return $this->PointsEnabled() && $this->Points()->exists();
    }

    /**
     * The plus points (pros), ordered.
     *
     * @return HasManyList<ReviewPoint>
     */
    public function ProsList(): HasManyList
    {
        /** @var HasManyList<ReviewPoint> $list */
        $list = $this->Points()->filter('Type', 'Pro');

        return $list;
    }

    /**
     * The minus points (cons), ordered.
     *
     * @return HasManyList<ReviewPoint>
     */
    public function ConsList(): HasManyList
    {
        /** @var HasManyList<ReviewPoint> $list */
        $list = $this->Points()->filter('Type', 'Con');

        return $list;
    }

    /**
     * Create a review's points of one type ('Pro' | 'Con') from a textarea value — one point per line,
     * trimmed, empties dropped, each capped to 255 chars, and no more than max_points kept.
     */
    public static function savePointsFromText(Review $review, string $text, string $type): void
    {
        $max = max(0, (int) self::config()->get('max_points'));
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $sort = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $sort >= $max) {
                continue;
            }
            $point = ReviewPoint::create();
            $point->ReviewID = $review->ID;
            $point->Type = $type;
            $point->Text = mb_substr($line, 0, 255);
            $point->Sort = ++$sort;
            $point->write();
        }
    }

    /**
     * The helpful-vote endpoint for this review in a direction ('up' | 'down'). Built via the controller's
     * join_links Link() so the slashes are always correct (don't hand-concatenate $BaseHref in templates).
     */
    public function VoteLink(string $direction): string
    {
        return ReviewVoteController::singleton()->Link($this->ID . '/' . $direction);
    }

    /**
     * Hidden CSRF field for the helpful-vote POST forms.
     */
    public function VoteSecurityField(): DBHTMLText
    {
        $token = SecurityToken::inst();
        $html = DBHTMLText::create();
        if ($token->getValue()) {
            $html->setValue(sprintf(
                '<input type="hidden" name="%s" value="%s">',
                Convert::raw2att($token->getName()),
                Convert::raw2att((string) $token->getValue())
            ));
        }

        return $html;
    }

    public static function HoneypotEnabled(): bool
    {
        return (bool) self::config()->get('use_honeypot');
    }

    /**
     * Enable silverstripe/spamprotection on a form when it is installed and a protector is configured.
     * No-op (and never throws) otherwise, so the built-in honeypot/throttle remain the fallback.
     */
    public static function applySpamProtection(Form $form): void
    {
        if ($form->hasMethod('enableSpamProtection')) {
            try {
                $form->enableSpamProtection();
            } catch (\Exception $e) {
                // No default spam protector configured — fall back to the honeypot/throttle.
            }
        }
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
