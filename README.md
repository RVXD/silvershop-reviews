# silvershop/reviews

Product and shop reviews for [SilverShop](https://github.com/silvershop/silvershop-core) (SilverStripe CMS 6):
star ratings, moderation, verified purchases, review-invitation emails, photo reviews, helpful votes, a product
Q&A, and optional bridges to external review providers (Trustpilot, Kiyoh / Klantenvertellen, The Feedback Company).

## Requirements

- `silvershop/core` ^6
- `silverstripe/framework` ^6 / PHP 8.3+

## Installation

```bash
composer require silvershop/reviews
```

Then run a dev/build (`/dev/build?flush=1`). The extensions are applied automatically to `Product`,
`ProductController` and `SiteConfig`.

### Show reviews on the product page

Add the reviews block to your product template (e.g. `themes/<theme>/templates/SilverShop/Page/Layout/Product.ss`),
inside the product scope:

```ss
<% include SilverShop\Reviews\ProductReviews %>
```

This renders the rating summary, review list (with photos + helpful votes), the "write a review" form, and the
Q&A section. The template is self-styled; override it by copying it into your theme.

Optional drop-ins:

```ss
<%-- a compact store-rating badge for the footer/homepage --%>
<% include SilverShop\Reviews\ShopRating %>

<%-- Organization aggregate-rating JSON-LD, e.g. in the site <head> --%>
<% include SilverShop\Reviews\ShopRatingSchemaOrg %>
```

Product-level `aggregateRating` + review JSON-LD is emitted automatically by the product reviews include.

## Routes

| Path | Purpose |
|---|---|
| `review/{token}` | Tokenised review-invitation landing (leave reviews for a purchased order) |
| `review/unsubscribe/{token}` | Opt the order out of invitations |
| `shop-review` | Public "review our shop" page |
| `review-vote/{reviewID}/{up\|down}` | Record a helpful vote |

## Moderation (CMS)

- **Reviews** admin (left menu) — moderate reviews, questions, and review invitations.
- Each review has a **Photos** tab; each question has an **Answers** tab (mark answers `IsStaff` for a "Shop" badge).
- **Settings → Shop reviews** — the store's own reviews + aggregate.

Reviews/questions are hidden until approved (see `moderation` below).

## Configuration

All options are set via YAML. Example `app/_config/reviews.yml`:

```yaml
SilverShop\Reviews\Model\Review:
  allow_reviews: true        # master switch for the whole reviews section (list + form + votes)
  moderation: true           # reviews hidden until approved
  who_can_review: 'anyone'   # 'anyone' | 'members' | 'verified' (must have purchased the product)
  submit_throttle_seconds: 30
  allow_votes: true          # helpful 👍/👎 votes
  allow_photos: true         # photo uploads on reviews
  max_photos: 3

SilverShop\Reviews\Model\ProductQuestion:
  allow_qna: true            # master switch for the whole Q&A section
  moderation: true           # questions hidden until approved
  who_can_ask: 'anyone'      # 'anyone' | 'members'
```

### Review-invitation emails

A cron-run task sends invitations (and one reminder) for eligible orders.

```yaml
SilverShop\Reviews\Service\ReviewInvitationService:
  trigger_statuses: [Sent, Complete]   # order statuses that make an order eligible
  delay_days: 14                       # wait this long after dispatch / paid / placed
  reminder_enabled: true
  reminder_delay_days: 7               # one reminder if still unanswered
  max_per_run: 50                      # safety cap per run
  from_email: ''                       # defaults to store-profile ContactEmail, then the framework admin email
```

Schedule the task daily (dry-run without `--run`):

```bash
# CLI
vendor/bin/sake tasks:SilverShop-Reviews-Task-SendReviewInvitationsTask --run --reminders
# or in a browser (dev): /dev/tasks/SilverShop-Reviews-Task-SendReviewInvitationsTask?run=1&reminders=1
# preview one order's email without sending: ...?preview=<orderID>
```

The sender identity (name + logo) comes from [`silvershop/store-profile`](https://github.com/silvershop/silvershop-store-profile)
when installed. Make sure a mail transport (`MAILER_DSN`) is configured for real delivery.

### External review providers (optional)

Delegate invitations and/or show an external aggregate rating. Pick one `active` provider and set its credentials.
Each adapter is inert until configured; a provider that handles invitations replaces the native invitation email.

```yaml
SilverShop\Reviews\Provider\ReviewProviderRegistry:
  active: 'trustpilot'       # '' = native reviews only; or 'kiyoh' / 'feedbackcompany'

SilverShop\Reviews\Provider\TrustpilotProvider:
  api_key: '...'
  business_unit_id: '...'
  invitations_token: '...'   # optional; enables invitation hand-off

SilverShop\Reviews\Provider\KiyohProvider:
  api_key: '...'
  location_id: '...'

SilverShop\Reviews\Provider\FeedbackCompanyProvider:
  client_id: '...'
  client_secret: '...'
  shop_id: '...'
```

Fetched ratings are cached (`SilverShop\Reviews\Provider\AbstractReviewProvider.cache_ttl`, default 3600s).

> Provider endpoints/fields follow each vendor's public docs and should be verified against their current API with
> a real account before production. All provider calls fail soft (never break page rendering).

## Testing

```bash
composer install
composer lint    # phpcs (PSR-12)
composer stan    # phpstan level 5
composer test    # phpunit
```

CI runs all three on PHP 8.3 and 8.4.

## License

BSD-3-Clause
