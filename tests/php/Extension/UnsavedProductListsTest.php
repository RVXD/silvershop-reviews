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

    public function testApprovedListsOnUnsavedProductAreEmptyDataLists(): void
    {
        $product = Product::create();

        $this->assertInstanceOf(DataList::class, $product->ApprovedReviews());
        $this->assertInstanceOf(DataList::class, $product->ApprovedQuestions());
        $this->assertFalse($product->ApprovedReviews()->exists());
        $this->assertFalse($product->HasReviews());
    }
}
