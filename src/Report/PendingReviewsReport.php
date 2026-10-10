<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Report;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Reports\Report;

/**
 * The moderation queue: submitted product reviews that have not yet been approved. Oldest first, so the
 * longest-waiting reviews are handled first. A read-only overview — moderation itself happens in the
 * Reviews admin.
 */
class PendingReviewsReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Reviews awaiting moderation');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Submitted product reviews that are not yet approved — the moderation queue, oldest first.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 400;
    }

    public function sourceRecords($params = null)
    {
        return Review::get()
            ->filter('Approved', false)
            ->sort('Created', 'ASC');
    }

    public function columns()
    {
        return [
            'SubjectTitle' => _t(__CLASS__ . '.ColProduct', 'Product'),
            'AuthorName' => _t(__CLASS__ . '.ColAuthor', 'Author'),
            'Rating' => _t(__CLASS__ . '.ColRating', 'Rating'),
            'Created.Nice' => _t(__CLASS__ . '.ColDate', 'Submitted'),
        ];
    }
}
