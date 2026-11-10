---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnipulse omnipulse/matomo symfony/http-client   # one package per provider
composer require omnipulse/google-reviews omnipulse/liberapay               # whichever families the site needs
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omnipulse` requires `symfony/http-client-contracts` (^3.0) and `ext-mbstring`,
  nothing else. It replaces the former names `omnisense/omnireach`, `omnisense/omnireview`,
  `omnisense/omnifund` (and `omniscient/omnireach`, `omniscient/omnireview`, `glitchr/omnireview`,
  `omniscient/omnifund`): a project never installs both, but the namespaces changed, so code that
  used them moves to `Omnipulse\` ([the README](../README.md)).
- The HTTP client is the application's. Any implementation of
  `Symfony\Contracts\HttpClient\HttpClientInterface` does - `symfony/http-client` (a library that
  stands alone and loads no framework) with `HttpClient::create()`, or a `MockHttpClient` in a
  test. What each family's providers do without one:
  - the audience's (`search-console`, `bing-webmaster`, `matomo`, `plausible`, `ga4`) require the
    contracts only; a factory given no client refuses to build (`InvalidConfigException`: `The
    "matomo" provider needs an HTTP client: give one to MatomoFactory.`);
  - the review platforms' (`google-reviews`, `tripadvisor`, `trustpilot`) require
    `symfony/http-client` and make their own with `HttpClient::create()` when given none;
  - the funding platforms' (`github-sponsors`, `opencollective`, `liberapay`) require the
    contracts only and take a client in their constructor, not nullable.
- `omnipulse/search-console` and `omnipulse/ga4` require `ext-openssl`: they sign the JWT a Google
  service account's token is asked with.

- The Symfony bundle (`Omnipulse\Bridge\Symfony\OmnipulseBundle`) needs `symfony/config`,
  `symfony/dependency-injection` and `symfony/http-kernel`; the Twig functions `twig/twig`, and
  `symfony/cache-contracts` to keep the reviews they show. None is required by the package
  ([Symfony and Twig](symfony.md)).

Omnipulse runs the same in plain PHP, in a worker, in Laravel and in Symfony. Three first
scripts follow, one per family; each runs with no key.

## Plain PHP: the audience

A whole script with no provider and no network: the core's `FixedReach` answers from rows it was
given, as a provider would have returned them. It shows the pieces every provider shares.

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\Metric;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Row;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReachFactory;

// Rows a provider would have returned: search queries by day, visits by entry page and source.
$search = [
    new Row(['query' => 'atelier poterie strasbourg', 'date' => '2026-10-20'], ['clicks' => 12, 'impressions' => 340, 'ctr' => 0.0353, 'position' => 4.2]),
    new Row(['query' => 'cours de poterie', 'date' => '2026-10-21'], ['clicks' => 3, 'impressions' => 410, 'ctr' => 0.0073, 'position' => 11.8]),
    new Row(['query' => 'poterie', 'date' => '2026-09-01'], ['clicks' => 1, 'impressions' => 90, 'ctr' => 0.0111, 'position' => 23.0]),
];
$audience = [
    new Row(['entry_page' => '/', 'source' => 'google'], ['visitors' => 210, 'visits' => 260, 'pageviews' => 700]),
    new Row(['entry_page' => '/ateliers', 'source' => 'google'], ['visitors' => 95, 'visits' => 110, 'pageviews' => 300]),
    new Row(['entry_page' => '/ateliers', 'source' => 'direct'], ['visitors' => 20, 'visits' => 25, 'pageviews' => 40]),
];

$registry = new Registry([new FixedReachFactory($search, $audience)], [
    'site' => ['factory' => 'fixed', 'options' => ['site' => 'https://atelier.example.org/']],
]);

$october = [new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')];

// Search queries in October (the September row is outside the period).
$report = $registry->search('site')->searchPerformance(new ReachQuery(...$october, dimensions: [Dimension::Query]));
foreach ($report->rows as $row) {
    printf("%-28s %3d clicks %4d impressions  position %4.1f\n", $row->dimension(Dimension::Query), $row->metric(Metric::Clicks), $row->metric(Metric::Impressions), $row->metric(Metric::Position));
}
echo 'totals: ', json_encode($report->totals()), "\n\n";

// Visits by entry page, from Google only.
$entries = $registry->audience('site')->audience(new ReachQuery(...$october, dimensions: [Dimension::EntryPage], metrics: [Metric::Visits], filters: ['source' => 'google']));
foreach ($entries->rows as $row) {
    printf("%-10s %d visits\n", $row->dimension(Dimension::EntryPage), $row->metric(Metric::Visits));
}

// The last 28 whole days, as seen on 2026-10-26: yesterday is the last.
$last = ReachQuery::lastDays(28, today: new DateTimeImmutable('2026-10-26'));
echo "\nlastDays(28): ", $last->start->format('Y-m-d'), ' to ', $last->end->format('Y-m-d'), "\n";

// What a provider's capabilities refuse, before any call.
$registry2 = new Registry([new Omnipulse\Matomo\MatomoFactory(new Omnipulse\Testing\RecordedHttpClient(static fn () => [500, '']))], [
    'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1']],
]);
try {
    $registry2->audience('stats')->audience(new ReachQuery(...$october, dimensions: [Dimension::Query]));
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
try {
    $registry2->search('stats');
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php bare.php
atelier poterie strasbourg    12 clicks  340 impressions  position  4.2
cours de poterie               3 clicks  410 impressions  position 11.8
totals: {"clicks":15,"impressions":750}

/          260 visits
/ateliers  110 visits

lastDays(28): 2026-09-28 to 2026-10-25
Matomo does not report the dimension "query".
The "stats" provider (Matomo) does not report search performance; it gives: audience.
```

