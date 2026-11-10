---
title: Testing
order: 60
---

# Testing

Testing an application that uses Omnipulse, whichever family. Writing a provider and its tests:
[Writing a provider](providers.md). The core ships one fake per family:

| Family | Provider | Factory, name | Gives |
|---|---|---|---|
| audience | `Testing\FixedReach` | `FixedReachFactory`, `fixed` | search performance and audience, from rows given |
| reviews | `Testing\FixedReviews` | `FixedReviewsFactory`, `fixed-reviews` | rating, reviews, writeUrl, a place that does not answer |
| funding | `Testing\ArrayProvider` | `ArrayFactory`, `array` | profile, sponsors, tiers, from models given |

## The fixed provider as a fake

`Omnipulse\Testing\FixedReach` answers both contracts from rows it was given, with no network; its
factory, `FixedReachFactory`, registers it under the name `fixed`. Configure the provider the
application uses with it in tests:

```php
use Omnipulse\Model\Row;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReachFactory;

$registry = new Registry([new FixedReachFactory(
    search: [new Row(['query' => 'atelier poterie', 'date' => '2026-10-20'], ['clicks' => 12, 'impressions' => 340])],
    audience: [new Row(['entry_page' => '/', 'date' => '2026-10-20'], ['visits' => 260])],
)], [
    'google' => ['factory' => 'fixed'],
    'stats' => ['factory' => 'fixed', 'options' => ['site' => 'https://atelier.example.org/']],
]);
```

What it does with a query:

- its capabilities are every dimension and every metric, combined: it refuses nothing but a metric
  or dimension that does not exist;
- a row with a `date` outside the period is left out; a row without one is kept;
- the query's filters and limit are applied (`ReachQuery::apply()`);
- each row is projected on the query's dimensions, and on its metrics when it names some. Rows are
  not summed: two rows of the same query on two days, asked by query only, stay two rows. Give it
  the rows as the test expects them;
- the report's `provider` is `fixed`, its `site` the query's, else the `site` option, else
  `https://example.org/`.

## The fixed review platform

