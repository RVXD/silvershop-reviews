<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Admin;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS moderation area for reviews: approve/reject, edit, reply (via Content), and filter by status.
 */
class ReviewsAdmin extends ModelAdmin
{
    private static array $managed_models = [
        Review::class,
    ];

    private static string $url_segment = 'reviews';

    private static string $menu_title = 'Reviews';

    private static string $menu_icon_class = 'font-icon-comment';
}
