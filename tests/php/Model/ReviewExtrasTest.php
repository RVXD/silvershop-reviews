<?php

namespace SilverShop\Reviews\Tests\Model;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewImage;
use SilverShop\Reviews\Model\ReviewPoint;
use SilverStripe\Core\Config\Config;
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

    public function testSavePointsFromTextSplitsTrimsAndCaps(): void
    {
        Config::modify()->set(Review::class, 'max_points', 3);
        $review = Review::create();
        $review->Rating = 4;
        $review->write();

        Review::savePointsFromText($review, "Great sound\n  Light  \n\nLong battery\nFourth (dropped)", 'Pro');
        Review::savePointsFromText($review, 'No case included', 'Con');

        $review = Review::get()->byID($review->ID);
        $this->assertTrue($review->HasPoints());
        // trimmed, empties dropped, capped at max_points (3), order preserved
        $this->assertSame(['Great sound', 'Light', 'Long battery'], $review->ProsList()->column('Text'));
        $this->assertSame(['No case included'], $review->ConsList()->column('Text'));
    }

    public function testHasPointsFalseWhenDisabled(): void
    {
        Config::modify()->set(Review::class, 'allow_review_points', false);
        $review = Review::create();
        $review->write();
        $point = ReviewPoint::create();
        $point->ReviewID = $review->ID;
        $point->Type = 'Pro';
        $point->Text = 'x';
        $point->write();

        $this->assertFalse(Review::get()->byID($review->ID)->HasPoints());
    }
}
