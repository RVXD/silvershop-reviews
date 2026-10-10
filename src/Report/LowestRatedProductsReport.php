<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Report;

use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;

/**
 * Products ranked by their average approved-review rating, worst first — to surface quality problems. The
 * average matches the storefront (approved reviews only). Products with fewer than the minimum number of
 * reviews are excluded, so a single stray rating does not dominate. Limited to the lowest 100.
 *
 * Reviews are attached to their subject polymorphically; "products" here are the classes carrying the
 * product-review extension (Product in v1), which keeps shop-level (SiteConfig) reviews out of the ranking.
 */
class LowestRatedProductsReport extends Report
{
    private const LIMIT = 100;

    private const DEFAULT_MIN_REVIEWS = 1;

    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Lowest-rated products');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Products ranked by average approved review rating, worst first — to surface quality problems.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 410;
    }

    public function parameterFields()
    {
        return FieldList::create(
            NumericField::create('MinReviews', _t(__CLASS__ . '.MinReviews', 'Minimum number of reviews'))
                ->setValue(self::DEFAULT_MIN_REVIEWS)
        );
    }

    public function sourceRecords($params = null)
    {
        $productClasses = $this->reviewableProductClasses();
        if (empty($productClasses)) {
            return ArrayList::create();
        }

        $min = (isset($params['MinReviews']) && $params['MinReviews'] !== '')
            ? max(1, (int) $params['MinReviews'])
            : self::DEFAULT_MIN_REVIEWS;

        $query = SQLSelect::create();
        $query->setSelect([
            'SubjectID' => '"SubjectID"',
            'SubjectClass' => '"SubjectClass"',
            'AvgRating' => 'AVG("Rating")',
            'ReviewCount' => 'COUNT(*)',
        ]);
        $query->setFrom('"SilverShop_Review"');
        $query->addWhere('"Approved" = 1');

        $placeholders = implode(',', array_fill(0, count($productClasses), '?'));
        $query->addWhere(['"SubjectClass" IN (' . $placeholders . ')' => $productClasses]);

        $query->setGroupBy(['"SubjectID"', '"SubjectClass"']);
        // $min is cast to int, so inlining it in the HAVING clause is injection-safe and portable.
        $query->addHaving('COUNT(*) >= ' . (int) $min);
        $query->setOrderBy('AVG("Rating")', 'ASC');
        $query->setLimit(self::LIMIT);

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $list->push(ArrayData::create([
                'Product' => $this->subjectTitle((string) $row['SubjectClass'], (int) $row['SubjectID']),
                'AvgRating' => number_format((float) $row['AvgRating'], 1),
                'ReviewCount' => (int) $row['ReviewCount'],
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Product' => _t(__CLASS__ . '.ColProduct', 'Product'),
            'AvgRating' => _t(__CLASS__ . '.ColAvgRating', 'Average rating'),
            'ReviewCount' => _t(__CLASS__ . '.ColReviews', 'Reviews'),
        ];
    }

    /**
     * Every DataObject class that is reviewable as a product (carries ReviewableProductExtension).
     *
     * @return array<int, string>
     */
    private function reviewableProductClasses(): array
    {
        $classes = [];
        foreach (ClassInfo::subclassesFor(DataObject::class) as $candidate) {
            if (DataObject::has_extension($candidate, ReviewableProductExtension::class)) {
                $classes[] = $candidate;
            }
        }

        return $classes;
    }

    /**
     * Resolve a polymorphic subject's title (the reviewed product), with a safe fallback.
     */
    private function subjectTitle(string $class, int $id): string
    {
        if ($class === '' || !class_exists($class)) {
            return '#' . $id;
        }

        $subject = DataObject::get_by_id($class, $id);

        return ($subject && $subject->exists()) ? (string) $subject->getTitle() : ('#' . $id);
    }
}
