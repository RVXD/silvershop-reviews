<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Admin;

use SilverShop\Reviews\Model\ProductQuestion;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS moderation area for reviews: approve/reject, edit, reply (via Content), and filter by status.
 * Also lists review invitations and their status (sent / responded / unsubscribed).
 */
class ReviewsAdmin extends ModelAdmin
{
    private static array $managed_models = [
        Review::class,
        ProductQuestion::class,
        ReviewInvitation::class,
    ];

    private static string $url_segment = 'reviews';

    private static string $menu_title = 'Reviews';

    private static string $menu_icon_class = 'font-icon-comment';
}
