<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverStripe\ORM\DataObject;

/**
 * An external review provider (Trustpilot, Kiyoh, The Feedback Company, …). A provider can plug into any
 * of three modes — invitation hand-off, shop-rating display, product-rating display — and declares which
 * it supports. Adapters usually extend {@link AbstractReviewProvider} and override only what they offer.
 *
 * The display modes (getShopRating / getProductRating) are entirely core-free; only the invitation hand-off
 * touches an order, so its parameter is type-hinted to the framework DataObject (a silvershop/core Order at
 * runtime) to keep the provider contract loadable and analysable without silvershop/core.
 */
interface ReviewProvider
{
    /**
     * Short stable key, e.g. 'trustpilot'. Used in config and SiteConfig selection.
     */
    public function getKey(): string;

    /**
     * Human-readable name, e.g. 'Trustpilot'.
     */
    public function getName(): string;

    /**
     * Whether the provider has the credentials/config it needs to be used.
     */
    public function isConfigured(): bool;

    public function supportsInvitations(): bool;

    public function supportsShopRating(): bool;

    public function supportsProductRating(): bool;

    /**
     * Hand an order off to the provider so it sends the review invitation. Return true on success.
     * $order is a silvershop/core Order (passed as a DataObject so the contract stays core-free).
     */
    public function sendInvitation(DataObject $order): bool;

    /**
     * The store's aggregate rating at the provider, or null when unavailable.
     */
    public function getShopRating(): ?ProviderRating;

    /**
     * A product's aggregate rating at the provider (keyed by SKU / InternalItemID), or null.
     */
    public function getProductRating(string $sku): ?ProviderRating;
}
