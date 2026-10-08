<?php

namespace SilverShop\Reviews\Tests\Control;

use SilverShop\Reviews\Control\ShopReviewController;
use SilverShop\Reviews\Model\Review;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\SiteConfig\SiteConfig;

class ShopReviewHooksTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $required_extensions = [
        ShopReviewController::class => [ShopReviewTestExtension::class],
    ];

    public function testHoneypotToggle(): void
    {
        Config::modify()->set(Review::class, 'use_honeypot', true);
        $this->assertStringContainsString('name="HPWebsite"', (string) $this->get('shop-review')->getBody());

        Config::modify()->set(Review::class, 'use_honeypot', false);
        $this->assertStringNotContainsString('name="HPWebsite"', (string) $this->get('shop-review')->getBody());
    }

    public function testUpdateShopReviewFormHookCanAddFields(): void
    {
        $body = (string) $this->get('shop-review')->getBody();
        $this->assertStringContainsString('ExtraField', $body, 'updateShopReviewForm hook can add fields');
    }

    public function testUpdateShopReviewHookCanModifyTheRecord(): void
    {
        $this->get('shop-review');
        $this->submitForm('Form_ShopReviewForm', 'action_doSubmitShopReview', [
            'Rating' => 5,
            'Content' => 'Great shop',
            'AuthorName' => 'Tester',
            'AuthorEmail' => 'tester@example.com',
        ]);

        $review = Review::get()->filter('SubjectClass', SiteConfig::class)->last();
        $this->assertNotNull($review);
        $this->assertSame('hooked', $review->Title, 'updateShopReview hook modified the review before write');
    }
}

class ShopReviewTestExtension extends Extension implements TestOnly
{
    public function updateShopReviewForm(Form $form): void
    {
        $form->Fields()->push(TextField::create('ExtraField', 'Extra'));
    }

    public function updateShopReview(Review $review, array $data): void
    {
        $review->Title = 'hooked';
    }
}
