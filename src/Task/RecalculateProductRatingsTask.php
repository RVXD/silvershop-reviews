<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Task;

use SilverShop\Page\Product;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Backfills / repairs the denormalised AverageRating column on every product from its approved reviews. The column is
 * kept current by the Review write/delete hooks, so this is only needed once after installing the feature (or after a
 * bulk import that bypassed the ORM hooks, e.g. direct SQL seeding).
 */
class RecalculateProductRatingsTask extends BuildTask
{
    protected string $title = 'Recalculate product review ratings';

    protected static string $description =
        'Recomputes every product\'s denormalised AverageRating (used by the storefront "Best rated" sort) from its approved reviews.';

    protected static string $commandName = 'reviews-recalculate-ratings';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $count = 0;
        foreach (Product::get() as $product) {
            if ($product->hasMethod('recalculateAverageRating')) {
                $product->recalculateAverageRating();
                $count++;
            }
        }

        $output->writeln(sprintf('Recalculated AverageRating for %d product(s).', $count));

        return Command::SUCCESS;
    }
}
