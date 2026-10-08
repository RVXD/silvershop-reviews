<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\ProductQuestion;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Provider\ProviderRating;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;

/**
 * Makes a Product reviewable: owns its reviews (polymorphic), exposes aggregate rating helpers for
 * templates, adds a moderation grid in the CMS, and emits aggregate-rating JSON-LD for rich snippets.
 *
 * @extends Extension<\SilverShop\Page\Product>
 */
class ReviewableProductExtension extends Extension
{
    private static array $has_many = [
        'Reviews' => Review::class . '.Subject',
        'Questions' => ProductQuestion::class . '.Subject',
    ];

    private static array $cascade_deletes = [
        'Reviews',
        'Questions',
    ];

    /**
     * Approved customer questions for this product, newest first.
     */
    public function ApprovedQuestions(): \SilverStripe\ORM\DataList
    {
        return $this->getOwner()->Questions()->filter('Approved', true);
    }

    public function HasQuestions(): bool
    {
        return $this->ApprovedQuestions()->exists();
    }

    /**
     * Master switch: is the reviews feature enabled at all?
     */
    public function ReviewsEnabled(): bool
    {
        return (bool) Review::config()->get('allow_reviews');
    }

    /**
     * Master switch: is the Q&A feature enabled at all?
     */
    public function QnaEnabled(): bool
    {
        return (bool) ProductQuestion::config()->get('allow_qna');
    }

    /**
     * Approved reviews for this product, newest first — the list shown on the storefront.
     */
    public function ApprovedReviews(): DataList
    {
        return $this->getOwner()->Reviews()->filter('Approved', true);
    }

    public function HasReviews(): bool
    {
        return $this->ApprovedReviews()->exists();
    }

    /**
     * Approved reviews as a paginated list (page size from `Review.reviews_per_page`, 0 = all), for the
     * product page. Reads the `start` GET var from the current request.
     */
    public function PaginatedReviews(): PaginatedList
    {
        $list = PaginatedList::create($this->ApprovedReviews(), Controller::curr()->getRequest());
        $list->setPageLength(max(0, (int) Review::config()->get('reviews_per_page')));

        return $list;
    }

    public function RatingCount(): int
    {
        return $this->ApprovedReviews()->count();
    }

    /**
     * Mean rating of approved reviews (0.0 when none), rounded to one decimal.
     */
    public function AverageRating(): float
    {
        if (!$this->HasReviews()) {
            return 0.0;
        }

        return round((float) $this->ApprovedReviews()->avg('Rating'), 1);
    }

    /**
     * Average as a 0–100 percentage, for CSS star-bar widths.
     */
    public function AverageRatingPercent(): int
    {
        return (int) round($this->AverageRating() / 5 * 100);
    }

    /**
     * Per-star counts (5★ down to 1★) for a ratings breakdown bar.
     */
    public function RatingBreakdown(): ArrayList
    {
        $total = $this->RatingCount();
        $list = ArrayList::create();
        for ($stars = 5; $stars >= 1; $stars--) {
            $count = $this->ApprovedReviews()->filter('Rating', $stars)->count();
            $list->push(ArrayData::create([
                'Stars' => $stars,
                'Count' => $count,
                'Percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ]));
        }

        return $list;
    }

    /**
     * Has this member (or email) bought this product in a placed order? Used to flag verified reviews.
     */
    public function reviewerHasPurchased(?Member $member, ?string $email = null): bool
    {
        $owner = $this->getOwner();
        $reviews = null;

        // Walk the member's / email's orders and look for this product among the order items.
        $orders = \SilverShop\Model\Order::get()->filter('Status:not', ['Cart', 'Unpaid']);
        if ($member && $member->exists()) {
            $orders = $orders->filter('MemberID', $member->ID);
        } elseif ($email) {
            $orders = $orders->filter('LatestEmail', $email);
        } else {
            return false;
        }

        foreach ($orders->column('ID') as $orderID) {
            $order = \SilverShop\Model\Order::get()->byID($orderID);
            if (!$order) {
                continue;
            }
            foreach ($order->Items() as $item) {
                $product = $item->hasMethod('Product') ? $item->Product(true) : null;
                if ($product && (int) $product->ID === (int) $owner->ID) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Aggregate-rating + review JSON-LD for this product, for Google rich snippets.
     * Returns empty when there are no approved reviews (never emit a zero rating).
     */
    public function ReviewsSchemaOrg(): DBHTMLText
    {
        $html = DBHTMLText::create();
        if (!$this->HasReviews()) {
            return $html;
        }

        $owner = $this->getOwner();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $owner->getTitle(),
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $this->AverageRating(),
                'reviewCount' => $this->RatingCount(),
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'review' => [],
        ];

        foreach ($this->ApprovedReviews()->limit(10) as $review) {
            $schema['review'][] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) (int) $review->Rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $review->AuthorName ?: 'Anonymous',
                ],
                'name' => $review->Title,
                'reviewBody' => $review->Content,
                'datePublished' => date('Y-m-d', strtotime((string) $review->Created)),
            ];
        }

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $html->setValue('<script type="application/ld+json">' . $json . '</script>');

        return $html;
    }

    /**
     * This product's rating at the active external review provider (by SKU / InternalItemID), or null.
     */
    public function ProviderRating(): ?ProviderRating
    {
        $provider = ReviewProviderRegistry::create()->forProductRating();
        if (!$provider) {
            return null;
        }
        $sku = (string) $this->getOwner()->InternalItemID;

        return $sku !== '' ? $provider->getProductRating($sku) : null;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $summary = sprintf(
            '<p class="message">%s</p>',
            _t(
                self::class . '.CMSSummary',
                'Average rating: {avg} / 5 from {count} approved review(s).',
                ['avg' => $this->AverageRating(), 'count' => $this->RatingCount()]
            )
        );

        $grid = GridField::create(
            'Reviews',
            _t(self::class . '.Reviews', 'Reviews'),
            $this->getOwner()->Reviews(),
            GridFieldConfig_RecordEditor::create()
        );

        $fields->addFieldsToTab('Root.Reviews', [
            LiteralField::create('ReviewsSummary', $summary),
            $grid,
        ]);
    }
}
