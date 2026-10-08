<?php

namespace SilverShop\Reviews\Tests\Provider;

use SilverShop\Reviews\Provider\DemoReviewProvider;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class ReviewProviderRegistryTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(ReviewProviderRegistry::class, 'providers', [
            'demo' => DemoReviewProvider::class,
        ]);
    }

    public function testActiveResolvesAndSupportsAllModes(): void
    {
        Config::modify()->set(ReviewProviderRegistry::class, 'active', 'demo');
        $registry = new ReviewProviderRegistry();

        $this->assertInstanceOf(DemoReviewProvider::class, $registry->active());
        $this->assertInstanceOf(DemoReviewProvider::class, $registry->get('demo'));
        $this->assertNotNull($registry->forShopRating());
        $this->assertNotNull($registry->forProductRating());
        $this->assertNotNull($registry->forInvitations());
    }

    public function testNoActiveMeansNative(): void
    {
        Config::modify()->set(ReviewProviderRegistry::class, 'active', '');
        $registry = new ReviewProviderRegistry();

        $this->assertNull($registry->active());
        $this->assertNull($registry->forShopRating());
        $this->assertNull($registry->forProductRating());
        $this->assertNull($registry->forInvitations());
    }
}
