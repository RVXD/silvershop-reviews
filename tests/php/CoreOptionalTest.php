<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Tests;

use SilverShop\Reviews\Admin\ReviewsAdmin;
use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverShop\Reviews\Extension\ReviewOrderExtension;
use SilverShop\Reviews\Extension\ShopReviewExtension;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverShop\Reviews\Service\ReviewInvitationService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Proves the module is core-optional: the company/website-review surface (SiteConfig) works with no shop, and the
 * shop-only surfaces (product reviews, the Review↔Order relation, the order-triggered invitation stack) are wired
 * only when silvershop/core is present — degrading cleanly, never fatally, when it is absent. These assertions hold
 * in BOTH modes (core present and core absent), so the suite runs unskipped in either clean-room.
 */
class CoreOptionalTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function coreInstalled(): bool
    {
        return class_exists('SilverShop\\Model\\Order');
    }

    public function testSiteConfigIsReviewableWithoutCore(): void
    {
        $config = SiteConfig::current_site_config();
        $this->assertTrue(
            $config->hasExtension(ShopReviewExtension::class),
            'SiteConfig carries the company/website review extension regardless of silvershop/core'
        );
    }

    public function testCompanyReviewSubmitAggregateAndOrganizationJsonLd(): void
    {
        $config = SiteConfig::current_site_config();
        foreach ([5, 4, 3] as $rating) {
            $review = Review::create();
            $review->Rating = $rating;
            $review->Approved = true;
            $review->SubjectID = $config->ID;
            $review->SubjectClass = $config->ClassName;
            $review->write();
        }
        // An unapproved review must not count.
        $pending = Review::create();
        $pending->Rating = 1;
        $pending->Approved = false;
        $pending->SubjectID = $config->ID;
        $pending->SubjectClass = $config->ClassName;
        $pending->write();

        $config = SiteConfig::current_site_config();
        $this->assertSame(3, $config->ShopRatingCount());
        $this->assertSame(4.0, $config->ShopAverageRating());

        $jsonLd = (string) $config->ShopRatingSchemaOrg();
        $this->assertStringContainsString('application/ld+json', $jsonLd, 'emits a JSON-LD script block');
        $this->assertStringContainsString('"@type":"Organization"', $jsonLd, 'company JSON-LD is an Organization');
        $this->assertStringContainsString('"aggregateRating"', $jsonLd, 'with an aggregateRating node');
        $this->assertStringContainsString('"ratingValue":"4"', $jsonLd, 'carrying the computed average');
    }

    public function testLocalBusinessSchemaTypeIsConfigurable(): void
    {
        Config::modify()->set(ShopReviewExtension::class, 'schema_type', 'LocalBusiness');
        $config = SiteConfig::current_site_config();
        $review = Review::create();
        $review->Rating = 5;
        $review->Approved = true;
        $review->SubjectID = $config->ID;
        $review->SubjectClass = $config->ClassName;
        $review->write();

        $jsonLd = (string) SiteConfig::current_site_config()->ShopRatingSchemaOrg();
        $this->assertStringContainsString('"@type":"LocalBusiness"', $jsonLd);
    }

    public function testInvitationServiceLoadsAndResolvesSenderWithoutCore(): void
    {
        // The invitation service carries no hard core dependency (it resolves sender identity locally, not via
        // core's ShopEmailSender trait), so it loads and its config reflects on any site — the feature is simply
        // inert without core (no orders to invite), never a fatal.
        $this->assertTrue(
            class_exists(ReviewInvitationService::class),
            'ReviewInvitationService is always loadable (no fatal when core is absent)'
        );
        $this->assertNotSame(
            '',
            ReviewInvitationService::create()->senderEmail(),
            'a From address resolves core-free (admin email or no-reply fallback)'
        );
    }

    public function testInvitationAdminTabIsCoreGated(): void
    {
        $models = (array) Config::inst()->get(ReviewsAdmin::class, 'managed_models');
        $classes = [];
        foreach ($models as $key => $value) {
            $classes[] = is_int($key) ? $value : $key;
        }
        $this->assertSame(
            $this->coreInstalled(),
            in_array(ReviewInvitation::class, $classes, true),
            'review invitations are listed in the moderation admin only when silvershop/core is present'
        );
    }

    public function testReviewOrderRelationIsCoreGated(): void
    {
        $this->assertSame(
            $this->coreInstalled(),
            Review::has_extension(ReviewOrderExtension::class),
            'the Review↔Order relation is added only when silvershop/core is present'
        );
    }

    public function testProductBecomesReviewableOnlyWithCore(): void
    {
        if (!$this->coreInstalled()) {
            $this->assertFalse(
                class_exists('SilverShop\\Page\\Product'),
                'no Product class without silvershop/core — product reviews are inert'
            );
            return;
        }

        $this->assertTrue(
            \SilverShop\Page\Product::has_extension(ReviewableProductExtension::class),
            'Product gains the reviewable extension when silvershop/core is present'
        );
    }
}
