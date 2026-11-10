---
title: Concepts
order: 20
---

# Concepts

## Providers and contracts

`Omnipulse\ProviderInterface` is one outside platform, configured. What it can be asked comes
from the contracts it implements, one per capability, grouped in three families:

| Family | Interface | Method | Implemented by |
|---|---|---|---|
| audience | `SearchPerformanceInterface` | `searchPerformance(ReachQuery $query): Report` (default metrics: clicks, impressions, ctr, position) | search-console, bing-webmaster |
| audience | `AudienceInterface` | `audience(ReachQuery $query): Report` (default metrics: visitors, visits, pageviews) | matomo, plausible, ga4 |
| reviews | `RatingInterface` | `rating(Place $place): Rating` | google-reviews, tripadvisor, trustpilot |
| reviews | `ReviewsInterface` | `reviews(Place $place, int $limit = 5, ?string $language = null): list<Review>` | google-reviews, tripadvisor, trustpilot |
| reviews | `ReplyInterface` | `reply(Place $place, Review $review, string $text): Reply` | google-reviews, trustpilot (with the owner's or business user's access) |
| reviews | `InviteInterface` | `invite(Place $place, Invitation $invitation): ?string` | trustpilot (with a business user) |
| reviews | `WriteUrlInterface` | `writeUrl(Place $place): string` | google-reviews, tripadvisor, trustpilot |
| funding | `ProfileInterface` | `profile(?string $account = null): Profile` | github-sponsors, opencollective, liberapay |
| funding | `SponsorsInterface` | `sponsors(?string $account = null, ?int $limit = null): list<Sponsor>` | github-sponsors, opencollective |
| funding | `TiersInterface` | `tiers(?string $account = null): list<Tier>` | github-sponsors, opencollective |

Every contract extends `ProviderInterface`:

