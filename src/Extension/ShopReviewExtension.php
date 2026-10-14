<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Provider\ProviderRating;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Makes the store / company itself (SiteConfig) reviewable: overall company/website reviews via the same
 * polymorphic {@see Review} model, aggregate helpers for a rating badge, Organization (or LocalBusiness)
 * aggregateRating JSON-LD, and a CMS moderation grid. Entirely core-free — this is the company/site-review
 * feature that works on a plain SilverStripe site with no shop.
 *
 * The generic aggregate helpers come from {@see ReviewableExtension}; the public `Shop*` methods below are the
 * stable template API (kept for backward compatibility) and simply delegate to that shared logic.
 *
 * @extends ReviewableExtension<\SilverStripe\SiteConfig\SiteConfig>
 */
class ShopReviewExtension extends ReviewableExtension
{
    private static array $has_many = [
        'ShopReviews' => Review::class . '.Subject',
    ];

    /**
     * schema.org @type for the company aggregateRating JSON-LD. 'Organization' by default; set 'LocalBusiness'
     * (and populate address/geo in your own markup) for a local-business site that wants seller stars.
     */
    private static string $schema_type = 'Organization';

    protected function reviewsRelationName(): string
    {
        return 'ShopReviews';
    }

    /**
     * Whether to use company/website wording instead of shop wording on the rating badge / reviews surface
     * (auto on a core-free site). Lets SiteConfig-context templates pick the right label set.
     */
    public function IsCompanyMode(): bool
    {
        return Review::companyLabels();
    }

    /**
     * Approved shop/company reviews, newest first.
     */
    public function ApprovedShopReviews(): DataList
    {
        return $this->ApprovedReviews();
    }

    public function HasShopReviews(): bool
    {
        return $this->HasReviews();
    }

    public function ShopRatingCount(): int
    {
        return $this->RatingCount();
    }

    public function ShopAverageRating(): float
    {
        return $this->AverageRating();
    }

    public function ShopRatingPercent(): int
    {
        return $this->AverageRatingPercent();
    }

    public function ShopRatingBreakdown(): ArrayList
    {
        return $this->RatingBreakdown();
    }

    /**
     * Organization (or LocalBusiness) JSON-LD with the company's aggregate rating, for rich snippets.
     * Empty when there are no reviews (never emit a zero rating).
     */
    public function ShopRatingSchemaOrg(): DBHTMLText
    {
        $html = DBHTMLText::create();
        if (!$this->HasReviews()) {
            return $html;
        }

        $owner = $this->getOwner();
        $type = (string) Config::inst()->get(static::class, 'schema_type') ?: 'Organization';
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $owner->hasMethod('StoreProfileName') ? $owner->StoreProfileName() : $owner->Title,
            'url' => Director::absoluteBaseURL(),
            'aggregateRating' => $this->aggregateRatingNode(),
        ];

        // JSON_HEX_TAG|JSON_HEX_AMP escape < > & so the content can't break out of the <script> block.
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        $html->setValue('<script type="application/ld+json">' . $json . '</script>');

        return $html;
    }

    /**
     * The company's rating at the active external review provider, or null.
     */
    public function ProviderShopRating(): ?ProviderRating
    {
        $provider = ReviewProviderRegistry::create()->forShopRating();

        return $provider ? $provider->getShopRating() : null;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $summary = sprintf(
            '<p class="message">%s</p>',
            _t(
                self::class . '.CMSSummary',
                'Shop rating: {avg} / 5 from {count} approved review(s).',
                ['avg' => $this->ShopAverageRating(), 'count' => $this->ShopRatingCount()]
            )
        );

        $fields->addFieldsToTab('Root.ShopReviews', [
            LiteralField::create('ShopReviewsSummary', $summary),
            $this->buildReviewsGrid('ShopReviews', _t(self::class . '.ShopReviews', 'Shop reviews')),
        ]);
    }
}
