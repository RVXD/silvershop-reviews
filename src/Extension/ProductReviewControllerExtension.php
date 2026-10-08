<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\ProductQuestion;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewImage;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
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
        'QuestionForm',
    ];

    private const HONEYPOT = 'HPWebsite';

    private const PHOTO_PREFIX = 'Photo';

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
        if (!Review::config()->get('allow_reviews')) {
            return false;
        }
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

        // Optional photo uploads.
        if (Review::config()->get('allow_photos')) {
            $maxPhotos = max(1, (int) Review::config()->get('max_photos'));
            for ($i = 1; $i <= $maxPhotos; $i++) {
                $label = $i === 1 ? _t(self::class . '.AddPhoto', 'Add a photo') : '';
                $fields->push(FileField::create(self::PHOTO_PREFIX . $i, $label));
            }
        }

        // Honeypot: real users never see it; bots fill every field.
        $fields->push($this->honeypotField());

        $required = $member ? ['Rating', 'Content'] : ['Rating', 'Content', 'AuthorName', 'AuthorEmail'];

        $form = Form::create(
            $this->getOwner(),
            'ReviewForm',
            $fields,
            FieldList::create(
                FormAction::create('doPostReview', _t(self::class . '.Submit', 'Submit review'))
            ),
            RequiredFieldsValidator::create($required)
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

        $this->saveReviewPhotos($review);

        $session->set('ReviewLastSubmit', time());

        $message = $moderation
            ? _t(self::class . '.ThanksModerated', 'Thank you! Your review will appear once it has been approved.')
            : _t(self::class . '.Thanks', 'Thank you for your review.');
        $form->sessionMessage($message, 'good');

        return $owner->redirectBack();
    }

    /**
     * Save any uploaded photo files as ReviewImages (image types only), up to the configured max.
     */
    protected function saveReviewPhotos(Review $review): void
    {
        if (!Review::config()->get('allow_photos')) {
            return;
        }
        $maxPhotos = max(1, (int) Review::config()->get('max_photos'));

        for ($i = 1; $i <= $maxPhotos; $i++) {
            $tmp = $_FILES[self::PHOTO_PREFIX . $i] ?? null;
            if (!is_array($tmp) || empty($tmp['tmp_name']) || ($tmp['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $image = Image::create();
            $upload = Upload::create();
            $validator = Upload_Validator::create();
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $upload->setValidator($validator);

            if ($upload->loadIntoFile($tmp, $image, 'review-photos')) {
                $reviewImage = ReviewImage::create();
                $reviewImage->ReviewID = $review->ID;
                $reviewImage->ImageID = $image->ID;
                $reviewImage->Sort = $i;
                $reviewImage->write();
            }
        }
    }

    /**
     * A fully-hidden honeypot (label + input) reused across the review and question forms.
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

    /**
     * May the current visitor ask a question, per the `who_can_ask` config?
     */
    public function CanAsk(): bool
    {
        if (!ProductQuestion::config()->get('allow_qna')) {
            return false;
        }
        if (!ProductQuestion::config()->get('who_can_ask')) {
            return false;
        }
        if (ProductQuestion::config()->get('who_can_ask') === 'members') {
            return (bool) Security::getCurrentUser();
        }

        return true;
    }

    public function QuestionForm(): ?Form
    {
        if (!$this->CanAsk()) {
            return null;
        }

        $member = Security::getCurrentUser();
        $fields = FieldList::create(
            TextareaField::create('Question', _t(self::class . '.Question', 'Your question'))->setRows(3)
        );
        if ($member) {
            $fields->push(HiddenField::create('AuthorName', '', $member->getName()));
            $fields->push(HiddenField::create('AuthorEmail', '', $member->Email));
        } else {
            $fields->push(TextField::create('AuthorName', _t(self::class . '.AuthorName', 'Your name')));
            $fields->push(EmailField::create('AuthorEmail', _t(self::class . '.AuthorEmail', 'Your email'))
                ->setDescription(_t(self::class . '.QEmailPrivate', 'Not published — only used to notify you of an answer.')));
        }
        $fields->push($this->honeypotField());

        $required = $member ? ['Question'] : ['Question', 'AuthorName', 'AuthorEmail'];

        return Form::create(
            $this->getOwner(),
            'QuestionForm',
            $fields,
            FieldList::create(FormAction::create('doPostQuestion', _t(self::class . '.AskSubmit', 'Ask question'))),
            RequiredFieldsValidator::create($required)
        );
    }

    public function doPostQuestion(array $data, Form $form)
    {
        $owner = $this->getOwner();
        $product = $this->product();

        if (!empty($data[self::HONEYPOT])) {
            $form->sessionMessage(_t(self::class . '.QThanks', 'Thank you for your question.'), 'good');
            return $owner->redirectBack();
        }
        if (!$this->CanAsk()) {
            $form->sessionMessage(_t(self::class . '.AskGate', 'Please log in to ask a question.'), 'bad');
            return $owner->redirectBack();
        }

        $text = trim((string) ($data['Question'] ?? ''));
        if ($text === '') {
            $form->sessionMessage(_t(self::class . '.QEmpty', 'Please enter a question.'), 'bad');
            return $owner->redirectBack();
        }

        $member = Security::getCurrentUser();
        $moderation = (bool) ProductQuestion::config()->get('moderation');

        $question = ProductQuestion::create();
        $question->Question = $text;
        $question->AuthorName = $member ? $member->getName() : trim((string) ($data['AuthorName'] ?? ''));
        $question->AuthorEmail = $member ? $member->Email : trim((string) ($data['AuthorEmail'] ?? ''));
        $question->Approved = !$moderation;
        $question->SubjectID = $product->ID;
        $question->SubjectClass = $product->ClassName;
        if ($member) {
            $question->MemberID = $member->ID;
        }
        $question->write();

        $message = $moderation
            ? _t(self::class . '.QThanksModerated', 'Thank you! Your question will appear once it has been approved.')
            : _t(self::class . '.QThanks', 'Thank you for your question.');
        $form->sessionMessage($message, 'good');

        return $owner->redirectBack();
    }
}
