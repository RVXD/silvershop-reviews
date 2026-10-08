<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverShop\Model\Order;

/**
 * Kiyoh / Klantenvertellen adapter (NL; KV Media Groep).
 *
 * Config:
 *   SilverShop\Reviews\Provider\KiyohProvider:
 *     api_key: '...'            # connector / API key (API access must be enabled on the account)
 *     location_id: '...'        # your Kiyoh location/hash
 *     api_base: 'https://www.kiyoh.com/v1'
 *
 * NOTE: Kiyoh is mid-migration to a newer API and its classic feed is XML, not JSON — verify the exact
 * endpoint and response shape (and whether JSON is available) against your account before production. All
 * calls fail soft. Kiyoh can send the invitations itself (hand-off), so leave native invitation email off
 * when using Kiyoh for invitations.
 */
class KiyohProvider extends AbstractReviewProvider
{
    private static string $api_key = '';

    private static string $location_id = '';

    private static string $api_base = 'https://www.kiyoh.com/v1';

    public function getKey(): string
    {
        return 'kiyoh';
    }

    public function getName(): string
    {
        return 'Kiyoh';
    }

    public function isConfigured(): bool
    {
        return (string) $this->config()->get('api_key') !== ''
            && (string) $this->config()->get('location_id') !== '';
    }

    public function supportsShopRating(): bool
    {
        return true;
    }

    public function supportsProductRating(): bool
    {
        return true;
    }

    public function supportsInvitations(): bool
    {
        return true;
    }

    public function getShopRating(): ?ProviderRating
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $location = (string) $this->config()->get('location_id');
        $data = $this->httpGetJson(
            $this->base() . '/publication/review/external/location/' . rawurlencode($location) . '/statistics.json',
            $this->authHeaders(),
            'kiyoh_shop_' . $location
        );
        if (!$data) {
            return null;
        }
        // Kiyoh reports an average on a 0–10 scale; normalise to 5.
        $avg10 = $data['averageRating'] ?? ($data['percentage'] ?? null);
        if ($avg10 === null) {
            return null;
        }

        return ProviderRating::create(((float) $avg10) / 2, (int) ($data['numberReviews'] ?? 0), (string) ($data['url'] ?? ''), $this->getName());
    }

    public function getProductRating(string $sku): ?ProviderRating
    {
        if ($sku === '' || !$this->isConfigured()) {
            return null;
        }
        $location = (string) $this->config()->get('location_id');
        $data = $this->httpGetJson(
            $this->base() . '/publication/product/external/location/' . rawurlencode($location)
                . '/product/' . rawurlencode($sku) . '/statistics.json',
            $this->authHeaders(),
            'kiyoh_prod_' . $location . '_' . md5($sku)
        );
        if (!$data) {
            return null;
        }
        $avg10 = $data['averageRating'] ?? null;
        if ($avg10 === null) {
            return null;
        }

        return ProviderRating::create(((float) $avg10) / 2, (int) ($data['numberReviews'] ?? 0), '', $this->getName());
    }

    public function sendInvitation(Order $order): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $to = $order->getLatestEmail();
        if (!$to) {
            return false;
        }

        return $this->httpPostJson(
            $this->base() . '/invite/external/location/' . rawurlencode((string) $this->config()->get('location_id')),
            [
                'email' => $to,
                'order_number' => (string) $order->ID,
                'delay' => 0,
            ],
            $this->authHeaders()
        );
    }

    protected function base(): string
    {
        return rtrim((string) $this->config()->get('api_base'), '/');
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return [
            'X-Publication-Api-Token' => (string) $this->config()->get('api_key'),
            'Content-Type' => 'application/json',
        ];
    }
}
