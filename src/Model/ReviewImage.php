<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Model;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetContainer;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * A photo attached to a review. Owned by its Review (moderated with it, deleted with it).
 *
 * @property int $Sort
 * @property int $ReviewID
 * @property int $ImageID
 * @method Review Review()
 * @method Image Image()
 */
class ReviewImage extends DataObject
{
    private static string $table_name = 'SilverShop_ReviewImage';

    private static string $singular_name = 'Review photo';

    private static string $plural_name = 'Review photos';

    private static array $db = [
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Review' => Review::class,
        'Image' => Image::class,
    ];

    private static array $owns = [
        'Image',
    ];

    private static string $default_sort = 'Sort ASC';

    private static array $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'Review.Title' => 'Review',
    ];

    /**
     * A square thumbnail for templates, or null when there is no image.
     */
    public function Thumbnail(int $size = 90): ?AssetContainer
    {
        return $this->Image()->exists() ? $this->Image()->Fill($size, $size) : null;
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
