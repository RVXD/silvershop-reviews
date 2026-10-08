<?php

namespace SilverShop\Reviews\Tests\Model;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Dev\SapphireTest;

class ReviewTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testStarsString(): void
    {
        $review = Review::create();

        $review->Rating = 4;
        $this->assertSame('★★★★☆', $review->getStarsString());

        $review->Rating = 0;
        $this->assertSame('☆☆☆☆☆', $review->getStarsString());

        $review->Rating = 5;
        $this->assertSame('★★★★★', $review->getStarsString());
    }

    public function testStarsStringClampsOutOfRange(): void
    {
        $review = Review::create();

        $review->Rating = 9;
        $this->assertSame('★★★★★', $review->getStarsString());
    }
}
