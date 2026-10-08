<?php

namespace SilverShop\Reviews\Tests\Provider;

use PHPUnit\Framework\TestCase;
use SilverShop\Reviews\Provider\ProviderRating;

class ProviderRatingTest extends TestCase
{
    public function testFieldsAndDerivedValues(): void
    {
        $rating = new ProviderRating(4.6, 128, 'https://example.com', 'Demo');

        $this->assertSame(4.6, $rating->RatingValue);
        $this->assertSame(128, $rating->ReviewCount);
        $this->assertSame('https://example.com', $rating->Url);
        $this->assertSame('Demo', $rating->ProviderName);
        $this->assertSame(5, $rating->MaxRating);
        $this->assertSame(92, $rating->Percent);
        // round(4.6) = 5 filled stars
        $this->assertSame('★★★★★', $rating->StarsString);
    }

    public function testLowRating(): void
    {
        $rating = new ProviderRating(2.4, 10);

        $this->assertSame(48, $rating->Percent);
        // round(2.4) = 2 filled stars
        $this->assertSame('★★☆☆☆', $rating->StarsString);
    }
}
