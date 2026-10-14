<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Tests\Extension;

use SilverShop\Page\Product;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;

/**
 * Regression: a not-yet-saved Product (e.g. "Add new Product" in the CMS) has UnsavedRelationList relations,
 * so the DataList-typed review/question accessors must not blow up with a TypeError.
 */
class UnsavedProductListsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Uses the real silvershop/core Product; skip when core is absent (the generic reviewable path is
        // covered core-free by ReviewableAggregatesTest against a TestOnly subject).
        if (!class_exists('SilverShop\\Page\\Product')) {
            $this->markTestSkipped('silvershop/core is not installed');
        }
    }

    public function testApprovedListsOnUnsavedProductAreEmptyDataLists(): void
    {
        $product = Product::create();

        $this->assertInstanceOf(DataList::class, $product->ApprovedReviews());
        $this->assertInstanceOf(DataList::class, $product->ApprovedQuestions());
        $this->assertFalse($product->ApprovedReviews()->exists());
        $this->assertFalse($product->HasReviews());
    }
}
