# omnisense/omnipulse

Omnipulse is one PHP contract for what outside platforms know of a site or a project, in three
families:

- **audience**: the search engines say which queries the site shows for, on which pages, at which
  position and with how many clicks; the analytics tools say who visits, from where, landing on
  which page. Every tool is asked the same question - a `ReachQuery`: a period of whole days, the
  dimensions to break it down by, the metrics, exact filters, a limit - and gives the same answer
  back: a `Report` of `Row`s, in one set of units whoever answered;
- **reviews**: what customers say of an establishment on platforms it does not run - its rating,
  its reviews, replying as the establishment, inviting a customer to leave one, the link to write
  one - on Google, Tripadvisor and Trustpilot, each platform's terms of display and caching
  declared;
- **funding**: who funds a project, wherever the funding is received - the account's profile (how
  many fund it, what it receives, its goal), its public sponsors and the tiers it offers - on
  GitHub Sponsors, Open Collective and Liberapay.

```php
$registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));   // queries, clicks, position
$registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]));       // where visits start

$place = new Place('ChIJLU7jZClu5kcR4PcOOO6p3I0');                   // the platform's identifier, kept by the application
$registry->rating('reviews')->rating($place);                         // 4.6 from 128, the place on Google Maps
$registry->reviews('reviews')->reviews($place, 3);                    // shown with their author, date and link
$registry->terms('reviews');                                          // how long they may be kept, how they must be credited

$registry->profile('liberapay')->profile()->income;                   // 636.28 EUR a week
$registry->sponsors('github')->sponsors();                            // the public sponsors
```

The design is the one of the other Omnisense families: a provider's **factory** fills a
configuration with its name, its required options and the defaults of the others, then builds the
**provider**; one **registry** holds the application's providers by name, whichever family they
serve. A provider says what it can be asked in its **capabilities** - its contracts, and for the
audience its dimensions and metrics - and a question it cannot answer is refused before any call.
The core needs no framework: it requires `symfony/http-client-contracts` and `ext-mbstring`, and
every provider takes the application's HTTP client in its factory's constructor. A Symfony bundle
and Twig functions for the reviews ship in `Bridge/`, their components left to the application.

