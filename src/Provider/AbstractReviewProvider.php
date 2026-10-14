<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

/**
 * Base adapter: every capability off and every call a no-op, plus cached JSON HTTP helpers. Concrete
 * providers override `getKey()`/`getName()`/`isConfigured()`, the relevant `supports*()`, and the methods
 * for the modes they offer.
 */
abstract class AbstractReviewProvider implements ReviewProvider
{
    use Configurable;
    use Injectable;

    /**
     * Seconds to cache fetched ratings (display sync), so pages don't hit the provider API on every load.
     */
    private static int $cache_ttl = 3600;

    public function isConfigured(): bool
    {
        return false;
    }

    public function supportsInvitations(): bool
    {
        return false;
    }

    public function supportsShopRating(): bool
    {
        return false;
    }

    public function supportsProductRating(): bool
    {
        return false;
    }

    public function sendInvitation(DataObject $order): bool
    {
        return false;
    }

    /**
     * The customer email for an order, read defensively so the provider layer carries no hard silvershop/core
     * dependency. The call is dynamic on purpose: getLatestEmail() is a core Order method, so static analysis
     * stays clean on a core-free install.
     */
    protected function orderEmail(DataObject $order): string
    {
        if (!$order->hasMethod('getLatestEmail')) {
            return '';
        }

        return (string) $order->{'getLatestEmail'}();
    }

    public function getShopRating(): ?ProviderRating
    {
        return null;
    }

    public function getProductRating(string $sku): ?ProviderRating
    {
        return null;
    }

    protected function cache(): CacheInterface
    {
        return Injector::inst()->get(CacheInterface::class . '.reviewsProvider');
    }

    protected function cacheTtl(): int
    {
        return max(60, (int) $this->config()->get('cache_ttl'));
    }

    /**
     * GET a JSON endpoint with optional caching. Returns the decoded array, or null on any failure
     * (never throws — a provider outage must not break page rendering).
     *
     * @param array<string, string> $headers
     * @return array<mixed>|null
     */
    protected function httpGetJson(string $url, array $headers = [], ?string $cacheKey = null): ?array
    {
        if ($cacheKey) {
            try {
                $cached = $this->cache()->get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore cache read failures
            }
        }

        try {
            $response = (new Client(['timeout' => 8, 'http_errors' => false]))->get($url, ['headers' => $headers]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $data = json_decode((string) $response->getBody(), true);
            if (!is_array($data)) {
                return null;
            }
            if ($cacheKey) {
                try {
                    $this->cache()->set($cacheKey, $data, $this->cacheTtl());
                } catch (\Throwable $e) {
                    // ignore cache write failures
                }
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * POST JSON. Returns true on a 2xx response, false otherwise (never throws).
     *
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    protected function httpPostJson(string $url, array $body, array $headers = []): bool
    {
        try {
            $response = (new Client(['timeout' => 8, 'http_errors' => false]))->post($url, [
                'headers' => $headers,
                'json' => $body,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
