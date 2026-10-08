<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Adds the storefront "write a review" form to the product page, with submission gating
 * (anyone / members / verified purchasers), a honeypot + throttle for spam, and automatic
 * verified-purchase detection.
 *
 * @extends Extension<\SilverShop\Page\ProductController>
 */
class ProductReviewControllerExtension extends Extension
{
    private static array $allowed_actions = [
        'ReviewForm',
    ];

    private const HONEYPOT = 'HPWebsite';

    /**
     * The product page being viewed.
     */
    protected function product()
    {
        return $this->getOwner()->data();
    }

    /**
     * May the current visitor submit a review, per the `who_can_review` config?
     */
    public function CanReview(): bool
    {
        $member = Security::getCurrentUser();
        switch (Review::config()->get('who_can_review')) {
            case 'members':
                return (bool) $member;
            case 'verified':
                return $member && $this->product()->reviewerHasPurchased($member);
            case 'anyone':
            default:
                return true;
        }
    }

    /**
     * Explains why the form is hidden, when it is.
     */
    public function ReviewGateMessage(): string
    {
        if ($this->CanReview()) {
            return '';
        }

        if (Review::config()->get('who_can_review') === 'verified') {
            return _t(self::class . '.GateVerified', 'Only customers who bought this product can review it.');
        }

        return _t(self::class . '.GateMembers', 'Please log in to write a review.');
    }

    public function ReviewForm(): ?Form
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
            $fields->push(EmailField::create('AuthorEmail', _t(self::class . '.AuthorEmail', 'Your email'))
                ->setDescription(_t(self::class . '.EmailPrivate', 'Not published — used only to verify your purchase.')));
        }

        // Honeypot: real users leave it empty; bots fill every field.
        $fields->push(
            TextField::create(self::HONEYPOT, _t(self::class . '.Website', 'Website'))
                ->setAttribute('autocomplete', 'off')
                ->setAttribute('tabindex', '-1')
                ->setAttribute('style', 'position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden;')
                ->addExtraClass('review-hp')
        );

        $required = $member ? ['Rating', 'Content'] : ['Rating', 'Content', 'AuthorName', 'AuthorEmail'];

        $form = Form::create(
            $this->getOwner(),
            'ReviewForm',
            $fields,
            FieldList::create(
                FormAction::create('doPostReview', _t(self::class . '.Submit', 'Submit review'))
            ),
            RequiredFields::create($required)
        );

        return $form;
    }

    public function doPostReview(array $data, Form $form)
    {
        $owner = $this->getOwner();
        $product = $this->product();

        // Honeypot tripped → pretend success, write nothing.
        if (!empty($data[self::HONEYPOT])) {
            $form->sessionMessage(_t(self::class . '.Thanks', 'Thank you for your review.'), 'good');
            return $owner->redirectBack();
        }

        // Re-check gating server-side.
        if (!$this->CanReview()) {
            $form->sessionMessage($this->ReviewGateMessage(), 'bad');
            return $owner->redirectBack();
        }

        // Throttle repeated submissions from one session.
        $session = $owner->getRequest()->getSession();
        $throttle = (int) Review::config()->get('submit_throttle_seconds');
        $last = (int) $session->get('ReviewLastSubmit');
        if ($throttle > 0 && $last && (time() - $last) < $throttle) {
            $form->sessionMessage(_t(self::class . '.TooFast', 'Please wait a moment before submitting another review.'), 'bad');
            return $owner->redirectBack();
        }

        $rating = (int) ($data['Rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $form->sessionMessage(_t(self::class . '.BadRating', 'Please choose a rating between 1 and 5.'), 'bad');
            return $owner->redirectBack();
        }

        $member = Security::getCurrentUser();
        $authorName = $member ? $member->getName() : trim((string) ($data['AuthorName'] ?? ''));
        $authorEmail = $member ? $member->Email : trim((string) ($data['AuthorEmail'] ?? ''));

        $moderation = (bool) Review::config()->get('moderation');

        $review = Review::create();
        $review->Rating = $rating;
        $review->Title = trim((string) ($data['Title'] ?? ''));
        $review->Content = trim((string) ($data['Content'] ?? ''));
        $review->AuthorName = $authorName;
        $review->AuthorEmail = $authorEmail;
        $review->Approved = !$moderation;
        $review->Verified = $product->reviewerHasPurchased($member, $authorEmail ?: null);
        $review->SubjectID = $product->ID;
        $review->SubjectClass = $product->ClassName;
        if ($member) {
            $review->MemberID = $member->ID;
        }
        $review->write();

        $session->set('ReviewLastSubmit', time());

        $message = $moderation
            ? _t(self::class . '.ThanksModerated', 'Thank you! Your review will appear once it has been approved.')
            : _t(self::class . '.Thanks', 'Thank you for your review.');
        $form->sessionMessage($message, 'good');

        return $owner->redirectBack();
    }
}
