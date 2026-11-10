# omnisense/omnipulse

Audience, reviews and funding: what outside platforms know of a site or a project, under any PHP
host. The search engines say which queries the site shows for and at which position, the
analytics tools who visits and from where; the review platforms what customers say of an
establishment; the funding platforms who funds the project. One registry, contracts by
capability, one set of models per family whoever answers. Plain PHP: no framework in `require`,
the application's HTTP client injected (Symfony's HTTP client contracts).

```php
use Omnipulse\GoogleReviews\GoogleReviewsFactory;
use Omnipulse\Liberapay\LiberapayFactory;
use Omnipulse\Matomo\MatomoFactory;
use Omnipulse\Model\{Dimension, Metric, Place, ReachQuery};
use Omnipulse\Registry;
use Omnipulse\SearchConsole\SearchConsoleFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$registry = new Registry([new SearchConsoleFactory($http), new MatomoFactory($http), new GoogleReviewsFactory($http), new LiberapayFactory($http)], [
    'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '/secrets/service-account.json']],
    'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
    'reviews' => ['factory' => 'google-reviews', 'options' => ['api_key' => '...', 'language' => 'fr']],
    'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
]);

// Audience: the queries of the last 28 days, where visits start.
$queries = $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));
$entries = $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate]));

// Reviews: the rating, three reviews, the platform's terms of display.
$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0');
$rating = $registry->rating('reviews')->rating($place);        // 4.6 from 128, the place on Google Maps
$reviews = $registry->reviews('reviews')->reviews($place, 3);  // with their author, date and link
$terms = $registry->terms('reviews');                          // kept never, credited to "Google Maps"

// Funding: the profile, its income by the month.
$monthly = $registry->profile('liberapay')->profile()->income?->monthly();
```

## Formerly

