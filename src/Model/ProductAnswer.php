<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * An answer to a {@link ProductQuestion}, from staff or another customer.
 *
 * @property string $Answer
 * @property string $AuthorName
 * @property bool $IsStaff
 * @property bool $Approved
 * @property int $QuestionID
 * @property int $MemberID
 * @method ProductQuestion Question()
 * @method Member Member()
 */
class ProductAnswer extends DataObject
{
    private static string $table_name = 'SilverShop_ProductAnswer';

    private static string $singular_name = 'Answer';

    private static string $plural_name = 'Answers';

    private static array $db = [
        'Answer' => 'Text',
        'AuthorName' => 'Varchar(255)',
        'IsStaff' => 'Boolean',
        'Approved' => 'Boolean',
    ];

    private static array $has_one = [
        'Question' => ProductQuestion::class,
        'Member' => Member::class,
    ];

    private static array $defaults = [
        'Approved' => false,
    ];

    private static string $default_sort = 'Created ASC';

    private static array $summary_fields = [
        'Created.Nice' => 'Date',
        'Answer' => 'Answer',
        'AuthorName' => 'Author',
        'IsStaff.Nice' => 'Staff',
        'Approved.Nice' => 'Approved',
    ];

    public function getCMSFields(): \SilverStripe\Forms\FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['QuestionID', 'MemberID']);
        if ($staff = $fields->dataFieldByName('IsStaff')) {
            $staff->setDescription(_t(self::class . '.IsStaffDesc', 'Mark answers from your team so they show a "Shop" badge.'));
        }

        return $fields;
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