(run on 2026-10-26 in the [harness](harness.md), PHP 8.4, with the harness's autoloader; the
Matomo part needs `omnipulse/matomo` installed. Its last two lines were run again on 2026-11-10,
after the merge: the registry's message now names the provider's title and what it gives)

The pieces:

- a **factory** per provider package (`FixedReachFactory`, `MatomoFactory`,
  `SearchConsoleFactory`...), given the HTTP client to call with;
- the **registry**, built from the factories and the providers' options, by name: `get()`, and the
  typed `search()` and `audience()` (and the other families' getters), which refuse a provider that
  does not answer that contract;
- the **query**: whole days, both included, dimensions, metrics (none: the provider's defaults),
  exact filters, a limit; `ReachQuery::lastDays()` ends yesterday, since no tool has today's
  figures complete;
- the **report**: rows of dimensions and metrics in the family's units, `totals()` of the metrics
  that add up (clicks, impressions, visits, pageviews - not the averages, not distinct visitors).

The query asking Matomo for search queries was refused by its capabilities: no HTTP call was made
(the client given would have answered 500).

## An audience provider's API

Matomo publishes a demo whose reports anyone may read, so this one runs without an account:

```php
use Omnipulse\Matomo\MatomoFactory;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\Metric;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new MatomoFactory(HttpClient::create(['timeout' => 30]))], [
    // Matomo's public demo: site 1, reports anyone may view (token "anonymous", the default).
    'demo' => ['factory' => 'matomo', 'options' => ['url' => 'https://demo.matomo.cloud', 'site' => '1']],
]);
$matomo = $registry->audience('demo');

$week = ReachQuery::lastDays(7);
echo $week->start->format('Y-m-d'), ' to ', $week->end->format('Y-m-d'), "\n";

$site = $matomo->audience(new ReachQuery($week->start, $week->end, metrics: [Metric::Visits, Metric::Pageviews, Metric::BounceRate, Metric::VisitDuration]));
echo json_encode($site->rows[0]->metrics), "\n\n";

$entries = $matomo->audience(new ReachQuery($week->start, $week->end, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate], limit: 5));
foreach ($entries->rows as $row) {
    printf("%-40s %6d visits  %4.1f%% bounce\n", $row->dimension(Dimension::EntryPage), $row->metric(Metric::Visits), 100 * $row->metric(Metric::BounceRate));
}
```

```
$ php matomo-demo.php
2026-10-19 to 2026-10-25
{"visits":98616,"pageviews":192623,"bounce_rate":0.6655,"visit_duration":180.2}

/cart/                                     2669 visits  49.1% bounce
/jobs                                      5688 visits  52.2% bounce
/                                          3342 visits  47.4% bounce
/checkout/                                 1300 visits  61.4% bounce
/diving/bali                               3813 visits  73.9% bounce
```

(run live on 2026-10-26, 14:49 UTC, in the harness; two calls to demo.matomo.cloud)

The rows come in the order Matomo gives them, not sorted by visits; sort them in the application
when the order matters ([Audience](usage-audience.md)). The other providers need an account and a
key: their manuals show the same calls with their options. Building a provider calls nothing; a
missing required option is reported by `create()` as an `InvalidConfigException` naming it
(`The "plausible" provider needs: api_key.`).

## Plain PHP: funding

The block below runs as written in the tests (`Tests/DocsTest.php`, which checks the three
printed lines against the comments), with a recorded answer in place of Liberapay's.

```php
<?php

require 'vendor/autoload.php';

use Omnipulse\Liberapay\LiberapayFactory;
use Omnipulse\Registry;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// In production: Symfony\Component\HttpClient\HttpClient::create(['timeout' => 10]).
$http = new MockHttpClient(new MockResponse('{"username":"Liberapay","display_name":null,"npatrons":1782,"receiving":{"amount":"636.28","currency":"EUR"},"goal":{"amount":"1000.00","currency":"EUR"},"summaries":[]}'));

$registry = new Registry([new LiberapayFactory($http)], [
    'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
]);
$profile = $registry->profile('liberapay')->profile();

echo $profile->sponsors, " patrons\n";
// 1782 patrons
echo $profile->income?->monthly()?->decimal(), ' ', $profile->income?->amount->currency, " a month\n";
// 2757.21 EUR a month
echo $profile->goal?->percent, "% of the goal\n";
// 63% of the goal
```

Liberapay counts by the week: `Income::monthly()` turns 636.28 a week into a month of 52/12
weeks. Several funding platforms sit in one registry, each under the name the application gives
it:

```php
use Omnipulse\GitHubSponsors\GitHubSponsorsFactory;
use Omnipulse\Liberapay\LiberapayFactory;
use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\Registry;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create(['timeout' => 10]);
$registry = new Registry([new GitHubSponsorsFactory($http), new OpenCollectiveFactory($http), new LiberapayFactory($http)], [
    'github' => ['factory' => 'github-sponsors', 'options' => ['account' => 'glitchr-studio', 'token' => $_ENV['GITHUB_TOKEN']]],
    'oc' => ['factory' => 'opencollective', 'options' => ['account' => 'webpack']],
    'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
]);
foreach ($registry->sponsors('github')->sponsors() as $sponsor) { /* $sponsor->label(), ->avatarUrl, ->url */ }
```

Building a provider calls nothing: it is built the first time the registry is asked for it, its
required options checked then (`The "github-sponsors" provider needs: token.`), and a platform
is called only by `profile()`, `sponsors()` or `tiers()`. Cache what you read: sponsors change
slowly, and each platform limits its calls ([Funding](usage-funding.md#caching)).

## Plain PHP: reviews

So that it runs without keys, the HTTP client answers from the review providers' recorded answers
(the harness's `docker/harness/config/recorded.php`, a `MockHttpClient` callback serving each
provider's `Tests/Fixtures`); with keys, give the factories `HttpClient::create()` instead, or
nothing (they make one).

```php
<?php // reviews.php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\Exception\NotSupportedException;
use Omnipulse\GoogleReviews\GoogleReviewsFactory;
use Omnipulse\Model\Place;
use Omnipulse\Registry;
use Omnipulse\Trustpilot\TrustpilotFactory;
use Omnipulse\Trustpilot\TrustpilotProvider;
use Symfony\Component\HttpClient\MockHttpClient;

// The platforms' recorded answers, as the harness serves them: nothing leaves the machine.
$http = new MockHttpClient(require __DIR__.'/vendor/omnisense/omnipulse/docker/harness/config/recorded.php');

$registry = new Registry([new GoogleReviewsFactory($http), new TrustpilotFactory($http)], [
    'google' => ['factory' => 'google-reviews', 'options' => ['api_key' => getenv('GOOGLE_PLACES_KEY') ?: 'example', 'language' => 'fr']],
    'trustpilot' => ['factory' => 'trustpilot', 'options' => ['api_key' => getenv('TRUSTPILOT_API_KEY') ?: 'example']],
]);

$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0');           // a Google place ID: the one thing Google lets you keep
$rating = $registry->rating('google')->rating($place);
printf("%s/%d from %d reviews - %s\n", $rating->value, $rating->scale, $rating->count, $rating->url);
foreach ($registry->reviews('google')->reviews($place, 3) as $review) {
    printf("  %s by %s on %s%s: %s\n", $review->rating, $review->author, $review->publishedAt?->format('Y-m-d'), $review->translated ? ' (translated by Google)' : '', $review->text);
}
echo 'to write one: ', $registry->writeUrl('google')->writeUrl($place), "\n";

$terms = $registry->terms('google');
printf("kept: %s; credited to %s; read at %s\n", 0 === $terms->cacheFor ? 'never' : $terms->cacheFor.' s', $terms->attribution, $terms->source);

$trustpilot = $registry->get('trustpilot');
if ($trustpilot instanceof TrustpilotProvider) {
    $unit = $trustpilot->find('www.maison-erable.example');      // TrustpilotProvider's own: once, then keep the ID
    echo 'trustpilot business unit: ', $unit->id, ' (', $unit->name, ")\n";
}
echo 'trustpilot gives: ', implode(', ', $trustpilot->capabilities()->contracts()), "\n";

try {
    $registry->invitations('google');                          // Google has no invitation API
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php reviews.php
4.6/5 from 128 reviews - https://maps.google.com/?cid=10223305813012340704
  5 by Camille R. on 2026-09-23: Un accueil chaleureux, une cuisine remarquable.
  4 by Jordan K. on 2026-09-01 (translated by Google): Très bon, un peu d'attente.
to write one: https://search.google.com/local/writereview?placeid=ChIJLU7jZClu5kcR4PcOOO6p3I0
kept: never; credited to Google Maps; read at https://developers.google.com/maps/documentation/places/web-service/policies
trustpilot business unit: 46d6a890000064000500e0c3 (Maison Érable)
trustpilot gives: rating, reviews, writeUrl
The "google" provider (Google) does not give inviting a customer to review; it gives: rating, reviews, writeUrl.
```

(run on 2026-11-10 in the [harness](harness.md), PHP 8.4, the providers from their checkouts and
no network; the harness's paths in place of `vendor/omnisense/omnipulse`)

`find()` is not part of a contract: it is `TrustpilotProvider`'s own method (a business unit
looked up by its domain), reached through `get()`. `terms()` is a review platform's: how what it
gives may be shown and kept ([Reviews](usage-reviews.md#the-terms)).

What the application does around a review platform:

1. **Find** the place once - a Google place ID, a Tripadvisor location ID, a Trustpilot business
   unit (`find()` by domain) - and keep that ID: every platform allows it.
2. **Show** the rating and a few reviews with their origin, date and link - the Twig widget does,
   or the application's own template, keeping to `terms()`. Keep them no longer than
   `terms()->cacheFor`.
3. **Link** to the platform to read them all and to write one (`writeUrl()`).
4. As the establishment, with its own access: **reply** (`ReplyInterface`), **invite** a customer
   after a purchase (`InviteInterface`) - where the platform allows it.

No class of a framework is loaded by any of these scripts: `Tests/BareTest.php` and
`Tests/ReviewPlatformsTest.php` check it in processes of their own ([harness](harness.md)).

## In a framework

- **Symfony**: `Omnipulse\Bridge\Symfony\OmnipulseBundle`, in this package, builds the registry
  from `config/packages/omnipulse.yaml`, makes every provider injectable by its name, and
  registers the Twig functions for the reviews ([Symfony and Twig](symfony.md)).
- **Laravel**: no provider in omnibridge/laravel yet; a service provider of the application's
  builds the registry once ([Laravel](laravel.md)).
- **Any other**: build the `Registry` once, where the framework builds its services, as the
  scripts above do.
