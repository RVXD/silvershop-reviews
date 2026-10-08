<?php

namespace SilverShop\Reviews\Tests\Extension;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class ShopReviewAggregatesTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function makeShopReview(int $rating, bool $approved): void
    {
        $config = SiteConfig::current_site_config();
        $review = Review::create();
        $review->Rating = $rating;
        $review->Approved = $approved;
        $review->SubjectID = $config->ID;
        $review->SubjectClass = $config->ClassName;
        $review->write();
    }

    public function testShopAggregatesCountOnlyApproved(): void
    {
        $this->makeShopReview(5, true);
        $this->makeShopReview(5, true);
        $this->makeShopReview(3, true);
        $this->makeShopReview(1, false); // not counted

        $config = SiteConfig::current_site_config();

        $this->assertTrue($config->HasShopReviews());
        $this->assertSame(3, $config->ShopRatingCount());
        // (5 + 5 + 3) / 3 = 4.333 → 4.3
        $this->assertEqualsWithDelta(4.3, $config->ShopAverageRating(), 0.01);

        $fiveStar = $config->ShopRatingBreakdown()->filter('Stars', 5)->first();
        $this->assertSame(2, (int) $fiveStar->Count);
    }

    public function testNoShopReviews(): void
    {
        $config = SiteConfig::current_site_config();

        $this->assertFalse($config->HasShopReviews());
        $this->assertSame(0, $config->ShopRatingCount());
        $this->assertSame(0.0, $config->ShopAverageRating());
    }
}
