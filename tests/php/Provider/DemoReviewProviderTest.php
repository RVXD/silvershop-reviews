<?php

namespace SilverShop\Reviews\Tests\Provider;

use PHPUnit\Framework\TestCase;
use SilverShop\Reviews\Provider\DemoReviewProvider;

class DemoReviewProviderTest extends TestCase
{
    public function testCapabilities(): void
    {
        $provider = new DemoReviewProvider();

        $this->assertSame('demo', $provider->getKey());
        $this->assertSame('Demo provider', $provider->getName());
        $this->assertTrue($provider->isConfigured());
        $this->assertTrue($provider->supportsInvitations());
        $this->assertTrue($provider->supportsShopRating());
        $this->assertTrue($provider->supportsProductRating());
    }

    public function testShopRating(): void
    {
        $rating = (new DemoReviewProvider())->getShopRating();

        $this->assertNotNull($rating);
        $this->assertSame(4.6, $rating->RatingValue);
        $this->assertSame(128, $rating->ReviewCount);
    }

    public function testProductRating(): void
    {
        $provider = new DemoReviewProvider();

        $this->assertNull($provider->getProductRating(''));

        $rating = $provider->getProductRating('SKU-123');
        $this->assertNotNull($rating);
        $this->assertGreaterThanOrEqual(3.8, $rating->RatingValue);
        $this->assertLessThanOrEqual(4.9, $rating->RatingValue);

        // Deterministic: same SKU → same rating.
        $this->assertSame($rating->RatingValue, $provider->getProductRating('SKU-123')->RatingValue);
    }
}
