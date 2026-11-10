---
title: Reviews
order: 33
---

# Reviews

What customers say of an establishment on Google, Tripadvisor and Trustpilot, one section per
capability. Every example takes a `Registry` built as in
[Installation](installation.md) (or injected, [Symfony](symfony.md)); `$place` is a
`Omnipulse\Model\Place` ([Models](models.md#place)).

## What each platform gives

| | Google | Tripadvisor | Trustpilot |
|---|---|---|---|
| Rating | Places API (New); the owner's Business Profile when the `Place` has an `owner` and the owner's access is set | Content API, `/details` | Data Solutions API, `/business-units/{id}` |
| Reviews | 5 (Places), up to 50 (Business Profile) | 5 | up to 100 |
| Reply | Business Profile: the owner's refresh token and `Place::$owner` | - (not in the Content API) | the business user's token |
| Invite | - (Google has none: share `writeUrl()`) | - | Invitations API |
| Write URL | `search.google.com/local/writereview?placeid=`, nothing called | `write_review` of `/details` | `evaluateUrl` of `/web-links` |
| Kept | never; the place ID only | per its Caching Policy (not public): not kept | 24 hours at most |
| Pages not indexed | no | **yes** | no |

Each provider's manual has its options, its calls, its terms in full and what was verified. The
factory names are `google-reviews`, `tripadvisor` and `trustpilot` ([Configuration](configuration.md#omnipulsegoogle-reviews)).

## Asking for a capability

The registry's typed getters give the provider as the interface asked for:

| Getter | Returns | |
|---|---|---|
| `rating($name)` | `RatingInterface` | |
| `reviews($name)` | `ReviewsInterface` | |
| `replies($name)` | `ReplyInterface` | |
| `invitations($name)` | `InviteInterface` | |
| `writeUrl($name)` | `WriteUrlInterface` | |

A provider that does not implement the interface, or does not give it as configured (its
`capabilities()` say no: Trustpilot without a business user does not reply), is a
`NotSupportedException` naming what it gives: `The "google" provider (Google) does not give
inviting a customer to review; it gives: rating, reviews, writeUrl.`, `The "trustpilot" provider
(Trustpilot) does not give replying to a review; it gives: rating, reviews, writeUrl.` (run on
2026-11-10). `terms($name)` gives the platform's `Terms`.

So test `capabilities()` before offering an action, rather than catch the getter's exception:

```php
$provider = $registry->get('trustpilot');
if ($provider->capabilities()->reply) {
    // show the "Reply" button
}
```

## Rating

```php
$rating = $registry->rating('tripadvisor')->rating(new Place('1234567'));

printf('%.1f/%d from %d reviews', $rating->value, $rating->scale, $rating->count);   // 4.5/5 from 312 reviews
$rating->url;          // the establishment's page on Tripadvisor: link it
$rating->image;        // Tripadvisor's bubbles: show them from that address
$rating->outOfFive();  // the score on 5, for a scale other than 5
```

- Google: `rating` and `userRatingCount` of the place (`X-Goog-FieldMask:
  id,displayName,rating,userRatingCount,googleMapsUri`), `url` its `googleMapsUri`. For a `Place`
  with an `owner` and a provider with the owner's access, `averageRating` and `totalReviewCount` of
  the Business Profile instead, `url` a Google Maps search link of the place.
- Tripadvisor: `rating`, `num_reviews`, `web_url`, `rating_image_url` of `/details` in the
  provider's `language`.
- Trustpilot: `score.trustScore` and `numberOfReviews.total`, `url` the `profileUrl` of the
  business unit's `/web-links` (a second call).

A platform that does not know the place answers with an error: `ProviderException` (Google's
`NOT_FOUND` in `providerCode`).

## Reviews

```php
foreach ($registry->reviews('google')->reviews($place, limit: 3, language: 'en') as $review) {
    echo $review->author, ' ', $review->publishedAt?->format('Y-m-d'), ' ', $review->rating, '/', $review->scale, "\n";
    echo '"', $review->text, '"', $review->translated ? ' (translated by Google)' : '', "\n";
    echo $review->url, "\n";
    if ($review->reply) {
        echo '  the establishment: ', $review->reply->text, "\n";
    }
}
```

The reviews come in the platform's order, never more than `$limit` (each provider slices what it
is given) and never more than the platform gives:

| | Asked | Order | `$language` |
|---|---|---|---|
| Google, Places | the place's `reviews` field: Google gives 5 at most | Google's relevance | `languageCode`; Google translates the others and says so (`translated`) |
| Google, Business Profile | `pageSize` = `$limit` between 1 and 50 | `updateTime desc` | not sent |
| Tripadvisor | `limit` = `$limit` between 1 and 5 | Tripadvisor's: the most recent | `language` (the provider's when `null`); `translated` from `is_machine_translated` |
| Trustpilot | `perPage` = `$limit` between 1 and 100 | `createdat.desc` | `language`, a filter; not sent when `null` |

A review's `url` is its page on the platform where the platform gives one (Google's Places,
Tripadvisor); Trustpilot's is built as `https://www.trustpilot.com/reviews/<id>` (its API's own
links point to the API); Google's Business Profile gives none, and the place's Google Maps link
stands in.

