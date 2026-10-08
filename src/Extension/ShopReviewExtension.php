<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Provider\ProviderRating;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Makes the store itself (SiteConfig) reviewable: overall shop reviews via the same polymorphic Review
 * model, aggregate rating helpers for a store badge, Organization JSON-LD, and a CMS moderation grid.
 *
 * @extends Extension<\SilverStripe\SiteConfig\SiteConfig>
 */
class ShopReviewExtension extends Extension
{
    private static array $has_many = [
        'ShopReviews' => Review::class . '.Subject',
    ];

    /**
     * Approved shop reviews, newest first.
     */
    public function ApprovedShopReviews(): DataList
    {
        return $this->getOwner()->ShopReviews()->filter('Approved', true);
    }

    public function HasShopReviews(): bool
    {
        return $this->ApprovedShopReviews()->exists();
    }

    public function ShopRatingCount(): int
    {
        return $this->ApprovedShopReviews()->count();
    }

    public function ShopAverageRating(): float
    {
        if (!$this->HasShopReviews()) {
            return 0.0;
        }

        return round((float) $this->ApprovedShopReviews()->avg('Rating'), 1);
    }

    public function ShopRatingPercent(): int
    {
        return (int) round($this->ShopAverageRating() / 5 * 100);
    }

    public function ShopRatingBreakdown(): ArrayList
    {
        $total = $this->ShopRatingCount();
        $list = ArrayList::create();
        for ($stars = 5; $stars >= 1; $stars--) {
            $count = $this->ApprovedShopReviews()->filter('Rating', $stars)->count();
            $list->push(ArrayData::create([
                'Stars' => $stars,
                'Count' => $count,
                'Percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ]));
        }

        return $list;
    }

    /**
     * Organization JSON-LD with the store's aggregate rating, for rich snippets. Empty when no reviews.
     */
    public function ShopRatingSchemaOrg(): DBHTMLText
    {
        $html = DBHTMLText::create();
        if (!$this->HasShopReviews()) {
            return $html;
        }

        $owner = $this->getOwner();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $owner->hasMethod('StoreProfileName') ? $owner->StoreProfileName() : $owner->Title,
            'url' => Director::absoluteBaseURL(),
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $this->ShopAverageRating(),
                'reviewCount' => $this->ShopRatingCount(),
                'bestRating' => '5',
                'worstRating' => '1',
            ],
        ];

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $html->setValue('<script type="application/ld+json">' . $json . '</script>');

        return $html;
    }

    /**
     * The store's rating at the active external review provider, or null.
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

        $grid = GridField::create(
            'ShopReviews',
            _t(self::class . '.ShopReviews', 'Shop reviews'),
            $this->getOwner()->ShopReviews(),
            GridFieldConfig_RecordEditor::create()
        );

        $fields->addFieldsToTab('Root.ShopReviews', [
            LiteralField::create('ShopReviewsSummary', $summary),
            $grid,
        ]);
    }
}
