<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Tests\Report;

use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Report\LowestRatedProductsReport;
use SilverShop\Reviews\Report\PendingReviewsReport;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class ReportsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        RatedTestProduct::class,
    ];

    /**
     * Every report instantiates, has a title, and its sourceRecords() runs cleanly on an empty DB — which
     * exercises the hand-written SQL (the raw SQLSelect report) against the test database (SQLite in CI).
     */
    public function testAllReportsRunCleanly(): void
    {
        $classes = [
            PendingReviewsReport::class,
            LowestRatedProductsReport::class,
        ];

        foreach ($classes as $class) {
            $report = new $class();
            $this->assertNotEmpty((string) $report->title(), "{$class} has a title");
            $this->assertCount(0, $report->sourceRecords(), "{$class} runs cleanly with no data");
        }
    }

    public function testPendingReviewsReportFlagsUnapproved(): void
    {
        $product = RatedTestProduct::create(['Title' => 'Widget']);
        $product->write();

        $pending = $this->makeReview($product, 2, false, 'Alice');
        $approved = $this->makeReview($product, 5, true, 'Bob');

        $ids = array_map('intval', (new PendingReviewsReport())->sourceRecords()->column('ID'));
        $this->assertContains((int) $pending->ID, $ids, 'a pending review is in the moderation queue');
        $this->assertNotContains((int) $approved->ID, $ids, 'an approved review is not in the queue');
    }

    public function testLowestRatedProductsReportRanksWorstFirstAndRespectsMinimum(): void
    {
        $bad = RatedTestProduct::create(['Title' => 'Bad Product']);
        $bad->write();
        $good = RatedTestProduct::create(['Title' => 'Good Product']);
        $good->write();

        foreach ([1, 2] as $rating) {
            $this->makeReview($bad, $rating, true);
        }
        foreach ([5, 4] as $rating) {
            $this->makeReview($good, $rating, true);
        }
        // An unapproved 1★ review on the good product must not drag its average (approved-only).
        $this->makeReview($good, 1, false);

        $rows = (new LowestRatedProductsReport())->sourceRecords(['MinReviews' => 1]);
        $this->assertCount(2, $rows, 'both rated products appear');

        $first = $rows->first();
        $this->assertSame('Bad Product', (string) $first->Product, 'the worst-rated product is ranked first');
        $this->assertSame('1.5', (string) $first->AvgRating, 'the average counts approved reviews only');
        $this->assertSame(2, (int) $first->ReviewCount, 'the review count is correct');

        // The minimum-reviews threshold excludes products below it (each product has two approved reviews).
        $filtered = (new LowestRatedProductsReport())->sourceRecords(['MinReviews' => 3]);
        $this->assertCount(0, $filtered, 'products below the minimum review count are excluded');
    }

    private function makeReview(DataObject $subject, int $rating, bool $approved, string $author = ''): Review
    {
        $review = Review::create();
        $review->Rating = $rating;
        $review->Approved = $approved;
        $review->AuthorName = $author;
        $review->SubjectClass = $subject->ClassName;
        $review->SubjectID = $subject->ID;
        $review->write();

        return $review;
    }
}

/**
 * A minimal writable, product-reviewable subject — stands in for a Product (which needs a page tree / app
 * webroot that the module's isolated test run does not have).
 */
class RatedTestProduct extends DataObject implements TestOnly
{
    private static $table_name = 'Reviews_RatedTestProduct';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        ReviewableProductExtension::class,
    ];
}