| Method | Returns |
|---|---|
| `getName(): string` | the factory's name: `search-console`, `matomo`, `google-reviews`, `liberapay`... - also the `provider` of every report a provider of the audience returns |
| `getTitle(): string` | the platform's display name: `Google Search Console`, `Matomo`, `Google`, `Liberapay` |
| `capabilities(): Capabilities` | what it gives as configured, its terms and limits ([below](#capabilities)); calls nothing |

The review contracts extend `Omnipulse\TermsInterface` in between, which adds
`terms(): Terms`: how what the platform gives may be shown and kept, as the platform's own rules
say - how long it may be kept, how many reviews a call gives, what must be shown with it, whether a
page showing it stays out of search engines ([Models](models.md#terms)). A review provider reads
and writes on its platform and keeps nothing: what to show, and for how long, is the
application's, within the `Terms`.

The funding contracts read the configured account, or another one given as `$account`. The
audience's report methods throw `NotSupportedException` for a dimension, a filter or a metric the
provider does not report, before any call. Every other failure is one of the exceptions of
[Errors](errors.md).

A provider may implement a contract and not give it as configured: Google's and Trustpilot's
review providers implement `ReplyInterface` whether or not the owner's access is set; their
`capabilities()` then say `reply: false`, and `reply()` throws `NotSupportedException`. The
registry's typed getters check both ([below](#registry)).

## Capabilities

`Omnipulse\Capabilities` (a readonly value) says what a provider is before it is asked anything.
One class for the three families: each provider sets the fields of its own (named arguments) and
leaves the others at their defaults.

| Property | Type | Default | Family | |
|---|---|---|---|---|
| `searchPerformance` | `bool` | `false` | audience | it gives `SearchPerformanceInterface` |
| `audience` | `bool` | `false` | audience | it gives `AudienceInterface` |
| `dimensions` | `list<Dimension>` | `[]` | audience | the dimensions it breaks a report down by (and filters on) |
| `metrics` | `list<Metric>` | `[]` | audience | the metrics it counts |
| `combined` | `bool` | `true` | audience | dimensions combine freely; `false`: one dimension at a time besides the date |
| `rating` | `bool` | `false` | reviews | it gives `RatingInterface` |
| `reviews` | `bool` | `false` | reviews | it gives `ReviewsInterface` |
| `reply` | `bool` | `false` | reviews | it replies as the establishment, as configured (`ReplyInterface`) |
| `invite` | `bool` | `false` | reviews | it invites a customer to review, as configured (`InviteInterface`) |
| `writeUrl` | `bool` | `false` | reviews | it gives the address to leave a review (`WriteUrlInterface`) |
| `profile` | `bool` | `false` | funding | it gives `ProfileInterface` |
| `sponsors` | `bool` | `false` | funding | it gives `SponsorsInterface` |
| `tiers` | `bool` | `false` | funding | it gives `TiersInterface` |
| `key` | `?bool` | `null` | all | whether a key or token is needed to read it; `null`: not said (the audience's and the review platforms' providers do not say; the funding platforms' do) |
| `terms` | `list<string>` | `[]` | all | URLs of the terms whoever runs it must follow |
| `limits` | `list<string>` | `[]` | all | its rate limits, quotas and data freshness, in words |

`terms` are the addresses of the terms that apply. A review platform's rules of display and
caching are something else, a model of their own: its `Terms`, from `TermsInterface::terms()`.

| Method | |
|---|---|
| `contracts(): list<string>` | what it can be asked, by contract, in the order of the table: `searchPerformance`, `audience`, `rating`, `reviews`, `reply`, `invite`, `writeUrl`, `profile`, `sponsors`, `tiers` - those it gives |
| `families(): list<string>` | the families it serves: `audience`, `reviews`, `funding` |
| `gives(string $contract): bool` | whether it gives that contract (`"audience"`, `"writeUrl"`...); `false` for a name that is no contract |
| `check(ReachQuery $query, list<Metric> $metrics, string $provider): void` | the audience's: throws `NotSupportedException` when the query asks for a dimension or a filter outside `dimensions`, a metric outside `metrics`, or - with `combined` false - more than one dimension besides the date |
| `toArray(): array` | `contracts`, `families` and every property as plain values (enums as their strings), for a page that lists the providers |

`Capabilities::FAMILIES` maps each family to its contracts. What the core's fixed review provider
answers, as `toArray()`:

```
{"contracts":["rating","reviews","writeUrl"],"families":["reviews"],"searchPerformance":false,"audience":false,"dimensions":[],"metrics":[],"combined":true,"rating":true,"reviews":true,"reply":false,"invite":false,"writeUrl":true,"profile":false,"sponsors":false,"tiers":false,"key":false,"terms":[],"limits":[]}
```

(run on 2026-11-10 in the [harness](harness.md))

Every audience provider starts its report with `check()`, so a refusal costs no call. The messages
name what is missing: `Matomo does not report the dimension "query".`, `Bing Webmaster reports
one dimension at a time besides the date, not query and page.`

What each funding provider declares:

| | `profile` | `sponsors` | `tiers` | `key` | `terms` |
|---|---|---|---|---|---|
| GitHub Sponsors | yes | yes | yes | yes | GitHub's Terms of Service, the GitHub Sponsors additional terms, the GraphQL rate limits |
| Open Collective | yes | yes | yes | no | opencollective.com/tos, the API v2 documentation |
| Liberapay | yes | no | no | no | liberapay.com/about/legal, liberapay.com/about/privacy |
| `array` (testing) | yes | yes | yes | no | - |

and each review provider: Google and Trustpilot `rating`, `reviews`, `writeUrl`, with `reply`
(and Trustpilot's `invite`) only when the owner's or business user's access is configured;
Tripadvisor `rating`, `reviews`, `writeUrl`.

## Factory and Config

`Omnipulse\ProviderFactoryInterface` builds a provider from options:

| Method | |
|---|---|
| `getName(): string` | the name providers are configured with: `search-console`, `google-reviews`, `liberapay`... |
| `create(array $options = []): ProviderInterface` | a provider, its options checked |

Every provider's factory extends `Omnipulse\ProviderFactory`, which does the work:

1. `createConfig($options)` - a `Config` (an `ArrayObject`) holding the options given, then the
   factory's `populate()` fills each key the options did not set with its default;
2. the keys listed under `omnipulse.required_options` are checked: one missing, `''` or `[]` is an
   `InvalidConfigException` listing every missing key (`The "bing-webmaster" provider needs:
   api_key, site.`);
3. the factory's `build()` returns the provider.

`getName()` is read from the `Config` (`omnipulse.factory_name`, the class name when a factory
sets none), so it costs nothing. The reserved keys a factory sets:

| Key | |
|---|---|
| `omnipulse.factory_name` | the provider's name |
| `omnipulse.factory_title` | the provider's title |
| `omnipulse.required_options` | the option names that must be filled |

`Config` reads options with types:

| Method | |
|---|---|
| `defaults(array $defaults): self` | sets each key not set yet |
| `validateNotEmpty(list<string> $keys): void` | step 2 above |
| `get(string $key, mixed $default = null): mixed` | the value as it is |
| `string(string $key): ?string` | a scalar as a string; `null` when it is empty or not a scalar |
| `int(string $key, int $default = 0): int`, `float(string $key, float $default = 0.0): float` | a numeric value, else the default |
| `bool(string $key): bool` | `true`, or `"1"`, `"true"`, `"yes"`, `"on"` (an environment variable's spelling, any case) |
| `list(string $key): list<string>` | a list, or a comma-separated string (one environment variable) split and trimmed |

Factories take their HTTP client in their constructor and call nothing when they build: a provider
reached over HTTP makes its first call when it is first asked something (Google's providers ask
for their token then). The audience's factories refuse to build without a client, the review
platforms' make their own (`HttpClient::create()`), the funding platforms' take one, not nullable
([Installation](installation.md#requirements)).

## Registry

`Omnipulse\Registry` holds the application's providers by name, whichever family they serve:

```php
$registry = new Registry(
    [new SearchConsoleFactory($http), new MatomoFactory($http), new GoogleReviewsFactory($http), new LiberapayFactory($http)],   // keyed by getName()
    [
        'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '/secrets/sa.json']],
        'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
        'reviews' => ['factory' => 'google-reviews', 'options' => ['api_key' => '...']],
        'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
    ],
);
```

| Method | |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built on first use from its factory and options, then kept |
| `search(string $name): SearchPerformanceInterface` | the provider as that contract ([below](#typed-getters)) |
| `audience(string $name): AudienceInterface` | likewise |
| `rating()`, `reviews()`, `replies()`, `invitations()`, `writeUrl()` | likewise, as `RatingInterface`, `ReviewsInterface`, `ReplyInterface`, `InviteInterface`, `WriteUrlInterface` |
| `profile()`, `sponsors()`, `tiers()` | likewise, as `ProfileInterface`, `SponsorsInterface`, `TiersInterface` |
| `terms(string $name): Terms` | a review platform's `Terms`; `NotSupportedException` for a provider that is no review platform |
| `has(string $name): bool` | whether a provider of that name is configured |
| `options(string $name): array` | the options it is configured with (`[]` when none) |
| `create(string $name, array $overrides = []): ProviderInterface` | a provider built afresh, its configured options with `$overrides` over them (`array_replace`) - a key typed in a back office - not kept: `get()` still gives the configured one |
| `names(): list<string>` | the configured providers' names |
| `all(): array<string, ProviderInterface>` | every configured provider, built |
| `factories(): list<string>` | the factories installed: what `factory:` may name |

A name not configured, or configured with a factory not installed, is an `InvalidConfigException`
(`No "y" provider; configured: x.`, `No "matomo" factory for the "x" provider; installed:
none.`). The provider's name is the application's (`google`, `stats`); the factory's name is the
package's. Two providers may use the same factory with different options: two Matomo sites, the
Search Console properties of two domains, two Liberapay accounts. A later factory of the same
name replaces an earlier one.

### Typed getters

Every typed getter gives the provider when it implements the contract **and** its
`capabilities()` say it gives it as configured; otherwise a `NotSupportedException` naming what
the provider gives, before any call:

```
The "stats" provider (Matomo) does not report search performance; it gives: audience.
The "trustpilot" provider (Trustpilot) does not give replying to a review; it gives: rating, reviews, writeUrl.
The "liberapay" provider (Liberapay) does not list sponsors; it gives: profile.
The "liberapay" provider (Liberapay) does not give terms of display; it gives: profile.
```

(run on 2026-11-10 in the [harness](harness.md), Trustpilot configured without a business user)

So test `capabilities()` before offering an action, rather than catch the getter's exception:

```php
if ($registry->get('trustpilot')->capabilities()->reply) {
    // show the "Reply" button
}
```

## The audience's query

`Omnipulse\Model\ReachQuery` (readonly) is what an audience report is asked for. The reviews' and
the funding's models are in [Models](models.md).

```php
new ReachQuery(
    \DateTimeImmutable $start,
    \DateTimeImmutable $end,
    array $dimensions = [],    // list<Dimension>
    array $metrics = [],       // list<Metric>; none: the provider's defaults
    array $filters = [],       // array<string, string>, by Dimension value
    int $limit = 1000,
    ?string $site = null,      // another site than the configured one
);
```

| Property | Type | |
|---|---|---|
| `start`, `end` | `\DateTimeImmutable` | the period's first and last days, both included, set to midnight (in the timezone of the dates given) |
| `dimensions` | `list<Dimension>` | what the rows are broken down by, in that order; none: one row for the whole period |
| `metrics` | `list<Metric>` | what the rows count; none: the report's defaults |
| `filters` | `array<string, string>` | exact filters, keyed by a dimension's value: `['country' => 'FR', 'source' => 'google']`; all must hold |
| `limit` | `int` | at most this many rows, 1000 by default |
| `site` | `?string` | the site, property or domain to report on, when not the provider's `site` option |

The constructor throws `Omnipulse\Exception\InvalidArgumentException` when the period ends before
it starts, when `limit` is below 1, or when a filter names no dimension (`No "city" dimension to
filter on.`).

| Method | |
|---|---|
| `static lastDays(int $days, array $dimensions = [], array $metrics = [], ?\DateTimeImmutable $today = null): self` | the last `$days` whole days (at least 1), yesterday the last: today's figures are complete nowhere. `$today` for a test |
| `has(Dimension $d): bool` | whether the query breaks down by it |
| `filter(Dimension $d): ?string` | the filter's value on it, if any |
| `metricsOr(list<Metric> $defaults): list<Metric>` | the query's metrics, or the defaults when it names none |
| `breakdown(): list<Dimension>` | the dimensions but the date: what `combined: false` counts |
| `apply(iterable<Row> $rows): list<Row>` | the filters and the limit put on rows ([below](#filters-and-limit)) |

### Filters and limit

Every provider passes the rows it read through `$query->apply()` before returning them, so a query
means the same everywhere, whether or not the tool filtered upstream. `apply()` keeps a row unless
it has a filtered dimension with another value - a row that does not carry the filtered dimension
(a report not broken down by it, where the tool applied the filter itself) is kept - and stops at
`limit` rows. The comparison is exact and case-sensitive, on the values in the family's units
(below): filter `country` with `FR`, `device` with `mobile`.

## Dimensions

`Omnipulse\Model\Dimension`, a string-backed enum; the value is the key of `Row::$dimensions` and of
`ReachQuery::$filters`.

| Case | Value | Meaning | Reported by |
|---|---|---|---|
| `Query` | `query` | the search query typed | search engines |
| `Page` | `page` | a page: shown in search results (search engines, a full URL), or viewed (analytics, a path) | all |
| `EntryPage` | `entry_page` | the page a visit started on, the landing page | analytics |
| `Country` | `country` | the visitor's country: ISO 3166-1 alpha-2, upper case (`FR`); alpha-3 for Search Console (`FRA`) | search-console, matomo, plausible, ga4 |
| `Device` | `device` | `desktop`, `mobile`, `tablet` (lower case; Matomo also gives its other types) | search-console, matomo, plausible, ga4 |
| `Source` | `source` | where a visit came from: a search engine, a site, `direct`, as the tool names it | analytics |
| `Date` | `date` | the day, `Y-m-d`: a period's history | all (Bing: its own weekly periods) |

## Metrics

`Omnipulse\Model\Metric`, a string-backed enum; the value is the key of `Row::$metrics`.

| Case | Value | Unit | Search | Adds up |
|---|---|---|---|---|
| `Clicks` | `clicks` | clicks from search results to the site | yes | yes |
| `Impressions` | `impressions` | times the site was shown in search results | yes | yes |
| `Ctr` | `ctr` | clicks per impression, a ratio from 0 to 1 | yes | no |
| `Position` | `position` | the average position in search results, 1 the first | yes | no |
| `Visitors` | `visitors` | distinct visitors (users) | no | no |
| `Visits` | `visits` | visits (sessions) | no | yes |
| `Pageviews` | `pageviews` | pages viewed | no | yes |
| `BounceRate` | `bounce_rate` | visits of one page only, a ratio from 0 to 1 | no | no |
| `VisitDuration` | `visit_duration` | the average length of a visit, in seconds | no | no |

`isSearch()` is the "Search" column, `isAdditive()` the "Adds up" one: a sum of averages, ratios
or distinct counts over rows means nothing, so `Report::totals()` leaves them out. Values are `int`
for counts and `float` for ratios, positions and durations (Plausible's visit duration as
Plausible gives it). Ratios the providers compute or convert are rounded to 4 decimals, positions
and durations to 1.

A metric the tool does not give for a row is left out of that row, never set to zero:
`$row->metric(Metric::Position)` is `null` then.

## Rows and reports

`Omnipulse\Model\Row` (readonly) is one line of a report:

| Member | |
|---|---|
| `array<string, string> $dimensions` | by Dimension value: `['query' => 'atelier poterie', 'country' => 'FRA']` |
| `array<string, int\|float> $metrics` | by Metric value: `['clicks' => 42, 'position' => 3.6]` |
| `dimension(Dimension $d): ?string` | one dimension's value |
| `metric(Metric $m): int\|float\|null` | one metric's value |
| `toArray(): array` | `['dimensions' => ..., 'metrics' => ...]` |

`Omnipulse\Model\Report` (readonly) is a provider's answer:

| Member | |
|---|---|
| `string $provider` | the provider's name: `search-console`, `matomo`... |
| `string $site` | the site, property or domain reported on (the query's, else the configured one) |
| `ReachQuery $query` | the query answered |
| `list<Row> $rows` | the rows, filtered and cut to the limit |
| `?string $note` | what the figures are not, when the provider knows: provisional days, Bing's weekly periods, Matomo's distinct visitors summed by day |
| `totals(): array<string, int\|float>` | the sums of the metrics that add up, over the rows |
| `toArray(): array` | provider, site, start and end (`Y-m-d`), the dimensions' values, the rows, the note: a plain array to cache or to send as JSON |

The order of the rows is each provider's ([Search performance](usage-search.md),
[Audience](usage-audience.md)): sort them in the application when it matters.

## The HTTP helpers

The core gives the providers three helpers, one per family - each was its family's before the
merge, and they are kept apart for now: their providers read their answers differently (the
audience's by dotted paths into a `Data`, the review platforms' as a whole answer with its
status and headers, the funding platforms' with static readers over arrays), and folding them
into one is left to the move onto `omnisense/contracts`. All three keep the request's secrets out
of what they throw ([Errors](errors.md#no-secret-in-a-failure)).

### JsonApi (audience)

`new Omnipulse\Http\JsonApi(HttpClientInterface $http, string $provider)`, then
`request(string $method, string $url, array $options = [], string $what = 'the request'): Data`.
The options are the HTTP client's (`query`, `json`, `body`, `headers`, `auth_bearer`...). A
transport error becomes `UnreachableException`; a 401 or 403 `InvalidKeyException`; a 404
`NotFoundException`; any other status of 400 or more, or a 2xx body that is not JSON,
`ProviderException` - each with the tool's own message when the answer carries one, looked up
under `error_description`, `error.message`, `message`, `Message`, `detail`, `errors.0.message`,
`errors.0.detail`, `fault.faultstring`, then `error` (`JsonApi::message()`).

The answer is an `Omnipulse\Support\Data`: the decoded JSON read with types at dotted paths
(`string('rows.0.keys.1')`, `int()`, `float()`, `bool()`, `date()`, `list()`, `data()`,
`items()`, `raw()`, `has()`), each giving `null` (or an empty list) when the answer holds something
else - so an answer of an unexpected shape gives empty rows rather than a `\TypeError`.

### Answer (reviews)

```php
Answer::send(HttpClientInterface $http, string $provider, string $method, string $url, array $options = []): Answer
```

It sends the request (a `timeout` of 10 seconds unless one is given) and reads the whole answer.
No connection, a timeout, any transport error, an HTTP status of 500 or more, or 429 is an
`UnreachableException` (its `providerCode` the status for an HTTP one): the platform said nothing
about the request. Anything else comes back as an `Answer`:

| Member | |
|---|---|
| `status` | int: the HTTP status |
| `headers` | `array<string, list<string>>`, names in lower case |
| `body` | string: the whole body |
| `header(string $name): ?string` | the first value of a header, by any case |
| `json(): array` | the body decoded; `ProviderException` (`[<provider>] HTTP <status>, not a JSON object: ...`) otherwise |

What a 4xx means is the provider's to say (a refused key, an unknown place).

### Json (funding)

`Omnipulse\Http\Json`, static:

- `request(HttpClientInterface $http, string $method, string $url, array $options, string
  $provider, string $what): array` - the call, the HTTP client's failures as an
  `UnreachableException`, a status of 300 or more as a `ProviderException`
  (`GitHub answered 401 for the listing of x.`), a body that is not a JSON object or list as a
  `ProviderException` (`... answered something that is not JSON for ...`);
- typed reads of an answer, each `null` (or `[]`, `false`) when a step of the path is missing:
  `get($data, ...$path)`, `string()`, `int()`, `bool()` (only `true` is true), `list()`,
  `date()`.

```php
$answer = json_decode('{"data":{"account":{"stats":{"contributorsCount":"42"},"since":"2016-10-15T15:47:40.951Z"}}}', true);
Json::int($answer, 'data', 'account', 'stats', 'contributorsCount');   // 42
Json::date($answer, 'data', 'account', 'since')?->format('Y-m-d');      // "2016-10-15"
Json::string($answer, 'data', 'nothing');                               // null
```

(the harness's bare script does the same, run on 2026-11-10)