`Omnipulse\Testing\FixedReviews` is a review platform kept in memory; its factory,
`FixedReviewsFactory`, registers it under the name `fixed-reviews` (options: `host`, required,
and `cache_for`, [Configuration](configuration.md#fixed-reviews)):

```php
use Omnipulse\Model\Place;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReviewsFactory;

$registry = new Registry([new FixedReviewsFactory()], [
    'google' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'reviews.example', 'cache_for' => 0]],
]);

$registry->rating('google')->rating(new Place('p1'));      // 8.6/10 from 42, https://reviews.example/p1
$registry->reviews('google')->reviews(new Place('p1'));    // one review, by Camille R., on 2026-09-30
$registry->writeUrl('google')->writeUrl(new Place('p1'));  // https://reviews.example/write/p1
$registry->rating('google')->rating(new Place('down'));    // UnreachableException: [fixed-reviews] No answer.
```

(the harness's bare script does the same, run on 2026-11-10.) Its `Terms` are `cacheFor` the
`cache_for` option, `maxReviews` 5, `attribution` `Fixed Reviews`; `$calls` counts the ratings and
reviews it answered, to test what a cache kept (`Tests/Bridge/OmnipulseExtensionTest.php` does).
The place `down` raises `UnreachableException` from `rating()` and `writeUrl()`: the path of a
platform down.

## The array provider

`Omnipulse\Testing\ArrayProvider` answers what it was given, with every contract: for tests,
demos and an application's fixtures. Its factory, `ArrayFactory` (name `array`), takes the
models as options:

```php
use Omnipulse\Model\Goal;
use Omnipulse\Model\Income;
use Omnipulse\Model\Money;
use Omnipulse\Model\Period;
use Omnipulse\Model\Profile;
use Omnipulse\Model\Sponsor;
use Omnipulse\Model\Tier;
use Omnipulse\Registry;
use Omnipulse\Testing\ArrayFactory;

$registry = new Registry([new ArrayFactory()], [
    'github' => ['factory' => 'array', 'options' => [
        'profile' => new Profile('array', 'glitchr-studio', 'Glitch Art', sponsors: 3,
            income: new Income(Money::fromDecimal('12.00', 'EUR'), Period::Week),
            goal: new Goal('A day a week', amount: Money::fromDecimal('100', 'EUR'), period: Period::Week)),
        'sponsors' => [new Sponsor('ada', 'Ada'), new Sponsor('acme', 'ACME', kind: Sponsor::ORGANIZATION, tier: 'Sponsor'), new Sponsor('bob')],
        'tiers' => [new Tier('Backer', Money::fromDecimal(5, 'EUR')), new Tier('Once', null, Period::Once, customAmount: true)],
    ]],
]);

$registry->profile('github')->profile()->income->monthly()->decimal();   // "52.00"
$registry->sponsors('github')->sponsors(limit: 2);                       // Ada, ACME
```

(the harness's bare script does the same, run on 2026-11-10.) The application keeps the name it
uses (`github`); only the factory changes. `getName()` is `array`, `getTitle()` `Fixed data`;
`profile()` answers the same profile whatever account is asked for.

## An audience provider on recorded answers

To test the application's handling of a real tool's answers - and of its failures - give the
provider's factory `Omnipulse\Testing\RecordedHttpClient` (or Symfony's `MockHttpClient`):

```php
use Omnipulse\Exception\InvalidKeyException;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\Metric;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Row;
use Omnipulse\Plausible\PlausibleFactory;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReachFactory;
use Omnipulse\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

final class EntryPagesPanelTest extends TestCase
{
    public function testTheBusiestEntryPages(): void
    {
        $registry = new Registry([new FixedReachFactory(audience: [
            new Row(['entry_page' => '/', 'date' => '2026-10-20'], ['visits' => 260]),
            new Row(['entry_page' => '/ateliers', 'date' => '2026-10-21'], ['visits' => 110]),
        ])], ['stats' => ['factory' => 'fixed']]);

        $report = $registry->audience('stats')->audience(
            ReachQuery::lastDays(28, [Dimension::EntryPage], [Metric::Visits], today: new DateTimeImmutable('2026-10-26')),
        );

        self::assertSame(['/', '/ateliers'], array_map(static fn (Row $r) => $r->dimension(Dimension::EntryPage), $report->rows));
        self::assertSame(['visits' => 370], $report->totals());
    }

    public function testWhatPlausibleIsSent(): void
    {
        $http = RecordedHttpClient::routes([
            'POST /api/v2/query' => '{"results":[{"dimensions":["/"],"metrics":[3644,54]}]}',
        ]);
        $registry = new Registry([new PlausibleFactory($http)], [
            'stats' => ['factory' => 'plausible', 'options' => ['api_key' => 'test-key', 'site' => 'example.org']],
        ]);

        $report = $registry->audience('stats')->audience(new ReachQuery(
            new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'),
            [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate],
        ));

        self::assertSame(['visits' => 3644, 'bounce_rate' => 0.54], $report->rows[0]->metrics);
        self::assertSame('test-key', $http->requests[0]['options']['auth_bearer']);
        self::assertSame(['visit:entry_page'], $http->requests[0]['options']['json']['dimensions']);
    }

    public function testARevokedKey(): void
    {
        $http = RecordedHttpClient::routes(['POST /api/v2/query' => [401, '{"error":"Invalid API key"}']]);
        $plausible = (new PlausibleFactory($http))->create(['api_key' => 'revoked', 'site' => 'example.org']);

        $this->expectException(InvalidKeyException::class);
        $plausible->audience(ReachQuery::lastDays(7));
    }
}
```

```
$ vendor/bin/phpunit EntryPagesPanelTest.php
...                                                                 3 / 3 (100%)

OK (3 tests, 6 assertions)
```

(run on 2026-10-26 in the [harness](harness.md), PHPUnit 11.5, PHP 8.4)

`RecordedHttpClient` (shipped with the core; it needs no `symfony/http-client`):

| Member | |
|---|---|
| `new RecordedHttpClient(\Closure $answer)` | answers each request with `$answer($method, $url, $options)`, which returns `[status, body]` or `[status, body, headers]` |
| `static routes(array $routes): self` | answers by route: `"METHOD /path"` => a body (status 200) or `[status, body]`; the first route whose method matches and whose path ends the URL's path answers; anything else gets a 404 `{"error":"not recorded: ..."}` |
| `array $requests` | every request made: `['method' => ..., 'url' => ..., 'options' => ...]`, the options as the provider gave them (`json`, `body`, `query`, `auth_bearer`...) |
| `stream()` | throws `\LogicException`: it does not stream (no provider streams) |

Its responses (`RecordedResponse`) never throw: a provider sees the status and the body, as with a
real client called with `getContent(false)`. A network error cannot be recorded with it; use
`MockHttpClient` with a `MockResponse` whose `error` option is set, which `JsonApi` turns into an
`UnreachableException`.

## A review platform on a MockHttpClient

Every factory takes its HTTP client in its constructor. Given Symfony's `MockHttpClient`, the
provider runs whole - its requests, its mapping, its errors - and nothing leaves the machine:

```php
<?php

use Omnipulse\Exception\UnreachableException;
use Omnipulse\GoogleReviews\GoogleReviewsFactory;
use Omnipulse\Model\Place;
use Omnipulse\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The application's code: a one-line badge, or nothing when the platform is down. */
final class ReviewBadge
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function line(string $provider, string $placeId): ?string
    {
        try {
            $rating = $this->registry->rating($provider)->rating(new Place($placeId));
        } catch (UnreachableException) {
            return null;
        }

        return \sprintf('%.1f/%d (%d) on %s', $rating->value, $rating->scale, $rating->count, $this->registry->terms($provider)->attribution);
    }
}

final class ReviewBadgeTest extends TestCase
{
    private function registry(MockHttpClient $http): Registry
    {
        return new Registry([new GoogleReviewsFactory($http)], ['google' => ['factory' => 'google-reviews', 'options' => ['api_key' => 'test-key']]]);
    }

    public function testTheBadgeIsTheRatingCreditedToThePlatform(): void
    {
        $answer = new JsonMockResponse(['id' => 'ChIJ1', 'rating' => 4.6, 'userRatingCount' => 128, 'googleMapsUri' => 'https://maps.google.com/?cid=1']);
        $http = new MockHttpClient($answer);

        self::assertSame('4.6/5 (128) on Google Maps', (new ReviewBadge($this->registry($http)))->line('google', 'ChIJ1'));
        self::assertSame('https://places.googleapis.com/v1/places/ChIJ1', $answer->getRequestUrl());
        self::assertContains('X-Goog-Api-Key: test-key', $answer->getRequestOptions()['headers']);
    }

    public function testNothingWhenThePlatformIsDown(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        self::assertNull((new ReviewBadge($this->registry($http)))->line('google', 'ChIJ1'));
    }
}
```

```
$ vendor/bin/phpunit --testdox tests/
Review Badge
 ✔ The badge is the rating credited to the platform
 ✔ Nothing when the platform is down

OK (2 tests, 4 assertions)
```

(run on 2026-10-26 in the harness's image, PHPUnit 11.5.57, no network, with the former names;
the names above are the merge's)

The answers each provider expects are in its `Tests/Fixtures` (Google's `place.json`,
Tripadvisor's `details.json`, Trustpilot's `business-unit.json`...), written from each platform's
reference; the harness's `docker/harness/config/recorded.php` is a `MockHttpClient` callback that
serves all of them by URL, for a test that needs every platform at once:

```php
$http = new MockHttpClient(require 'vendor/omnisense/omnipulse/docker/harness/config/recorded.php');
```

A 503, a 429 or a transport error tests the "platform down" path (`UnreachableException`); a 401
or 403 the refused key (`InvalidKeyException`); a 404 with the platform's error body the unknown
place (`ProviderException`).

## A funding platform on recorded answers

To test the application's handling of a platform's answers and failures, give the factory a
`MockHttpClient`:

```php
use Omnipulse\Exception\ProviderException;
use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$oc = (new OpenCollectiveFactory(new MockHttpClient(new MockResponse('{"message":"Rate limit exceeded"}', ['http_code' => 429]))))
    ->create(['account' => 'webpack']);

try {
    $oc->sponsors();
} catch (ProviderException $e) {
    // Open Collective answered 429 for the backers of webpack.
}
```

The providers' own tests keep the shapes of live answers (the people invented) in
`Tests/Fixtures/`: `listing.json`, `sponsors-1.json`, `sponsors-2.json`, `no-listing.json`,
`not-found.json` for GitHub; `account.json`, `members-1.json`, `members-2.json`, `not-found.json`
for Open Collective; `public.json`, `hidden.json` for Liberapay. An application may start from
them.

Both GraphQL providers send every query as a `POST` with `{"query", "variables"}`: a
`MockHttpClient` callback can tell the listing (or account) query from the sponsors (or
members) one by the query text.

## Live checks

Each funding provider, and Matomo's, has a `LiveTest` (PHPUnit group `live`, left out of `test`) that reads a real
account: `docker compose run --rm omnipulse live` in the [harness](harness.md). Matomo's public
demo, Open Collective and Liberapay need no key; GitHub runs when `OMNIPULSE_GITHUB_TOKEN` is set, and is skipped
otherwise.

## The load check

`Omnipulse\Testing\LoadCheck` is the check every package of the family runs in its `BareTest`: each
class of the package loaded in a PHP process of its own, then what came from outside.

| Member | |
|---|---|
| `LoadCheck::FOREIGN` | the classes no package may load: Symfony's but its HTTP client and service contracts and polyfills, Illuminate, Laravel, Doctrine, Twig, League, Guzzle, Nyholm, Http, Omnibridge, Base, App |
| `run(string $root, string $namespace, string $autoload, list<string> $extra = []): array` | runs `bin/load-all` in a child PHP: `['loaded' => classes, 'foreign' => foreign symbols declared, 'files' => files included]`, the files Composer includes at start for every package left out |
| `foreign(list<string> $symbols): list<string>` | the symbols matching `FOREIGN` |
| `outside(list<string> $files, string $root, list<string> $allowed): list<string>` | the files from outside the package and outside the allowed vendor fragments (`/vendor/psr/`...) |
| `autoload(string $class, string $test, string $root): ?string` | the autoloader that knows both the class and the test (the package's or the harness's); `null` when the package sits in an application's `vendor/` |

`bin/load-all` loads every class but those under `Tests/`, `docker/`, `vendor/`, `docs/`, `bin/`
and `Bridge/`: the bridges need what the package only suggests (Symfony's DependencyInjection and
HttpKernel, Twig), and `Tests/ReviewPlatformsTest.php` checks the other way that loading the bundle
does name the framework. An application rarely needs the load check; it is how the family proves
that a provider pulls in no framework.

## No conformance suite

Unlike Omnibus, the family has no shared contract test (`Testing\*ContractTests`): each provider's
tests check its own requests and mapping, its refusals and its factory
([Verified, and not](verified.md)). The core's fakes are not test doubles of a given platform:
copy one as the start of an application's own when the test needs another shape.

## In a framework's functional test

In Symfony, the registry is the bundle's public service `Omnipulse\Registry`: configure the
provider under the test environment with a test factory (`FixedReachFactory`, `FixedReviewsFactory`
or `ArrayFactory` registered as autoconfigured services, then `factory: fixed` in
`config/packages/test/omnipulse.yaml`), or replace the HTTP client the factories receive with
`framework.http_client.mock_response_factory`. In Laravel, bind another `Registry` singleton in the
test's `setUp()`. Neither was run in an application for this page.
