<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * A customer question about a reviewable subject (a Product), with staff/customer answers. Uses the same
 * polymorphic Subject pattern as {@link Review}.
 *
 * @property string $Question
 * @property string $AuthorName
 * @property string $AuthorEmail
 * @property bool $Approved
 * @property int $SubjectID
 * @property string $SubjectClass
 * @property int $MemberID
 * @method DataObject Subject()
 * @method Member Member()
 */
class ProductQuestion extends DataObject
{
    private static string $table_name = 'SilverShop_ProductQuestion';

    private static string $singular_name = 'Question';

    private static string $plural_name = 'Questions';

    /**
     * Master switch for the whole Q&A feature (list + ask form). Set false to hide it entirely.
     */
    private static bool $allow_qna = true;

    /**
     * Questions are hidden until approved. Set false (config) to publish on submit.
     */
    private static bool $moderation = true;

    /**
     * Who may ask: 'anyone' | 'members'.
     */
    private static string $who_can_ask = 'anyone';

    private static array $db = [
        'Question' => 'Text',
        'AuthorName' => 'Varchar(255)',
        'AuthorEmail' => 'Varchar(255)',
        'Approved' => 'Boolean',
    ];

    private static array $has_one = [
        'Subject' => DataObject::class,
        'Member' => Member::class,
    ];

    private static array $has_many = [
        'Answers' => ProductAnswer::class . '.Question',
    ];

    private static array $cascade_deletes = [
        'Answers',
    ];

    private static array $defaults = [
        'Approved' => false,
    ];

    private static string $default_sort = 'Created DESC';

    private static array $summary_fields = [
        'Created.Nice' => 'Date',
        'SubjectTitle' => 'Subject',
        'Question' => 'Question',
        'AuthorName' => 'Author',
        'AnswerCount' => 'Answers',
        'Approved.Nice' => 'Approved',
    ];

    private static array $searchable_fields = [
        'Question',
        'AuthorName',
        'Approved',
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SubjectID', 'SubjectClass', 'MemberID', 'Answers']);

        if ($questionField = $fields->dataFieldByName('Question')) {
            $questionField->setDescription(_t(self::class . '.AboutDesc', 'Asked about: {subject}', [
                'subject' => $this->getSubjectTitle() ?: '—',
            ]));
        }

        if ($this->exists()) {
            $fields->addFieldToTab('Root.Answers', GridField::create(
                'Answers',
                _t(self::class . '.Answers', 'Answers'),
                $this->Answers(),
                GridFieldConfig_RecordEditor::create()
            ));
        }

        return $fields;
    }

    public function getSubjectTitle(): string
    {
        $subject = $this->Subject();
        return ($subject && $subject->exists()) ? (string) $subject->getTitle() : '';
    }

    public function getAnswerCount(): int
    {
        return $this->Answers()->count();
    }

    /**
     * Approved answers, oldest first — the answers shown on the storefront.
     */
    public function ApprovedAnswers(): DataList
    {
        return $this->Answers()->filter('Approved', true)->sort('Created ASC');
    }

    public function HasApprovedAnswers(): bool
    {
        return $this->ApprovedAnswers()->exists();
    }

    public function canView($member = null): bool
    {
        if ($this->Approved) {
            return true;
        }

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
