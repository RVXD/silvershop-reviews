<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use Psr\Log\LoggerInterface;
use SilverShop\Model\Order;
use SilverStripe\Core\Injector\Injector;

/**
 * A fake provider returning canned data, for trialling the provider pipeline (display badges + invitation
 * hand-off) without real credentials. Not for production — enable it only in dev.
 */
class DemoReviewProvider extends AbstractReviewProvider
{
    public function getKey(): string
    {
        return 'demo';
    }

    public function getName(): string
    {
        return 'Demo provider';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportsInvitations(): bool
    {
        return true;
    }

    public function supportsShopRating(): bool
    {
        return true;
    }

    public function supportsProductRating(): bool
    {
        return true;
    }

    public function sendInvitation(Order $order): bool
    {
        Injector::inst()->get(LoggerInterface::class)
            ->info('[DemoReviewProvider] would hand off review invitation for order #' . $order->ID);

        return true;
    }

    public function getShopRating(): ?ProviderRating
    {
        return new ProviderRating(4.6, 128, 'https://example.com/demo/shop-reviews', $this->getName());
    }

    public function getProductRating(string $sku): ?ProviderRating
    {
        if ($sku === '') {
            return null;
        }
        // Deterministic pseudo-rating from the SKU, so different products show different numbers.
        $n = array_sum(array_map('ord', str_split($sku)));
        $rating = 3.8 + ($n % 12) / 10; // 3.8–4.9
        $count = 5 + ($n % 40);

        return new ProviderRating($rating, $count, 'https://example.com/demo/product/' . rawurlencode($sku), $this->getName());
    }
}
