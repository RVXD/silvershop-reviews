<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Registry of external review providers. Providers are declared in config (key => Injector service) so
 * adapters plug in without code changes, and one is selected as active:
 *
 *   SilverShop\Reviews\Provider\ReviewProviderRegistry:
 *     active: trustpilot
 *     providers:
 *       trustpilot: '%$SilverShop\Reviews\Provider\TrustpilotProvider'
 *
 * The active provider only takes effect for a mode it both supports and is configured for; otherwise the
 * module falls back to its own native reviews / invitation email.
 */
class ReviewProviderRegistry
{
    use Configurable;
    use Injectable;

    /**
     * @var array<string, string> label => Injector service name / class
     */
    private static array $providers = [];

    /**
     * Key (getKey()) of the active provider; empty means use native reviews only.
     */
    private static string $active = '';

    /**
     * @return array<string, ReviewProvider> keyed by provider key
     */
    public function all(): array
    {
        $providers = [];
        foreach ((array) self::config()->get('providers') as $service) {
            if (!$service) {
                continue;
            }
            /** @var ReviewProvider $provider */
            $provider = Injector::inst()->get($service);
            $providers[$provider->getKey()] = $provider;
        }

        return $providers;
    }

    public function get(string $key): ?ReviewProvider
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The active, configured provider (or null → native).
     */
    public function active(): ?ReviewProvider
    {
        $key = (string) self::config()->get('active');
        if (!$key) {
            return null;
        }
        $provider = $this->get($key);

        return ($provider && $provider->isConfigured()) ? $provider : null;
    }

    public function forInvitations(): ?ReviewProvider
    {
        $provider = $this->active();

        return ($provider && $provider->supportsInvitations()) ? $provider : null;
    }

    public function forShopRating(): ?ReviewProvider
    {
        $provider = $this->active();

        return ($provider && $provider->supportsShopRating()) ? $provider : null;
    }

    public function forProductRating(): ?ReviewProvider
    {
        $provider = $this->active();

        return ($provider && $provider->supportsProductRating()) ? $provider : null;
    }
}
