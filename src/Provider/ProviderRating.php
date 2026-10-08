<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Provider;

use SilverStripe\Model\ArrayData;

/**
 * An aggregate rating fetched from an external review provider, in a template-friendly shape
 * ($RatingValue, $ReviewCount, $Percent, $StarsString, $ProviderName, $Url).
 */
class ProviderRating extends ArrayData
{
    public function __construct(float $ratingValue, int $reviewCount, string $url = '', string $providerName = '')
    {
        $max = 5;
        $rounded = (int) round($ratingValue);
        parent::__construct([
            'RatingValue' => round($ratingValue, 1),
            'ReviewCount' => $reviewCount,
            'Url' => $url,
            'ProviderName' => $providerName,
            'MaxRating' => $max,
            'Percent' => (int) round($ratingValue / $max * 100),
            'StarsString' => str_repeat('★', $rounded) . str_repeat('☆', $max - $rounded),
        ]);
    }
}
