<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Control;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\SecurityToken;

/**
 * Records "was this review helpful?" votes. One vote per review per session (basic dedup); the vote links
 * work without JavaScript (redirect back) and can be progressively enhanced to AJAX.
 */
class ReviewVoteController extends Controller
{
    private static string $url_segment = 'review-vote';

    private static array $allowed_actions = [
        'vote',
    ];

    private static array $url_handlers = [
        '$ReviewID/$Direction' => 'vote',
    ];

    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), 'review-vote', $action);
    }

    public function vote(HTTPRequest $request)
    {
        if (!Review::config()->get('allow_reviews') || !Review::config()->get('allow_votes')) {
            return $this->httpError(404);
        }
        // Voting changes state: require POST + a valid CSRF token (defends against forged votes).
        if (!$request->isPOST()) {
            return $this->httpError(404);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $id = (int) $request->param('ReviewID');
        $direction = (string) $request->param('Direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->httpError(404);
        }

        $review = Review::get()->filter(['ID' => $id, 'Approved' => true])->first();
        if (!$review) {
            return $this->httpError(404);
        }

        $session = $request->getSession();
        $voted = (array) ($session->get('ReviewVotes') ?? []);
        if (!in_array($id, $voted, true)) {
            if ($direction === 'up') {
                $review->HelpfulUp = (int) $review->HelpfulUp + 1;
            } else {
                $review->HelpfulDown = (int) $review->HelpfulDown + 1;
            }
            $review->write();

            $voted[] = $id;
            $session->set('ReviewVotes', $voted);
        }

        // redirectBack() validates the Referer with Director::is_site_url (no open redirect).
        return $this->redirectBack();
    }
}
