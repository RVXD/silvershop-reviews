<?php

namespace SilverShop\Reviews\Tests\Service;

use SilverShop\Model\Order;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverShop\Reviews\Provider\AbstractReviewProvider;
use SilverShop\Reviews\Provider\ReviewProviderRegistry;
use SilverShop\Reviews\Service\ReviewInvitationService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;

class ReviewInvitationServiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function makeOrder(string $email = 'buyer@example.com', string $status = 'Sent'): Order
    {
        $order = Order::create();
        $order->Status = $status;
        $order->Email = $email;
        $order->write();
        return $order;
    }

    private function makeInvitation(Order $order, string $status = 'Pending'): ReviewInvitation
    {
        $invitation = ReviewInvitation::create();
        $invitation->OrderID = $order->ID;
        $invitation->Status = $status;
        $invitation->write();
        return $invitation;
    }

    private function activateProvider(): void
    {
        Config::modify()->set(ReviewProviderRegistry::class, 'providers', ['test' => RecordingHandOffProvider::class]);
        Config::modify()->set(ReviewProviderRegistry::class, 'active', 'test');
        RecordingHandOffProvider::$calls = [];
    }

    public function testReferenceDatePriority(): void
    {
        $service = ReviewInvitationService::create();
        $order = Order::create();

        $order->Placed = '2026-01-01 00:00:00';
        $this->assertSame('2026-01-01 00:00:00', $service->referenceDate($order));

        $order->Paid = '2026-02-01 00:00:00';
        $this->assertSame('2026-02-01 00:00:00', $service->referenceDate($order));

        $order->Dispatched = '2026-03-01 00:00:00';
        $this->assertSame('2026-03-01 00:00:00', $service->referenceDate($order));
    }

    public function testSendHandsOffToActiveProvider(): void
    {
        $this->activateProvider();
        $order = $this->makeOrder('hand@example.com');
        $invitation = $this->makeInvitation($order);

        $this->assertTrue(
            ReviewInvitationService::create()->send($invitation, false),
            'send() delegates to the active provider and returns its result'
        );
        $this->assertSame([(int) $order->ID], RecordingHandOffProvider::$calls, 'the provider received the order');
    }

    public function testSendReturnsFalseWithoutAnEmail(): void
    {
        $order = $this->makeOrder(''); // no email, no member
        $invitation = $this->makeInvitation($order);

        $this->assertFalse(ReviewInvitationService::create()->send($invitation, false));
    }

    public function testRemindersSkippedWhenAProviderHandlesInvitations(): void
    {
        $this->activateProvider();

        $this->assertSame(
            0,
            ReviewInvitationService::create()->sendReminders(),
            'native reminders are skipped while a provider handles invitations'
        );
    }
}

class RecordingHandOffProvider extends AbstractReviewProvider implements TestOnly
{
    /** @var array<int> */
    public static array $calls = [];

    public function getKey(): string
    {
        return 'test';
    }

    public function getName(): string
    {
        return 'Test';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportsInvitations(): bool
    {
        return true;
    }

    public function sendInvitation(Order $order): bool
    {
        self::$calls[] = (int) $order->ID;
        return true;
    }
}