omnisense/omnireach was renamed **omnisense/omnipulse** on 2026-11-10, and omnisense/omnireview
and omnisense/omnifund merged into it the same day (Marco's decision): one family for what outside
platforms know of a site or a project. Their histories are kept: this repository's history is
omnireach's, and the merge commit's other parents are omnireview's and omnifund's last commits.

`composer.json` replaces `omnisense/omnireach`, `omnisense/omnireview` and `omnisense/omnifund`
(and their former names `omniscient/omnireach`, `omniscient/omnireview`, `glitchr/omnireview`,
`omniscient/omnifund`), so that a project never installs both. The namespaces changed, though:
code that used the former packages moves.

| Before | Now |
|---|---|
| `Omnireach\` | `Omnipulse\` |
| `Omnireview\` (`GatewayInterface`, `GatewayFactory`, `GatewayFactoryInterface`, `Model\Capabilities`) | `Omnipulse\` (`ProviderInterface` and `TermsInterface`, `ProviderFactory`, `ProviderFactoryInterface`, `Capabilities`) |
| `Omnifund\` | `Omnipulse\` |
| `omnireach/<provider>` | `omnipulse/<provider>` |
| `omnireview/google` (`Omnireview\Google\GoogleGateway`, factory `google`) | `omnipulse/google-reviews` (`Omnipulse\GoogleReviews\GoogleReviewsProvider`, factory `google-reviews`) |
| `omnireview/tripadvisor`, `omnireview/trustpilot` (`...Gateway`, `...GatewayFactory`) | `omnipulse/tripadvisor`, `omnipulse/trustpilot` (`...Provider`, `...Factory`) |
| `omnifund/github-sponsors`, `omnifund/opencollective`, `omnifund/liberapay` | `omnipulse/github-sponsors`, `omnipulse/opencollective`, `omnipulse/liberapay` |
| `OmnireviewBundle`: `omnireview: gateways:` | `OmnipulseBundle`: `omnipulse: providers:` |
| Twig `omnireview_rating()`, `_reviews()`, `_terms()`, `_write_url()`, `omnireview_widget()` | `omnipulse_rating()`, `_reviews()`, `_terms()`, `_write_url()`, `omnipulse_reviews_widget()` |

Google's reviews are `omnipulse/google-reviews` rather than `omnipulse/google`: `ga4` and
`search-console` are Google's too. Every provider replaces its former name in the same way. The
[CHANGELOG](CHANGELOG.md) lists what else changed.

## The shape

- **Contracts by capability**, by family:
  - audience: `SearchPerformanceInterface` (search engines), `AudienceInterface` (analytics);
  - reviews: `RatingInterface`, `ReviewsInterface`, `ReplyInterface`, `InviteInterface`,
    `WriteUrlInterface`, all extending `TermsInterface` (`terms()`: the platform's rules of
    display and caching, `Model\Terms`);
  - funding: `ProfileInterface`, `SponsorsInterface`, `TiersInterface`.
- **One `Capabilities`**: the contracts a provider gives as configured (`contracts()`,
  `families()`), the audience's dimensions and metrics and whether they combine (`check()` refuses
  a query before any call), whether a key is needed, the terms (URLs) and limits.
- **One `Registry`**, `Config`, `ProviderFactory`: providers by name, as in the other omni
  families; its typed getters (`search()`, `audience()`, `rating()`, `reviews()`, `replies()`,
  `invitations()`, `writeUrl()`, `profile()`, `sponsors()`, `tiers()`) check the contract and
  the capability, `terms()` gives a review platform's `Terms`.
- **Models** (`Omnipulse\Model`): the audience's `ReachQuery`, `Dimension`, `Metric`, `Row`,
  `Report` (units the same everywhere: ratios from 0 to 1, seconds, dates as Y-m-d); the reviews'
  `Place`, `Rating`, `Review`, `Reply`, `Invitation`, `Terms`; the funding's `Profile`,
  `Sponsor`, `Tier`, `Money`, `Period`, `Income`, `Goal`.
- **HTTP**: `Http\JsonApi` (the audience's), `Http\Answer` (the review platforms'), `Http\Json`
  (the funding platforms'), `Http\Redaction` (no secret in a failure), `Support\Data`.
- **Bridges**: `Bridge\Symfony\OmnipulseBundle` (every provider configured and injectable by
  name) and `Bridge\Twig\OmnipulseExtension` (the reviews with their origin, date and link, kept
  no longer than the platform allows, never as JSON-LD). Their components are not required.
- **Testing**: `Testing\FixedReach`, `Testing\FixedReviews`, `Testing\ArrayProvider`,
  `Testing\RecordedHttpClient`, `Testing\LoadCheck`.

## Providers

| Package | Family | Contracts | Factory | Key |
|---|---|---|---|---|
| [omnipulse/search-console](https://github.com/glitchr-omnisense/omnipulse-search-console) | audience | search performance | `search-console` | service account or token |
| [omnipulse/bing-webmaster](https://github.com/glitchr-omnisense/omnipulse-bing-webmaster) | audience | search performance | `bing-webmaster` | API key |
| [omnipulse/matomo](https://github.com/glitchr-omnisense/omnipulse-matomo) | audience | audience | `matomo` | token (or anonymous) |
| [omnipulse/plausible](https://github.com/glitchr-omnisense/omnipulse-plausible) | audience | audience | `plausible` | API key |
| [omnipulse/ga4](https://github.com/glitchr-omnisense/omnipulse-ga4) | audience | audience | `ga4` | service account or token |
| [omnipulse/google-reviews](https://github.com/glitchr-omnisense/omnipulse-google-reviews) | reviews | rating, reviews, writeUrl; reply with the owner's access | `google-reviews` | Places API key; the owner's OAuth access |
| [omnipulse/tripadvisor](https://github.com/glitchr-omnisense/omnipulse-tripadvisor) | reviews | rating, reviews, writeUrl | `tripadvisor` | Content API key |
| [omnipulse/trustpilot](https://github.com/glitchr-omnisense/omnipulse-trustpilot) | reviews | rating, reviews, writeUrl; reply and invite with a business user | `trustpilot` | API key; secret and business user |
| [omnipulse/github-sponsors](https://github.com/glitchr-omnisense/omnipulse-github-sponsors) | funding | profile, sponsors, tiers | `github-sponsors` | a token |
| [omnipulse/opencollective](https://github.com/glitchr-omnisense/omnipulse-opencollective) | funding | profile, sponsors, tiers | `opencollective` | none (a token raises the limit) |
| [omnipulse/liberapay](https://github.com/glitchr-omnisense/omnipulse-liberapay) | funding | profile | `liberapay` | none |

What it is not: the site's own visit counter (`Base\Service\Analytics` counts visits; omnipulse
does not duplicate it), a tracker, a rank checker scraping search results, a review store (no
JSON-LD `AggregateRating` from a third party's reviews), a payment service.

## Documentation

The manual, in `docs/`:

- [Overview and providers](docs/index.md)
- [Installation: a first script per family](docs/installation.md)
- [Symfony and Twig](docs/symfony.md) and [Laravel](docs/laravel.md)
- [Concepts](docs/concepts.md): contracts, capabilities, the registry, the HTTP helpers, the audience's query and reports
- [Models](docs/models.md): the reviews' and the funding's
- Usage: [search performance](docs/usage-search.md), [audience](docs/usage-audience.md), [reviews](docs/usage-reviews.md), [funding](docs/usage-funding.md)
- [Configuration reference](docs/configuration.md)
- [Errors](docs/errors.md)
- [Testing](docs/testing.md)
- [The harness](docs/harness.md)
- [Writing a provider](docs/providers.md)
- [Verified, and not](docs/verified.md)

## Docker

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnipulse test       # every package's tests, no network
docker compose run --rm omnipulse bare       # plain PHP, the three families: and what PHP loaded
docker compose run --rm omnipulse reviews    # the review platforms installed, on their recorded answers
docker compose run --rm omnipulse phpstan    # PHPStan at the highest level, each package
```

## Verified, and not

- Verified (the harness, PHP 8.4, 2026-11-10): the core and the eleven providers' tests, the bare
  script and the review platforms' script loading no framework, PHPStan at the highest level with
  no error baselined on every package ([docs/verified.md](docs/verified.md)).
- Verified live, earlier: Matomo's public demo (2026-10-24, 2026-10-26); Open Collective without
  a key, Liberapay's public.json, GitHub's GraphQL API with a token (2026-10-24).
- Not verified live: Search Console, Bing Webmaster, Plausible, Google Analytics, and none of the
  review platforms (each needs an account and a key). Their tests answer in the shapes their
  references document; each provider's README says which.

## Responsibility

omnisense/omnipulse and its omnipulse/* providers are unofficial clients, affiliated with none of
the services they reach. The MIT licence covers the code only and grants no right to any service
or its data. Whoever runs them complies with each service's terms (named in each provider's README,
in `Capabilities::$terms` and, for a review platform, in its `Terms`) and obtains what they
require. Reviewers' and sponsors' names and pictures are personal data: show only what the
platform shows publicly, and follow its rules.

## Licence

MIT.
