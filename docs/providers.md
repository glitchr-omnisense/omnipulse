---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package `omnipulse/<name>`, namespace `Omnipulse\<Name>\`, with a factory and a
provider class, serving one family. It requires `omnisense/omnipulse` and
`symfony/http-client-contracts`, nothing else (`symfony/http-client` and PHPUnit in
`require-dev`; a review platform's package requires `symfony/http-client`, for the client it makes
when given none). The rules shared by the three families come first, then each family's.

## The rules

- **The factory** extends `Omnipulse\ProviderFactory`: `populate()` sets its name
  (`omnipulse.factory_name`, kebab-case: `search-console`), its title, its required options and
  the defaults of the others; `build()` returns the provider. The HTTP client comes through the
  factory's constructor; with none, an audience factory's `build()` throws
  `InvalidConfigException`, a review platform's makes one (below).
- **The provider** implements the contracts it can answer and describes itself in
  `capabilities()`, true to the configuration: a contract that needs an access the options do not
  hold is `false`, and its method throws `NotSupportedException::operation()`. The registry's
  typed getters read it ([Concepts](concepts.md#typed-getters)).
- **Secrets** are constructor arguments marked `#[\SensitiveParameter]`, and every call goes
  through the core's HTTP helper of the family, which keeps them out of what it throws
  ([Errors](errors.md#no-secret-in-a-failure)).

## An audience provider

- **The provider** implements `SearchPerformanceInterface` or `AudienceInterface` (or both) and
  describes itself in `capabilities()`: its dimensions and metrics, whether dimensions combine, its
  terms (URLs) and its limits (in words).
- **A report** starts with `$this->capabilities()->check($query, $metrics, '<Title>')`, `$metrics`
  being `$query->metricsOr(<the contract's defaults>)`, so a query it cannot answer costs no call;
  a filter it cannot apply, even on a dimension it reports, is refused with
  `NotSupportedException` too, never dropped. It ends with `$query->apply($rows)`, which puts the
  filters and the limit on what the tool returned.
- **Units** are the family's: ratios from 0 to 1 (a bounce rate in percent is divided by 100),
  seconds, dates `Y-m-d`, devices `desktop`/`mobile`/`tablet`, countries upper case, pages as the
  tool gives them (full URLs for search engines, paths for analytics). A metric the tool does not
  give for a row is left out of it, never zero.
- **Calls** go through `Omnipulse\Http\JsonApi` (every failure becomes the family's exception,
  with the tool's message); answers are read with `Support\Data`, so an unexpected shape gives
  empty values, never a `\TypeError`. Keys go in a header or a body when the tool allows it, not in
  the URL, where logs keep them (Bing's API takes its key only in the query string; `JsonApi`
  keeps the query out of its messages: [Errors](errors.md#the-messages)).
- **Paging**: a tool that pages its answers is called page after page until `limit` rows are read
  or a page comes back short.
- **The note**: `Report::$note` says what the figures are not (provisional, sampled, weekly,
  summed by day), when the provider knows it.

### A skeleton

```php
<?php

// ExampleFactory.php and ExampleProvider.php, here in one listing
namespace Omnipulse\Example;

use Omnipulse\AudienceInterface;
use Omnipulse\Capabilities;
use Omnipulse\Config;
use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Http\JsonApi;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\Metric;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Report;
use Omnipulse\Model\Row;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExampleFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnipulse.factory_name' => 'example',
            'omnipulse.factory_title' => 'Example Analytics',
            'omnipulse.required_options' => ['api_key', 'site'],
            'url' => 'https://api.example-analytics.test',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new ExampleProvider(
            new JsonApi($this->http ?? throw new InvalidConfigException('The "example" provider needs an HTTP client: give one to ExampleFactory.'), 'Example Analytics'),
            (string) $c->string('api_key'),
            (string) $c->string('site'),
            rtrim((string) $c->string('url'), '/'),
        );
    }
}

final class ExampleProvider implements AudienceInterface
{
    public function __construct(private readonly JsonApi $api, #[\SensitiveParameter] private readonly string $apiKey, private readonly string $site, private readonly string $url)
    {
    }

    public function getName(): string
    {
        return 'example';
    }

    public function getTitle(): string
    {
        return 'Example Analytics';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            audience: true,
            dimensions: [Dimension::EntryPage, Dimension::Date],
            metrics: [Metric::Visits, Metric::BounceRate],
            combined: false,
            terms: ['https://example-analytics.test/terms'],
            limits: ['100 requests a minute'],
        );
    }

    public function audience(ReachQuery $query): Report
    {
        $metrics = $query->metricsOr([Metric::Visits]);
        $this->capabilities()->check($query, $metrics, 'Example Analytics');
        $site = $query->site ?? $this->site;
        $answer = $this->api->request('GET', $this->url.'/stats', [
            'query' => ['site' => $site, 'from' => $query->start->format('Y-m-d'), 'to' => $query->end->format('Y-m-d'), 'by' => $query->breakdown()[0]->value ?? 'none'],
            'auth_bearer' => $this->apiKey,
        ], 'the stats of '.$site);

        $rows = [];
        foreach ($answer->list('rows') as $line) {
            $values = [];
            foreach ($metrics as $metric) {
                $value = match ($metric) {
                    Metric::Visits => $line->int('visits'),
                    Metric::BounceRate => null !== ($percent = $line->float('bounce_percent')) ? round($percent / 100, 4) : null,   // percent -> ratio
                    default => null,
                };
                if (null !== $value) {
                    $values[$metric->value] = $value;           // a metric not given is left out, never 0
                }
            }
            $rows[] = new Row($query->has(Dimension::EntryPage) ? ['entry_page' => (string) $line->string('page')] : [], $values);
        }

        return new Report('example', $site, $query, $query->apply($rows));
    }
}
```

Run on recorded answers (2026-10-26, in the [harness](harness.md)):

```php
$http = RecordedHttpClient::routes(['GET /stats' => '{"rows":[{"page":"/","visits":260,"bounce_percent":41.5},{"page":"/ateliers","visits":110}]}']);
$registry = new Registry([new ExampleFactory($http)], ['stats' => ['factory' => 'example', 'options' => ['api_key' => 'k', 'site' => 'example.org']]]);
$report = $registry->audience('stats')->audience(ReachQuery::lastDays(7, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate], new \DateTimeImmutable('2026-10-26')));
echo json_encode(array_map(static fn ($r) => $r->toArray(), $report->rows)), "\n";
echo json_encode($http->requests[0]['options']['query']), "\n";
```

```
[{"dimensions":{"entry_page":"\/"},"metrics":{"visits":260,"bounce_rate":0.415}},{"dimensions":{"entry_page":"\/ateliers"},"metrics":{"visits":110}}]
{"site":"example.org","from":"2026-10-19","to":"2026-10-25","by":"entry_page"}
```

The second row has no `bounce_rate`: the answer gave none.

## A review platform

- **The provider** implements the review contracts it gives - `RatingInterface`,
  `ReviewsInterface`, `ReplyInterface`, `InviteInterface`, `WriteUrlInterface` - which all extend
  `TermsInterface`; `capabilities()` sets `rating`, `reviews`, `reply`, `invite`, `writeUrl`.
- **`terms()`** is read in the platform's documentation, with its address (`source`): how long
  what it gives may be kept (`cacheFor`: `0`, or `null` when the platform's policy is not public),
  the reviews per call (`maxReviews`), the attribution, `authors`, `link`, `noIndex`, the other
  rules in words ([Models](models.md#terms)).
- **Calls** go through `Omnipulse\Http\Answer::send()`: a transport error, a 5xx or a 429 become
  `UnreachableException`; the provider reads the rest - a 401 or 403 is
  `InvalidKeyException::from('<platform>', ...)`, another error `ProviderException::from(...)`,
  the platform's own code as `providerCode`.
- **The factory** takes `?HttpClientInterface $http = null` (named `$http`: the Symfony bundle
  gives the application's `http_client` to an argument of that name) and makes one with
  `HttpClient::create()` when it is `null`.
- **The models** keep what showing a review requires: its author as the platform names them, its
  date, its link (`Review::$url`, the establishment's page when the platform gives none); a
  `Rating` its `url` and, when the platform draws its score, its `image`.

```php
final class GuestbookProvider implements RatingInterface, ReviewsInterface, WriteUrlInterface
{
    public function capabilities(): Capabilities
    {
        return new Capabilities(rating: true, reviews: true, writeUrl: true);
    }

    public function terms(): Terms
    {
        return new Terms(cacheFor: 3600, maxReviews: 20, attribution: 'Guestbook', source: 'https://guestbook.example/terms');
    }

    /** @return array<mixed> */
    private function call(string $path, array $query = []): array
    {
        $answer = Answer::send($this->http, 'guestbook', 'GET', $this->baseUrl.$path, ['query' => $query, 'headers' => ['Authorization' => 'Bearer '.$this->token]]);
        if (\in_array($answer->status, [401, 403], true)) {
            throw InvalidKeyException::from('guestbook', \sprintf('The token was refused (HTTP %d).', $answer->status), (string) $answer->status);
        }
        if ($answer->status >= 400) {
            throw ProviderException::from('guestbook', \sprintf('HTTP %d.', $answer->status), (string) $answer->status);
        }

        return $answer->json();
    }

    // getName(), getTitle(), rating(), reviews(), writeUrl() as the contracts say
}
```

(a sketch, not run; omnipulse/tripadvisor is the shortest provider to read whole.) The existing
providers: `GoogleReviewsProvider` (two APIs behind one provider, an OAuth refresh token kept until
a minute before it lapses, `Place::$owner` selecting the owner's side), `TripadvisorProvider` (a
key in the query, one details call kept for the rating and the write link), `TrustpilotProvider`
(three hosts, a client-credentials token, a business user's header, a `find()` of its own).

## A funding platform

- **The provider** implements the funding contracts it can answer - `ProfileInterface`,
  `SponsorsInterface`, `TiersInterface` - and says so in `capabilities()`, with whether a key is
  needed (`key`) and the terms that apply. A platform that keeps its sponsors private (Liberapay)
  implements the profile only; the registry then refuses `sponsors()` with a
  `NotSupportedException`. Each method reads the configured account, or the `$account` given.
- **Calls** go through `Omnipulse\Http\Json::request()`, which turns a network failure into an
  `UnreachableException` and an error status into a `ProviderException`; a GraphQL `errors`
  member is a `ProviderException` too (`ProviderException::answered('<Title>', '<what>',
  '<the error>')`). `Json`'s typed readers walk an answer without trusting its shape.
- **Amounts** are `Model\Money` in hundredths of the currency, with the `Model\Period` the platform
  counts in (Liberapay: the week; GitHub: the month; Open Collective's budget: the year).
- **The factory** takes a `HttpClientInterface $http`, not nullable: in Symfony the bundle then
  needs the application's `http_client` ([Symfony](symfony.md#the-http-client)).
- **Sponsors** are listed page after page when no limit is given, and no more pages than needed
  with one.

## Its tests

- **The provider's own test** (`Tests/<Name>ProviderTest.php`), on recorded answers - from the
  platform when a public account or demo exists (Matomo's, Open Collective's, Liberapay's are),
  else composed in the shape the platform's reference documents, and said so in the test's
  docblock: the request sent (URL, body, key), the mapping into the family's models and units,
  the refusals (`NotSupportedException` for each dimension, filter, combination or access it
  cannot answer), a refused key (`InvalidKeyException`), an unknown place or account, the factory
  through a `Registry`. Fixtures go in `Tests/Fixtures/`.
- **`Tests/BareTest.php`**: every class of the package loaded alone, nothing foreign:

```php
use Omnipulse\Testing\LoadCheck;

$autoload = LoadCheck::autoload(ExampleProvider::class, self::class, \dirname(__DIR__)) ?? self::markTestSkipped('installed in an application here');
$report = LoadCheck::run(\dirname(__DIR__), 'Omnipulse\\Example\\', $autoload);

self::assertContains(ExampleProvider::class, $report['loaded']);
self::assertSame([], $report['foreign']);
self::assertSame([], LoadCheck::outside($report['files'], \dirname(__DIR__), ['/vendor/omnisense/omnipulse/', '/omnipulse/core/', '/vendor/symfony/http-client-contracts/', '/vendor/symfony/service-contracts/']));
```

- **`Tests/LiveTest.php`** (`#[Group('live')]`), only for what needs no account (or a token from
  the environment, skipped without it), never in the default run: `docker compose run --rm
  omnipulse live`.

## Registering it

- Plain PHP: give the registry an instance of its factory.
- Symfony: a service implementing `ProviderFactoryInterface` is autoconfigured with the tag
  `omnipulse.provider_factory`; with the application's autowiring, the class in `src/` is enough.
  The bundle registers on its own only the eleven factories it knows
  (`OmnipulseBundle::factories()`).
- The harness: a line in `docker/harness/plugins.php` (its slug, its tests' namespace, its
  factory); a review platform also a block in `docker/harness/config/review-platforms.php` (its
  environment variables, example options and place) and its fixtures in
  `docker/harness/config/recorded.php`.

Its README states what was verified and what was not, and the platform's terms
("Responsibility").
