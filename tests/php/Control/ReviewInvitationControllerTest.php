<?php

namespace SilverShop\Reviews\Tests\Control;

use SilverShop\Model\Order;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\SiteConfig\SiteConfig;

class ReviewInvitationControllerTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private function makeInvitation(string $status = 'Sent'): ReviewInvitation
    {
        $order = Order::create();
        $order->Status = 'Sent';
        $order->Email = 'buyer@example.com';
        $order->write();

        $invitation = ReviewInvitation::create();
        $invitation->OrderID = $order->ID;
        $invitation->Status = $status;
        $invitation->write();

        return $invitation;
    }

    public function testLandingCreatesVerifiedShopReviewAndMarksResponded(): void
    {
        $invitation = $this->makeInvitation();
        $orderID = $invitation->OrderID;

        $page = $this->get('review/' . $invitation->Token);
        $this->assertSame(200, $page->getStatusCode(), 'the token landing page loads');

        $this->submitForm('Form_ReviewInvitationForm', 'action_doSubmitReviews', [
            'Rating_shop' => 5,
            'Title_shop' => 'Great shop',
            'Content_shop' => 'Smooth from start to finish',
        ]);

        $shopReview = Review::get()
            ->filter(['OrderID' => $orderID, 'SubjectClass' => SiteConfig::class])
            ->first();
        $this->assertNotNull($shopReview, 'an overall shop review was created from the invitation');
        $this->assertSame(5, (int) $shopReview->Rating);
        $this->assertTrue((bool) $shopReview->Verified, 'invitation reviews are verified');
        $this->assertSame((int) $orderID, (int) $shopReview->OrderID, 'the review is linked to the order');

        $invitation = ReviewInvitation::get()->byID($invitation->ID);
        $this->assertSame('Responded', $invitation->Status, 'the invitation is marked responded');
    }

    public function testUnsubscribe(): void
    {
        $invitation = $this->makeInvitation();

        $this->get('review/unsubscribe/' . $invitation->Token);

        $invitation = ReviewInvitation::get()->byID($invitation->ID);
        $this->assertSame('Unsubscribed', $invitation->Status);
    }

    public function testInvalidTokenIs404(): void
    {
        $page = $this->get('review/does-not-exist');
        $this->assertSame(404, $page->getStatusCode());
    }
}
