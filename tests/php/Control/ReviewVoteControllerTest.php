<?php

namespace SilverShop\Reviews\Tests\Control;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Dev\FunctionalTest;

class ReviewVoteControllerTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private function approvedReview(): Review
    {
        $review = Review::create();
        $review->Rating = 5;
        $review->Approved = true;
        $review->write();
        return $review;
    }

    public function testUpVoteIncrementsAndDedupesPerSession(): void
    {
        $review = $this->approvedReview();

        $this->get('review-vote/' . $review->ID . '/up');
        $this->assertSame(1, (int) Review::get()->byID($review->ID)->HelpfulUp);

        // Same session → deduped, stays at 1.
        $this->get('review-vote/' . $review->ID . '/up');
        $this->assertSame(1, (int) Review::get()->byID($review->ID)->HelpfulUp);
    }

    public function testDownVote(): void
    {
        $review = $this->approvedReview();

        $this->get('review-vote/' . $review->ID . '/down');
        $this->assertSame(1, (int) Review::get()->byID($review->ID)->HelpfulDown);
    }

    public function testUnapprovedReviewCannotBeVoted(): void
    {
        $review = Review::create();
        $review->Rating = 5;
        $review->Approved = false;
        $review->write();

        $this->assertSame(404, $this->get('review-vote/' . $review->ID . '/up')->getStatusCode());
    }

    public function testInvalidDirectionIs404(): void
    {
        $review = $this->approvedReview();

        $this->assertSame(404, $this->get('review-vote/' . $review->ID . '/sideways')->getStatusCode());
    }
}
