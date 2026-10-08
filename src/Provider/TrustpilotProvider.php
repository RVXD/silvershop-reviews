<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverShop\Model\Order;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Trustpilot adapter.
 *
 * Config (set via YAML / env on your site, never commit secrets):
 *   SilverShop\Reviews\Provider\TrustpilotProvider:
 *     api_key: '...'               # Business API key
 *     business_unit_id: '...'      # your Business Unit ID
 *     invitations_token: '...'     # OAuth bearer for the Invitations API (optional; enables hand-off)
 *
 * NOTE: endpoints/fields follow Trustpilot's public docs and must be verified against the current API with
 * a real account before production use. All calls fail soft (null/false) so an outage never breaks pages.
 * Trustpilot's guidelines require inviting ALL customers (no selective/incentivised invites) — the module's
 * invitation eligibility already invites every qualifying order.
 */
class TrustpilotProvider extends AbstractReviewProvider
{
    private static string $api_key = '';

    private static string $business_unit_id = '';

    private static string $invitations_token = '';

    public function getKey(): string
    {
        return 'trustpilot';
    }

    public function getName(): string
    {
        return 'Trustpilot';
    }

    public function isConfigured(): bool
    {
        return (string) $this->config()->get('api_key') !== ''
            && (string) $this->config()->get('business_unit_id') !== '';
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
        return (string) $this->config()->get('invitations_token') !== '';
    }

    public function getShopRating(): ?ProviderRating
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $buid = (string) $this->config()->get('business_unit_id');
        $data = $this->httpGetJson(
            "https://api.trustpilot.com/v1/business-units/{$buid}",
            ['apikey' => (string) $this->config()->get('api_key')],
            'tp_shop_' . $buid
        );
        if (!$data) {
            return null;
        }

        $score = $data['score']['trustScore'] ?? ($data['score']['stars'] ?? null);
        if ($score === null) {
            return null;
        }
        $count = (int) ($data['numberOfReviews']['total'] ?? 0);

        return ProviderRating::create((float) $score, $count, (string) ($data['profileUrl'] ?? ''), $this->getName());
    }

    public function getProductRating(string $sku): ?ProviderRating
    {
        if ($sku === '' || !$this->isConfigured()) {
            return null;
        }
        $buid = (string) $this->config()->get('business_unit_id');
        $data = $this->httpGetJson(
            "https://api.trustpilot.com/v1/product-reviews/business-units/{$buid}/summaries?sku=" . rawurlencode($sku),
            ['apikey' => (string) $this->config()->get('api_key')],
            'tp_prod_' . $buid . '_' . md5($sku)
        );
        if (!$data) {
            return null;
        }
        $summary = $data['summaries'][0] ?? $data;
        $stars = $summary['starsAverage'] ?? ($summary['averageStars'] ?? null);
        if ($stars === null) {
            return null;
        }

        return ProviderRating::create((float) $stars, (int) ($summary['numberOfReviews'] ?? 0), '', $this->getName());
    }

    public function sendInvitation(Order $order): bool
    {
        if (!$this->supportsInvitations() || !$this->isConfigured()) {
            return false;
        }
        $to = $order->getLatestEmail();
        if (!$to) {
            return false;
        }
        $buid = (string) $this->config()->get('business_unit_id');
        $config = SiteConfig::current_site_config();
        $senderName = $config->hasMethod('StoreProfileName') ? (string) $config->StoreProfileName() : (string) $config->Title;

        return $this->httpPostJson(
            "https://invitations-api.trustpilot.com/v1/private/business-units/{$buid}/email-invitations",
            [
                'consumerEmail' => $to,
                'consumerName' => $senderName ? $order->getLatestEmail() : $to,
                'referenceNumber' => (string) $order->ID,
                'locale' => 'en-US',
                'senderName' => $senderName,
            ],
            [
                'Authorization' => 'Bearer ' . (string) $this->config()->get('invitations_token'),
                'Content-Type' => 'application/json',
            ]
        );
    }
}
