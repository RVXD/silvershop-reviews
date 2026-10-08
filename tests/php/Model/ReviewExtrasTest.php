<?php

namespace SilverShop\Reviews\Tests\Model;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewImage;
use SilverStripe\Dev\SapphireTest;

class ReviewExtrasTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testHelpfulScoreAndVotesEnabled(): void
    {
        $review = Review::create();
        $review->HelpfulUp = 5;
        $review->HelpfulDown = 2;
        $review->write();

        $this->assertSame(3, $review->getHelpfulScore());
        $this->assertTrue($review->VotesEnabled());
    }

    public function testHasImages(): void
    {
        $review = Review::create();
        $review->Rating = 4;
        $review->write();
        $this->assertFalse($review->HasImages());

        $image = ReviewImage::create();
        $image->ReviewID = $review->ID;
        $image->write();

        $this->assertTrue(Review::get()->byID($review->ID)->HasImages());
    }
}
