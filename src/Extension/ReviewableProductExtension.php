<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\ProductQuestion;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Provider\ProviderRating;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;

/**
 * Makes a Product reviewable (the shop subject). Inherits the generic aggregate helpers from
 * {@see ReviewableExtension} and adds the Product-only surface: the denormalised sort column, verified-buyer
 * detection (needs silvershop/core's Order), Q&A, product-level JSON-LD and the external product provider
 * rating. Attached only when silvershop/core is present (guarded in the module's _config.php).
 *
 * @extends ReviewableExtension<\SilverShop\Page\Product>
 */
class ReviewableProductExtension extends ReviewableExtension
{
    /**
     * The sortable product column that mirrors {@see AverageRating()}. Exposed as a constant so the storefront can
     * offer a "Best rated" sort (silvershop/listsorter needs a real DB column; a computed method is not sortable)
     * without hard-coding the field name.
     */
    public const RATING_SORT_FIELD = 'AverageRating';

    /**
     * Denormalised mean of the product's approved reviews, kept in sync by {@see Review} write/delete hooks and the
     * RecalculateProductRatingsTask. 0.000 when there are no approved reviews, so unrated products sort last under
     * "{field} DESC". It only exists to make rating sortable at the DB level — read {@see AverageRating()} for display.
     */
    private static array $db = [
        'AverageRating' => 'Decimal(4,3)',
    ];

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
    public function ApprovedQuestions(): DataList
    {
        $owner = $this->getOwner();
        if (!$owner->isInDB()) {
            // A not-yet-saved product has an UnsavedRelationList; return an empty DataList of the right type.
            return ProductQuestion::get()->filter('ID', 0);
        }

        return $owner->Questions()->filter('Approved', true);
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
     * Recompute the denormalised {@see $db} AverageRating from the approved reviews and store it on BOTH the draft and
     * live product rows. Written with a direct query (not $owner->write()) because this is a cache column, not
     * editorial content: a normal write would create a new Version and leave the Live stage stale for the storefront.
     * Called from the Review write/delete hooks and the backfill task; a no-op for an unsaved product.
     */
    public function recalculateAverageRating(): void
    {
        $owner = $this->getOwner();
        if (!$owner->isInDB()) {
            return;
        }

        $average = round((float) $owner->Reviews()->filter('Approved', true)->avg('Rating'), 3);
        $id = (int) $owner->ID;

        // The column lives on the table that declares it (SilverShop_Product), not the hierarchy base (SiteTree);
        // resolve it from the schema so subclasses work too, then update both the draft and live stage rows.
        $table = DataObject::getSchema()->tableForField($owner->ClassName, 'AverageRating');
        if (!$table) {
            return;
        }

        $dbSchema = DB::get_schema();
        foreach ([$table, $table . '_Live'] as $stageTable) {
            if ($dbSchema->hasTable($stageTable)) {
                DB::prepared_query(
                    sprintf('UPDATE "%s" SET "AverageRating" = ? WHERE "ID" = ?', $stageTable),
                    [$average, $id]
                );
            }
        }
    }

    /**
     * Has this member (or email) bought this product in a placed order? Used to flag verified reviews.
     * Needs silvershop/core's Order; this extension is only attached when core is present.
     */
    public function reviewerHasPurchased(?Member $member, ?string $email = null): bool
    {
        $owner = $this->getOwner();

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
            'aggregateRating' => $this->aggregateRatingNode(),
            'review' => [],
        ];

        foreach ($this->ApprovedReviews()->limit(10) as $review) {
            /** @var \SilverShop\Reviews\Model\Review $review */
            $reviewData = [
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

            // Pros/cons as schema.org positiveNotes / negativeNotes (ItemList of ListItems).
            foreach (['Pro' => 'positiveNotes', 'Con' => 'negativeNotes'] as $type => $property) {
                $points = $review->Points()->filter('Type', $type);
                if (!$points->count()) {
                    continue;
                }
                $items = [];
                $position = 1;
                foreach ($points as $point) {
                    $items[] = ['@type' => 'ListItem', 'position' => $position++, 'name' => (string) $point->Text];
                }
                $reviewData[$property] = ['@type' => 'ItemList', 'itemListElement' => $items];
            }

            $schema['review'][] = $reviewData;
        }

        // JSON_HEX_TAG|JSON_HEX_AMP escape < > & so user content (title/body/author) can't break out of
        // the <script> block (stored XSS).
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
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
            '<p class="message" style="margin-top:1.5em">%s</p>',
            _t(
                self::class . '.CMSSummary',
                'Average rating: {avg} / 5 from {count} approved review(s).',
                ['avg' => $this->AverageRating(), 'count' => $this->RatingCount()]
            )
        );

        // Reviews tab: the grid, then the rating summary (spaced away from the grid), then a guidance notice.
        $fields->addFieldsToTab('Root.Reviews', [
            $this->buildReviewsGrid('Reviews', _t(self::class . '.Reviews', 'Reviews')),
            LiteralField::create('ReviewsSummary', $summary),
            LiteralField::create('ReviewsInfo', $this->cmsNotice(_t(
                self::class . '.ReviewsInfo',
                'Approved reviews appear on the product page and count towards its rating. Add a review here, '
                . 'or let customers submit their own, then tick "Approved" to publish it.'
            ))),
        ]);

        // Questions tab (auto-scaffolded grid): add a matching guidance notice at the bottom.
        $fields->addFieldToTab('Root.Questions', LiteralField::create('QuestionsInfo', $this->cmsNotice(_t(
            self::class . '.QuestionsInfo',
            'Customer questions about this product. Approve a question — and add an answer — to show it on '
            . 'the product page.'
        ))));
    }

    /**
     * A blue info notice for the CMS, matching the style SilverShop core uses on the Pricing/Variations tabs.
     */
    private function cmsNotice(string $text): string
    {
        return '<p class="message notice" style="margin-top:1.5em;display:flex;align-items:flex-start;gap:.5em">'
            . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>' . $text . '</span></p>';
    }
}
