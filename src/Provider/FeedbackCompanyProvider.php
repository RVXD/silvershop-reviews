<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverShop\Model\Order;

/**
 * The Feedback Company adapter (NL; Utrecht).
 *
 * Config:
 *   SilverShop\Reviews\Provider\FeedbackCompanyProvider:
 *     client_id: '...'
 *     client_secret: '...'
 *     shop_id: '...'
 *     api_base: 'https://www.feedbackcompany.com/api/v2'
 *
 * NOTE: The Feedback Company uses OAuth client-credentials; this adapter sends the credentials as headers
 * as a simple baseline — verify the exact auth flow (token exchange), endpoints and field names against
 * your account before production. Gathers both shop and product reviews and can send invitations. Fails soft.
 */
class FeedbackCompanyProvider extends AbstractReviewProvider
{
    private static string $client_id = '';

    private static string $client_secret = '';

    private static string $shop_id = '';

    private static string $api_base = 'https://www.feedbackcompany.com/api/v2';

    public function getKey(): string
    {
        return 'feedbackcompany';
    }

    public function getName(): string
    {
        return 'The Feedback Company';
    }

    public function isConfigured(): bool
    {
        return (string) $this->config()->get('client_id') !== ''
            && (string) $this->config()->get('client_secret') !== ''
            && (string) $this->config()->get('shop_id') !== '';
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
        $shop = (string) $this->config()->get('shop_id');
        $data = $this->httpGetJson(
            $this->base() . '/review/summary?shop_id=' . rawurlencode($shop),
            $this->authHeaders(),
            'fbc_shop_' . $shop
        );
        if (!$data) {
            return null;
        }
        $summary = $data['data'] ?? $data;
        // Feedback Company scores are on a 0–10 scale; normalise to 5.
        $avg10 = $summary['average'] ?? ($summary['score'] ?? null);
        if ($avg10 === null) {
            return null;
        }

        return ProviderRating::create(((float) $avg10) / 2, (int) ($summary['total_reviews'] ?? ($summary['amount'] ?? 0)), (string) ($summary['url'] ?? ''), $this->getName());
    }

    public function getProductRating(string $sku): ?ProviderRating
    {
        if ($sku === '' || !$this->isConfigured()) {
            return null;
        }
        $shop = (string) $this->config()->get('shop_id');
        $data = $this->httpGetJson(
            $this->base() . '/product_review/summary?shop_id=' . rawurlencode($shop) . '&product_id=' . rawurlencode($sku),
            $this->authHeaders(),
            'fbc_prod_' . $shop . '_' . md5($sku)
        );
        if (!$data) {
            return null;
        }
        $summary = $data['data'] ?? $data;
        $avg10 = $summary['average'] ?? null;
        if ($avg10 === null) {
            return null;
        }

        return ProviderRating::create(((float) $avg10) / 2, (int) ($summary['total_reviews'] ?? 0), '', $this->getName());
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
            $this->base() . '/invite',
            [
                'shop_id' => (string) $this->config()->get('shop_id'),
                'email' => $to,
                'order_number' => (string) $order->ID,
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
            'client_id' => (string) $this->config()->get('client_id'),
            'client_token' => (string) $this->config()->get('client_secret'),
            'Content-Type' => 'application/json',
        ];
    }
}
