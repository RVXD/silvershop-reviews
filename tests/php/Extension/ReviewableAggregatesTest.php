<?php

namespace SilverShop\Reviews\Tests\Extension;

use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverShop\Reviews\Model\Review;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class ReviewableAggregatesTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        ReviewableTestObject::class,
    ];

    public function testAggregatesCountOnlyApprovedReviews(): void
    {
        $subject = ReviewableTestObject::create();
        $subject->Title = 'Thing';
        $subject->write();

        foreach ([5, 4, 3] as $rating) {
            $this->makeReview($subject, $rating, true);
        }
        // An unapproved 1★ review must not count towards the aggregates.
        $this->makeReview($subject, 1, false);

        $subject = ReviewableTestObject::get()->byID($subject->ID);

        $this->assertTrue($subject->HasReviews());
        $this->assertSame(3, $subject->RatingCount());
        $this->assertSame(4.0, $subject->AverageRating());
        $this->assertSame(80, $subject->AverageRatingPercent());

        $fiveStar = $subject->RatingBreakdown()->filter('Stars', 5)->first();
        $this->assertSame(1, (int) $fiveStar->Count);
    }

    public function testNoReviewsGivesZeroes(): void
    {
        $subject = ReviewableTestObject::create();
        $subject->write();

        $this->assertFalse($subject->HasReviews());
        $this->assertSame(0, $subject->RatingCount());
        $this->assertSame(0.0, $subject->AverageRating());
    }

    public function testPaginatedReviewsRespectsPageSize(): void
    {
        $subject = ReviewableTestObject::create();
        $subject->write();
        for ($i = 0; $i < 5; $i++) {
            $this->makeReview($subject, 5, true);
        }

        Config::modify()->set(Review::class, 'reviews_per_page', 2);
        $subject = ReviewableTestObject::get()->byID($subject->ID);

        $paginated = $subject->PaginatedReviews();
        $this->assertSame(2, $paginated->getPageLength(), 'page size comes from config');
        $this->assertSame(5, (int) $paginated->getTotalItems(), 'all approved reviews are in the list');
        $this->assertSame(2, $paginated->count(), 'the first page holds one page-worth');
    }

    private function makeReview(DataObject $subject, int $rating, bool $approved): void
    {
        $review = Review::create();
        $review->Rating = $rating;
        $review->Approved = $approved;
        $review->SubjectID = $subject->ID;
        $review->SubjectClass = $subject->ClassName;
        $review->write();
    }
}

class ReviewableTestObject extends DataObject implements TestOnly
{
    private static $table_name = 'Reviews_ReviewableTestObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        ReviewableProductExtension::class,
    ];
}
