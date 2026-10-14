<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Model\Order;
use SilverStripe\Core\Extension;

/**
 * Adds the `Order` relation to {@see \SilverShop\Reviews\Model\Review} — the order a verified review came from.
 *
 * silvershop/core is a SOFT dependency, so this relation lives in an extension that is attached only when core
 * is present (guarded in the module's _config.php). On an existing shop install it re-adds the identical
 * `OrderID` column the Review model used to declare natively, so dev/build performs no destructive change and
 * existing review→order links are preserved. On a core-free site the column simply never exists.
 *
 * @extends Extension<\SilverShop\Reviews\Model\Review>
 */
class ReviewOrderExtension extends Extension
{
    private static array $has_one = [
        'Order' => Order::class,
    ];
}
