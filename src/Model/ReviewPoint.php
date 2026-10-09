<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * A single plus/minus point on a {@link Review} — a short pro (Type "Pro") or con (Type "Con"). Owned by its
 * Review (moderated with it, deleted with it).
 *
 * @property string $Type
 * @property string $Text
 * @property int    $Sort
 * @property int    $ReviewID
 * @method Review Review()
 */
class ReviewPoint extends DataObject
{
    private static string $table_name = 'SilverShop_ReviewPoint';

    private static string $singular_name = 'Review point';

    private static string $plural_name = 'Review points';

    private static array $db = [
        'Type' => "Enum('Pro,Con','Pro')",
        'Text' => 'Varchar(255)',
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Review' => Review::class,
    ];

    private static string $default_sort = 'Sort ASC, ID ASC';

    private static array $summary_fields = [
        'Type' => 'Type',
        'Text' => 'Text',
    ];

    /**
     * Whether this is a plus point (as opposed to a minus point).
     */
    public function IsPro(): bool
    {
        return $this->Type === 'Pro';
    }

    public function canView($member = null): bool
    {
        $review = $this->Review();
        if ($review && $review->exists()) {
            return $review->canView($member);
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
