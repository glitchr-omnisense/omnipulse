---
title: Usage policies
order: 45
---

# Usage policies

Omnipulse builds on [omnisense/contracts](https://github.com/glitchr-omnisense/contracts), the
kernel every omni family shares. Each provider declares its **usage policy** as data - a
`policy.json` beside its code, named in its `composer.json` (`extra.omnisense.policy`): the rate
limits its terms set, the rights they give (commercial use, caching, storage, redistribution,
attribution), what every request must carry (a key, a User-Agent), and where and when this was
read (`provenance.verifiedAt`). What the terms do not say is `unknown` (`rateLimits: null`), never
a figure made up.

```php
$registry->policy('reviews');        // Omnisense\Contracts\Policy\UsagePolicy, as the site runs it
$registry->policies();               // every configured provider's: what a credits page lists
$registry->get('reviews')->capabilities()->policy();   // as the provider declares it
```

## The guard

The registry's typed getters - `search()`, `audience()`, `rating()`, `reviews()`, `replies()`,
`invitations()`, `writeUrl()`, `profile()`, `sponsors()`, `tiers()` - give the provider behind its
guard (`Omnipulse\Policy\Guarded*`; `inner()` is the provider itself). Each call is an operation
named after the contract's method - `searchPerformance`, `audience`, `rating`, `reviews`, `reply`,
`invite`, `writeUrl`, `profile`, `sponsors`, `tiers` - checked against every limit that counts it,
then counted, failed or not. Over a limit, the call is never made:
`Omnipulse\Exception\UsageLimitReachedException` (an `OmnipulseException`), its `retryAfter` in
seconds and its `limit`.

The provider's HTTP calls read every answer's `Retry-After` and `RateLimit` fields
(draft-ietf-httpapi-ratelimit-headers): a 429 is a `UsageLimitReachedException` with the wait
asked, and every next call of that provider waits too, without reaching the network. GitHub's
`x-ratelimit-*` fields are read with their epoch reset. `get()`, `all()`, `create()` and `terms()`
stay unguarded.

Counts live in the registry's limiter, an in-memory one by default: an application served by many
processes gives a shared one as the third argument (`Omnisense\Contracts\Policy\RateLimiterInterface`;
omnibridge/symfony's adapter over symfony/rate-limiter).

## A site's tightening

A site may tighten a provider's policy, never loosen it (`InvalidPolicyException` otherwise), and
say what it is (`usage`): a use the terms forbid fails at once (`UsageNotPermitted`).

```php
new Registry($factories, [
    'reviews' => [
        'factory' => 'google-reviews',
        'options' => ['api_key' => '...'],
        'policy' => ['rateLimits' => [['max' => 200, 'window' => 'day', 'operations' => ['rating', 'reviews']]]],
        'usage' => ['commercial' => true],
    ],
], $limiter);
```

## A review platform's Terms

A review platform's `terms()` (`Model\Terms`: how long what it gives may be kept, how it must be
credited, its other rules in words) stays what the family's display follows. Its caching and
attribution are its policy's rights too: `Terms::rights()` maps `cacheFor` (`0`: forbidden, a
number: allowed up to that many seconds, `null`: unknown) and `attribution` (required on
display), and each review provider's tests check that its `policy.json` says the same or stricter.

## The older Capabilities fields

`Capabilities::$key`, `$terms` and `$limits` stay, deprecated: a provider that gives them alone
gets a policy made of them (its limits unknown, the words of `$limits` in its notes; a key required
unless `key: false`), and a provider that gives a policy has them read from it.

## The providers' policies (read on 2026-11-10)

| Provider | Rate limits | Rights | Source |
|---|---|---|---|
| `bing-webmaster` | unknown (no quota published for the statistics calls) | unknown | learn.microsoft.com (IWebmasterApi) |
| `ga4` | 200,000 tokens/day and 40,000/hour per property, 14,000/hour per project per property (as `units`), 120 potentially thresholded requests/hour; 10 concurrent | unknown | developers.google.com/analytics/devguides/reporting/data/v1/quotas |
| `search-console` | Search Analytics 1,200/minute per site and per user, 40,000/minute and 30,000,000/day per project | unknown | developers.google.com/webmaster-tools/limits |
| `matomo` | unknown (the installation's own) | unknown | developer.matomo.org |
| `plausible` | 600/hour per key | unknown | plausible.io/docs/stats-api |
| `google-reviews` | 300/minute (Business Profile; Places' per-method quota unpublished), 10 replies/minute per profile | caching, storage, redistribution forbidden; "Google Maps" credited | developers.google.com/maps/documentation/places/web-service/policies |
| `tripadvisor` | 50/second per key (the daily budget is the account's) | caching, storage, redistribution forbidden; "Tripadvisor" credited | tripadvisor-content-api.readme.io (caching policy, terms) |
| `trustpilot` | 833 per 5 minutes, 10,000/hour | caching up to 24 hours; "Trustpilot" credited | developers.trustpilot.com/rate-limiting |
| `github-sponsors` | 5,000 points/hour, 2,000 points/minute (as `points`); 100 concurrent | unknown | docs.github.com (GraphQL rate limits) |
| `opencollective` | unknown | commercial use and redistribution under contract (ToS 11, 13(b)) | opencollective.com/tos |
| `liberapay` | unknown | unknown | liberapay.com/about/legal |

Each provider's `policy.json` has the details and the reading in `provenance.notes`. A limit
counted in tokens or points is counted one per call by the guard (a lower bound): the platform's
429 and its `Retry-After` stop the rest.
