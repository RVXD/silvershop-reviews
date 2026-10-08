<?php

namespace SilverShop\Reviews\Tests\Extension;

use SilverShop\Reviews\Extension\ProductReviewControllerExtension;
use SilverShop\Reviews\Model\ProductQuestion;
use SilverShop\Reviews\Model\Review;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class ProductReviewTogglesTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testAllowReviewsGatesCanReview(): void
    {
        $extension = new ProductReviewControllerExtension();
        Config::modify()->set(Review::class, 'who_can_review', 'anyone');

        Config::modify()->set(Review::class, 'allow_reviews', true);
        $this->assertTrue($extension->CanReview());

        Config::modify()->set(Review::class, 'allow_reviews', false);
        $this->assertFalse($extension->CanReview(), 'reviews master switch off → cannot review');
    }

    public function testAllowQnaGatesCanAsk(): void
    {
        $extension = new ProductReviewControllerExtension();
        Config::modify()->set(ProductQuestion::class, 'who_can_ask', 'anyone');

        Config::modify()->set(ProductQuestion::class, 'allow_qna', true);
        $this->assertTrue($extension->CanAsk());

        Config::modify()->set(ProductQuestion::class, 'allow_qna', false);
        $this->assertFalse($extension->CanAsk(), 'Q&A master switch off → cannot ask');
    }
}
