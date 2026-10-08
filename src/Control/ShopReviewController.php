<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Control;

use SilverShop\Model\Order;
use SilverShop\Reviews\Model\Review;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Public "review our shop" page: lets a visitor leave an overall store review (subject = SiteConfig),
 * gated by the same who_can_review config, with honeypot + throttle anti-spam.
 */
class ShopReviewController extends Controller
{
    private static string $url_segment = 'shop-review';

    private static array $allowed_actions = [
        'index',
        'ShopReviewForm',
    ];

    private const HONEYPOT = 'HPWebsite';

    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), 'shop-review', $action);
    }

    public function index(HTTPRequest $request)
    {
        return $this->customise([
            'SiteTitle' => $this->shopName(),
            'ShopConfig' => SiteConfig::current_site_config(),
            'ReviewForm' => $this->ShopReviewForm(),
            'CanReview' => $this->CanReview(),
            'GateMessage' => $this->ReviewGateMessage(),
        ])->renderWith('SilverShop\\Reviews\\ShopReviewPage');
    }

    public function CanReview(): bool
    {
        $member = Security::getCurrentUser();
        switch (Review::config()->get('who_can_review')) {
            case 'members':
                return (bool) $member;
            case 'verified':
                return $member && $this->memberHasOrdered($member);
            case 'anyone':
            default:
                return true;
        }
    }

    public function ReviewGateMessage(): string
    {
        if ($this->CanReview()) {
            return '';
        }
        if (Review::config()->get('who_can_review') === 'verified') {
            return _t(self::class . '.GateVerified', 'Only customers who have ordered can review the shop.');
        }

        return _t(self::class . '.GateMembers', 'Please log in to review the shop.');
    }

    public function ShopReviewForm(): ?Form
    {
        if (!$this->CanReview()) {
            return null;
        }

        $member = Security::getCurrentUser();
        $fields = FieldList::create(
            DropdownField::create('Rating', _t(self::class . '.Rating', 'Your rating'), array_combine(range(5, 1), range(5, 1)))
                ->setEmptyString(_t(self::class . '.ChooseRating', '(choose)')),
            TextField::create('Title', _t(self::class . '.Title', 'Title')),
            TextareaField::create('Content', _t(self::class . '.Content', 'Your review'))->setRows(5)
        );

        if ($member) {
            $fields->push(HiddenField::create('AuthorName', '', $member->getName()));
            $fields->push(HiddenField::create('AuthorEmail', '', $member->Email));
        } else {
            $fields->push(TextField::create('AuthorName', _t(self::class . '.AuthorName', 'Your name')));
            $fields->push(EmailField::create('AuthorEmail', _t(self::class . '.AuthorEmail', 'Your email')));
        }

        if (Review::HoneypotEnabled()) {
            $fields->push($this->honeypotField());
        }

        $required = $member ? ['Rating', 'Content'] : ['Rating', 'Content', 'AuthorName', 'AuthorEmail'];

        $form = Form::create(
            $this,
            'ShopReviewForm',
            $fields,
            FieldList::create(FormAction::create('doSubmitShopReview', _t(self::class . '.Submit', 'Submit review'))),
            RequiredFieldsValidator::create($required)
        );
        Review::applySpamProtection($form);
        $this->extend('updateShopReviewForm', $form);

        return $form;
    }

    public function doSubmitShopReview(array $data, Form $form)
    {
        if (!empty($data[self::HONEYPOT])) {
            $form->sessionMessage(_t(self::class . '.Thanks', 'Thank you for your review.'), 'good');
            return $this->redirectBack();
        }
        if (!$this->CanReview()) {
            $form->sessionMessage($this->ReviewGateMessage(), 'bad');
            return $this->redirectBack();
        }

        $session = $this->getRequest()->getSession();
        $throttle = (int) Review::config()->get('submit_throttle_seconds');
        $last = (int) $session->get('ShopReviewLastSubmit');
        if ($throttle > 0 && $last && (time() - $last) < $throttle) {
            $form->sessionMessage(_t(self::class . '.TooFast', 'Please wait a moment before submitting again.'), 'bad');
            return $this->redirectBack();
        }

        $rating = (int) ($data['Rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $form->sessionMessage(_t(self::class . '.BadRating', 'Please choose a rating between 1 and 5.'), 'bad');
            return $this->redirectBack();
        }

        $member = Security::getCurrentUser();
        $moderation = (bool) Review::config()->get('moderation');
        $config = SiteConfig::current_site_config();

        $review = Review::create();
        $review->Rating = $rating;
        $review->Title = trim((string) ($data['Title'] ?? ''));
        $review->Content = trim((string) ($data['Content'] ?? ''));
        $review->AuthorName = $member ? $member->getName() : trim((string) ($data['AuthorName'] ?? ''));
        $review->AuthorEmail = $member ? $member->Email : trim((string) ($data['AuthorEmail'] ?? ''));
        $review->Approved = !$moderation;
        $review->Verified = $member ? $this->memberHasOrdered($member) : false;
        $review->SubjectID = $config->ID;
        $review->SubjectClass = $config->ClassName;
        if ($member) {
            $review->MemberID = $member->ID;
        }
        $this->extend('updateShopReview', $review, $data);
        $review->write();

        $session->set('ShopReviewLastSubmit', time());

        $message = $moderation
            ? _t(self::class . '.ThanksModerated', 'Thank you! Your review will appear once approved.')
            : _t(self::class . '.Thanks', 'Thank you for your review.');
        $form->sessionMessage($message, 'good');

        return $this->redirectBack();
    }

    /**
     * A fully-hidden honeypot (label + input), so real users never see it but bots fill it.
     */
    protected function honeypotField(): LiteralField
    {
        return LiteralField::create(
            self::HONEYPOT . '_hp',
            '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">'
            . '<label>Website <input type="text" name="' . self::HONEYPOT . '" autocomplete="off" tabindex="-1" value=""></label>'
            . '</div>'
        );
    }

    protected function memberHasOrdered(Member $member): bool
    {
        return Order::get()
            ->filter('MemberID', $member->ID)
            ->filter('Status:not', ['Cart', 'Unpaid'])
            ->exists();
    }

    protected function shopName(): string
    {
        $config = SiteConfig::current_site_config();
        if ($config->hasMethod('StoreProfileName') && $config->StoreProfileName()) {
            return (string) $config->StoreProfileName();
        }

        return (string) $config->Title;
    }
}