Published as `omnisense/omnipulse`. omnisense/omnireach was renamed omnipulse on 2026-11-10, and
omnisense/omnireview and omnisense/omnifund merged into it the same day; the package `replace`s
those names (and their former `omniscient/*` and `glitchr/omnireview` ones), but the namespaces
changed: `Omnireach\`, `Omnireview\` and `Omnifund\` are all `Omnipulse\`
([the README](../README.md#formerly) has the table). Providers are `omnipulse/<provider>`,
namespace `Omnipulse\<Provider>\`.

## When to use it

- A site taken over from someone else: its search history and its audience over any period the
  tools still keep, before and after a redesign, page by page.
- A back office that shows the same "queries, pages, visits" panels whichever tool the site uses,
  or several at once.
- A site that shows its Google, Tripadvisor or Trustpilot rating and a few reviews, credited to the
  platform as the platform requires, with the links to read them all and to write one; a back
  office that answers reviews as the establishment or invites customers after a purchase.
- A site or a README badge that shows its sponsors, its backers or its funding goal, read from the
  platform that receives the money; a project funded on more than one platform that wants one
  list.

It is not a visit counter of its own, not a tracker (no script is added to a page), not a rank
checker scraping search results, not a review store (nothing is collected, kept or rewritten, and
no JSON-LD `AggregateRating` is built from a third party's reviews), and it takes no payment: every
figure comes from a platform's API, with the owner's own access where one is needed. It stores
nothing either: caching is the application's job, within each platform's terms and quotas (the
Twig review functions do it for the reviews, within the platform's `Terms`).

## Providers

Eleven packages. "Contracts" are what each provider implements and its `capabilities()` give as
configured; for the audience, "one at a time" means its `combined` is `false`: one dimension
besides the date.

| Package | Family | Factory name | Contracts | Credentials |
|---|---|---|---|---|
| [`omnipulse/search-console`](https://github.com/glitchr-omnisense/omnipulse-search-console) | audience | `search-console` | search performance: query, page, country, device, date (combined); clicks, impressions, ctr, position | a service account's key, or an access token |
| [`omnipulse/bing-webmaster`](https://github.com/glitchr-omnisense/omnipulse-bing-webmaster) | audience | `bing-webmaster` | search performance: query or page, date (one at a time); clicks, impressions, ctr, position | an API key |
| [`omnipulse/matomo`](https://github.com/glitchr-omnisense/omnipulse-matomo) | audience | `matomo` | audience: entry page, page, source, country or device, date (one at a time); visitors, visits, pageviews, bounce rate, visit duration | a `token_auth`, or none for public reports |
| [`omnipulse/plausible`](https://github.com/glitchr-omnisense/omnipulse-plausible) | audience | `plausible` | audience: page, entry page, source, country, device, date (combined); the same metrics | a Stats API key |
| [`omnipulse/ga4`](https://github.com/glitchr-omnisense/omnipulse-ga4) | audience | `ga4` | audience: page, entry page, source, country, device, date (combined); the same metrics | a service account's key, or an access token |
| [`omnipulse/google-reviews`](https://github.com/glitchr-omnisense/omnipulse-google-reviews) | reviews | `google-reviews` | rating, reviews, writeUrl (Places API); reply and up to 50 reviews with the owner's access (Business Profile API) | a Places API key; the owner's OAuth client and refresh token |
| [`omnipulse/tripadvisor`](https://github.com/glitchr-omnisense/omnipulse-tripadvisor) | reviews | `tripadvisor` | rating, reviews, writeUrl (Content API) | a Content API key |
| [`omnipulse/trustpilot`](https://github.com/glitchr-omnisense/omnipulse-trustpilot) | reviews | `trustpilot` | rating, reviews, writeUrl; reply and invite with a business user | an API key; its secret and a business user |
| [`omnipulse/github-sponsors`](https://github.com/glitchr-omnisense/omnipulse-github-sponsors) | funding | `github-sponsors` | profile (no income), sponsors (the public ones), tiers | a GitHub token |
| [`omnipulse/opencollective`](https://github.com/glitchr-omnisense/omnipulse-opencollective) | funding | `opencollective` | profile (no goal; income by the year), sponsors (the backers), tiers | none (a token raises the limit) |
| [`omnipulse/liberapay`](https://github.com/glitchr-omnisense/omnipulse-liberapay) | funding | `liberapay` | profile (income by the week) | none |

What each review platform declares in its `Terms`:

| Platform | Kept (`cacheFor`) | Reviews per call (`maxReviews`) | Credited to | Pages not indexed |
|---|---|---|---|---|
| Google | never (`0`): the place ID only | 5 declared; 50 from the Business Profile | Google Maps | no |
| Tripadvisor | as its Caching Policy allows (`null`: not public, so not kept) | 5 | Tripadvisor | yes |
| Trustpilot | 24 hours (`86400`) | 100 | Trustpilot | no |

No provider serves two families. The core's test providers do what a provider would, with no
network: `Testing\FixedReach` (`fixed`: both audience contracts), `Testing\FixedReviews`
(`fixed-reviews`: rating, reviews, writeUrl) and `Testing\ArrayProvider` (`array`: profile,
sponsors, tiers) ([Testing](testing.md)). Each provider's own manual lists its options, the calls it
makes and how it maps the platform's answer.

## How this manual is organised

- [Installation](installation.md): requirements, a first script per family in plain PHP, an
  audience provider's API called live.
- [Symfony and Twig](symfony.md) (the bundle, the review functions and widget) and
  [Laravel](laravel.md) (a service provider written by hand).
- [Concepts](concepts.md): providers and their contracts by family, capabilities, factories, the
  registry, the HTTP helpers; the audience's query, dimensions, metrics, rows and reports.
- [Models](models.md): the reviews' and the funding's models, field by field.
- Usage: [search performance](usage-search.md), [audience](usage-audience.md),
  [reviews](usage-reviews.md), [funding](usage-funding.md).
- [Configuration reference](configuration.md): the registry's configuration and every provider's
  options.
- [Errors](errors.md): every exception, its message, what to catch where.
- [Testing](testing.md): the fixed providers, the recorded HTTP client, the load check.
- [The Docker harness](harness.md): the core and every provider installed, its commands.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