## Replying

As the establishment, with its own access (Google: `client_id`, `client_secret`, `refresh_token`
and a `Place` with `owner`; Trustpilot: `api_secret` and `business_user_id`):

```php
$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0', 'Maison Érable', 'accounts/101/locations/202');
$google = $registry->replies('google');

foreach ($registry->reviews('google')->reviews($place, 10) as $review) {
    if (null === $review->reply && $review->rating <= 3) {
        $reply = $google->reply($place, $review, 'Merci pour votre retour, nous en tenons compte.');
        echo $reply->text, ' ', $reply->at?->format(DATE_ATOM), "\n";
    }
}
```

The review must come from the owner's side: its `id` is the identifier the reply goes to (a
Google Places review's `id` is not a Business Profile review ID). Google answers with the reply as
published (`PUT .../reviews/{id}/reply`); Trustpilot's
`Reply` is the text sent, dated now. Without the access, `reply()` throws `NotSupportedException`
before any call.

## Inviting

Trustpilot only, with a business user:

```php
use Omnipulse\Model\Invitation;

$reference = $registry->invitations('trustpilot')->invite(new Place($businessUnitId), new Invitation(
    email: 'camille@example.org',
    name: 'Camille R.',
    reference: 'ORDER-42',
    locale: 'fr-FR',
    sendAt: new DateTimeImmutable('+3 days'),
    redirectUrl: 'https://www.maison-erable.example/merci',
));
```

The invitation's id comes back, or `null` when Trustpilot gives none. Invite every customer alike:
Google and Trustpilot both forbid soliciting only those likely to be pleased. Google has no
invitation API: give a customer `writeUrl()` instead.

## The link to write a review

```php
$registry->writeUrl('google')->writeUrl($place);       // https://search.google.com/local/writereview?placeid=ChIJ...
```

Google's is built without a call; Tripadvisor's is the `write_review` of the location's details
(the same call as its rating); Trustpilot's the `evaluateUrl` of `/web-links` in the provider's
`locale`. A platform that gives none is a `ProviderException` (`The location gives no
write_review link.`).

## The terms

```php
$terms = $registry->terms('tripadvisor');

$terms->attribution;              // "Tripadvisor": show it, linked to $rating->url
$terms->noIndex;                  // true: the page that shows it carries <meta name="robots" content="noindex">
$terms->allowsCaching(3600);      // false: not kept
foreach ($terms->rules as $rule) {
    echo $rule, "\n";             // the rest, in words
}
```

The Twig extension applies `cacheFor`, `attribution`, `authors`, `link` and `noIndex` (the
widget's `data-nosnippet`); an application that renders by itself applies them too
([Symfony and Twig](symfony.md#what-the-widget-shows)).
