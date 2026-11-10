---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-11-10 in the [harness](harness.md), the core and the eleven providers from their
checkouts, PHP 8.4.27, PHPUnit 11.5.57, the `live` group left out, no network: 112 tests, 632
assertions, green - the core 42; search-console 6, bing-webmaster 7, matomo 9, plausible 6, ga4 5;
google-reviews 4, tripadvisor 4, trustpilot 4; github-sponsors 11, opencollective 8, liberapay 6.
PHPStan at the highest level on the core and the eleven providers, no error baselined
(`docker compose run --rm omnipulse phpstan`, the same day).

- The core, audience (`Tests/ModelTest.php`, `Tests/RegistryTest.php`): the query (whole days, its
  validation, filters then limit, `lastDays()` ending yesterday), the capabilities refusing what a
  provider cannot report, reports and their totals, the registry and its typed getters,
  `JsonApi`'s errors and the secrets kept out of them.
- The core, reviews (`Tests/ReviewsRegistryTest.php`, `Tests/AnswerTest.php`): the typed getters
  checking the contract and the capability as configured, with their messages; `outOfFive()`,
  `allowsCaching()`, `contracts()` and `families()`; `Answer::send()` keeping no secret in an
  unreachable chain nor in a 5xx's trace.
- The core, funding (`Tests/FundingModelTest.php`, `Tests/FundingRegistryTest.php`,
  `Tests/JsonTest.php`): amounts from decimals and back, a currency or an amount refused, an
  income by the month, a sponsor shown by name or handle, the typed readers; providers built
  once, a capability refused, a provider not configured, a required option missing; `Json`'s
  failures keeping no secret.
- The bridges (`Tests/Bridge/OmnipulseBundleTest.php`, `Tests/Bridge/OmnipulseExtensionTest.php`):
  providers of two families built from configuration and injected by their argument's name, the
  installed packages' factories registered with the application's `http_client` (on a
  `ContainerBuilder`, not a kernel); the widget's origin, date, author link, escaped text, review
  and write links, no JSON-LD; the cache used for the platform's allowance and not for `0` or
  `null`; a platform down shows nothing; the widget where TwigBundle lets an application override
  it.
- In plain PHP (`Tests/BareTest.php`): the three families asked through one `Registry` and a
  recorded JSON API, loading nothing but Omnipulse and Symfony's HTTP client contracts, and every
  class but the bridges loading with the package's requirements alone; the review platforms
  installed asked a rating, reviews and a write link on their recorded answers, no framework
  loaded (`Tests/ReviewPlatformsTest.php`).
- The installation page's funding script run as written (`Tests/DocsTest.php`).
- Each provider: its requests and its mapping on answers recorded or composed (below), its
  refusals, a refused key, its factory through a `Registry`, and a `BareTest` loading every class
  with the package's requirements alone.

| Provider | Answers its tests use |
|---|---|
| search-console | composed in the shape the Search Console reference documents; the service account's JWT checked with a key made by the test |
| bing-webmaster | the JSON sample of `GetQueryStats` in Bing's reference, and rows composed in its shape |
| matomo | recorded from Matomo's public demo (demo.matomo.cloud, site 1) on 2026-10-24, trimmed of unread fields |
| plausible | composed in the shape the Stats API v2 documents |
| ga4 | composed in the shape the `runReport` reference documents; a service account's token with a key made by the test |
| google-reviews, tripadvisor, trustpilot | written from each platform's published reference, not captured |
| github-sponsors, opencollective, liberapay | the shapes of live answers of 2026-10-24, the people invented |

There is no conformance suite shared by the providers ([Testing](testing.md#no-conformance-suite)).

## What was run live

- `docker compose run --rm omnireach live` (the former name; `omnipulse live` now), 2026-10-24:
  Matomo's public demo, the whole site over a week and its entry pages day by day.
- For the audience's manual, 2026-10-26, 14:49-14:51 UTC: the
  [Installation](installation.md#an-audience-providers-api) script against the same demo, and a
  report by device and with device filters ([Audience](usage-audience.md#devices)), which showed
  two defects of Matomo's devices, fixed on 2026-10-29 (three `mobile` rows, a `mobile` filter
  refused). Every segment the demo was asked for (`deviceType==smartphone`, `deviceType==desktop`)
  answered 0 visits: the demo does not process segments on the fly, so filters on Matomo were not
  verified live.
- The funding providers' READMEs state live runs on 2026-10-24 (omnifund's harness, `live`): Open
  Collective's GraphQL API without a key (webpack: profile, tiers, three backers), Liberapay's
  public.json (the Liberapay team), GitHub's GraphQL API with a token (glitchr-studio's listing,
  sindresorhus's tiers and two sponsors).
- Google's link to write a review answered 200 on 2026-10-08.

Nothing was run live again after the merge of 2026-11-10: the providers' code changed by its
namespace, its class names (the review providers') and its typed reads of the answers (for
PHPStan), checked on the recorded answers only.

## What is not verified

- Search Console, Bing Webmaster, Plausible and Google Analytics were never called: each needs an
  account and a key. Their answers in the tests follow their published references.
- No review platform was called live: no Google Places key, no Business Profile access, no
  Tripadvisor Content API key, no Trustpilot application. Whether Trustpilot's Data Solutions API
  answers with a plain API key is Trustpilot's plans' to say.
- Tripadvisor's Caching Policy is not public: nothing of Tripadvisor's is kept until it is read.
- GitHub's funding goal as a live answer (no listing at hand had an active goal: the shape is
  GitHub's schema, accepted by the live query); an Open Collective personal token.
- A self-hosted Matomo with a token; positions as Bing gives them (`AvgImpressionPosition`); how
  Search Console and Plausible match a device filter's case (`mobile` is sent as given).
- The Symfony bundle in a real kernel with TwigBundle (the template override, a cache pool, the
  funding factories with `framework.http_client`), and the [Laravel](laravel.md) service
  provider: written from the code.
- An application using the family: none was run for this manual.

## Known defects

None known. Those the former manuals found were fixed before the merge (2026-10-29 for the
audience's, the review family's in the commits that wrote its manual).

## Responsibility

`omnisense/omnipulse` and its `omnipulse/*` providers are unofficial clients, affiliated with none
of the services they reach. The MIT licence covers the code only and grants no right to any
service or to its data. Whoever runs them complies with each service's terms (named in each
provider's README and manual, in `Capabilities::$terms` and, for a review platform, in its
`Terms`) and obtains what they require. Reviewers' and sponsors' names and pictures are personal
data: show only what the platform shows publicly.
