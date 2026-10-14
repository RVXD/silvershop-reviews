<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataList;

/**
 * Core-free base that makes ANY DataObject / Page / SiteConfig reviewable through the polymorphic
 * {@see Review} `Subject` relation: approved-review list, star aggregates (count / average / percent /
 * per-star breakdown), a shared aggregateRating JSON-LD node and a CMS moderation grid.
 *
 * Subclasses pick the `has_many` relation name (via {@see reviewsRelationName()}) and layer on the
 * subject-specific bits: {@see ReviewableProductExtension} (Product, verified-buyer, Product JSON-LD —
 * the one core-coupled subclass) and {@see ShopReviewExtension} (SiteConfig, company/site reviews,
 * Organization JSON-LD — the core-free default). This is the single home for the ~80 near-identical
 * aggregate lines that used to be duplicated across those two extensions.
 *
 * @template T of \SilverStripe\ORM\DataObject
 * @extends Extension<T>
 */
abstract class ReviewableExtension extends Extension
{
    /**
     * Name of the owner's `has_many` relation to {@see Review} (declared on the subclass). Products use
     * `Reviews`; the company/site extension uses `ShopReviews`.
     */
    protected function reviewsRelationName(): string
    {
        return 'Reviews';
    }

    /**
     * Approved reviews for this subject, newest first (the list shown on the site). An unsaved owner has an
     * UnsavedRelationList, so return an empty DataList of the right type instead of blowing up.
     */
    public function ApprovedReviews(): DataList
    {
        $owner = $this->getOwner();
        if (!$owner->isInDB()) {
            return Review::get()->filter('ID', 0);
        }
        $relation = $this->reviewsRelationName();

        return $owner->$relation()->filter('Approved', true);
    }

    public function HasReviews(): bool
    {
        return $this->ApprovedReviews()->exists();
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
     * Approved reviews as a paginated list (page size from `Review.reviews_per_page`, 0 = all). Reads the
     * `start` GET var from the current request.
     */
    public function PaginatedReviews(): PaginatedList
    {
        $list = PaginatedList::create($this->ApprovedReviews(), Controller::curr()->getRequest());
        $list->setPageLength(max(0, (int) Review::config()->get('reviews_per_page')));

        return $list;
    }

    /**
     * The schema.org `aggregateRating` node shared by the Product and Organization JSON-LD emitters.
     *
     * @return array<string, mixed>
     */
    protected function aggregateRatingNode(): array
    {
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $this->AverageRating(),
            'reviewCount' => $this->RatingCount(),
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }

    /**
     * A record-editor moderation grid over the owner's reviews relation, for the CMS.
     */
    protected function buildReviewsGrid(string $relation, string $title): GridField
    {
        return GridField::create(
            $relation,
            $title,
            $this->getOwner()->$relation(),
            GridFieldConfig_RecordEditor::create()
        );
    }
}
