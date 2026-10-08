<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Task;

use SilverShop\Reviews\Model\ReviewInvitation;
use SilverShop\Reviews\Service\ReviewInvitationService;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Sends review-invitation emails (and reminders) for eligible placed orders. Run from cron, e.g. daily.
 * Dry-run by default (lists eligible orders); pass --run to actually create + send.
 */
class SendReviewInvitationsTask extends BuildTask
{
    protected string $title = 'Send review invitations';

    protected static string $description =
        'Creates and emails tokenised review invitations for eligible orders, plus reminders. Dry-run unless --run.';

    public function getOptions(): array
    {
        return [
            new InputOption('run', null, InputOption::VALUE_NONE, 'Actually create and send invitations (otherwise just list eligible orders).'),
            new InputOption('reminders', null, InputOption::VALUE_NONE, 'Also send reminders for unanswered invitations.'),
            new InputOption('preview', null, InputOption::VALUE_OPTIONAL, 'Order ID: render that order\'s invitation email to screen, without sending.'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $service = ReviewInvitationService::create();

        // Preview mode: render the email for one order without sending.
        $previewOrderId = $input->getOption('preview');
        if ($previewOrderId) {
            $invitation = ReviewInvitation::get()->filter('OrderID', (int) $previewOrderId)->first();
            if (!$invitation) {
                $invitation = ReviewInvitation::create();
                $invitation->OrderID = (int) $previewOrderId;
                $invitation->Token = 'PREVIEW';
            }
            $output->writeln((string) $service->renderEmail($invitation, false));
            return Command::SUCCESS;
        }

        if (!$input->getOption('run')) {
            $eligible = $service->eligibleOrders();
            $output->writeln('Dry run — ' . count($eligible) . ' order(s) eligible for a review invitation:');
            foreach ($eligible as $order) {
                $output->writeln(sprintf('  #%d (%s) — %s', $order->ID, $order->Status, $order->getLatestEmail()));
            }
            $output->writeln('Pass --run to create and send invitations.');
            return Command::SUCCESS;
        }

        $result = $service->sendInvitations();
        $output->writeln(sprintf('Invited %d of %d eligible order(s).', $result['invited'], $result['candidates']));

        if ($input->getOption('reminders')) {
            $reminded = $service->sendReminders();
            $output->writeln(sprintf('Sent %d reminder(s).', $reminded));
        }

        return Command::SUCCESS;
    }
}
