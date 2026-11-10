---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry's configuration

`new Registry($factories, $config, $limiter = null)` takes the providers by name, each with its
factory and options:

```php
[
    '<provider name>' => [
        'factory' => '<factory name>',     // required: a factory's getName()
        'options' => [/* ... */],          // optional, default []: handed to the factory's create()
        'policy' => [/* ... */],           // optional: the site's tightening of the provider's usage policy
        'usage' => ['commercial' => true], // optional: what the site is (commercial, open_source, contract)
    ],
]
```

`policy` and `usage` may sit in `options` too; a looser `policy` is refused
([Usage policies](policies.md)). `$limiter` is where every provider's calls are counted: an
in-memory one by default, a shared one for an application served by many processes.

The provider name is the application's (`google`, `bing`, `stats`); the factory name is the
package's. Options are checked when the provider is first asked for, not when the registry is
built: a required option missing, `''` or `[]` is an `InvalidConfigException` naming it.

Every factory takes the HTTP client in its constructor - `new MatomoFactory($http)`; the audience's
refuse to build without one, the review platforms' make their own, the funding platforms' require
one ([Installation](installation.md#requirements)). Options read as strings accept any scalar (a
site id given as an integer included); an empty string is the same as an option not given. The
readers a factory uses are `Config`'s ([Concepts](concepts.md#factory-and-config)).

In Symfony the same configuration is the bundle's `omnipulse.providers`
([Symfony and Twig](symfony.md#configuration)).

- Audience: [search-console](#omnipulsesearch-console), [bing-webmaster](#omnipulsebing-webmaster),
  [matomo](#omnipulsematomo), [plausible](#omnipulseplausible), [ga4](#omnipulsega4).
- Reviews: [google-reviews](#omnipulsegoogle-reviews), [tripadvisor](#omnipulsetripadvisor),
  [trustpilot](#omnipulsetrustpilot).
- Funding: [github-sponsors](#omnipulsegithub-sponsors), [opencollective](#omnipulseopencollective),
  [liberapay](#omnipulseliberapay).
- [The test providers](#the-test-providers): `fixed`, `fixed-reviews`, `array`.

## omnipulse/search-console

Factory `Omnipulse\SearchConsole\SearchConsoleFactory`, name `search-console`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `site` | string | - | yes | the property: `sc-domain:example.org` (a domain property) or `https://example.org/` (a URL-prefix property) |
| `credentials` | string | `''` | one of the two | a service account's JSON key, or the path of its file; the account added as a user of the property |
| `access_token` | string | `''` | one of the two | a token with the `webmasters.readonly` scope, obtained elsewhere; used as it is, never refreshed |
| `data_state` | string | `final` | no | `final`: complete days only; `all`: the last days too, provisional (the report's note says so) |
| `url` | string | `https://searchconsole.googleapis.com/webmasters/v3` | no | the API's base URL, trailing slash removed |

With neither `credentials` nor `access_token`, `create()` throws `InvalidConfigException` (`A
Google API needs a service account's key or an access token.`); credentials that are neither a key
nor the path of one too. With both, the access token is used and the key never.

## omnipulse/bing-webmaster

Factory `Omnipulse\BingWebmaster\BingWebmasterFactory`, name `bing-webmaster`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | Bing Webmaster Tools, Settings, API access |
| `site` | string | - | yes | the site as verified there: `https://example.org/` |
| `url` | string | `https://ssl.bing.com/webmaster/api.svc/json` | no | the JSON endpoint's base URL |

## omnipulse/matomo

Factory `Omnipulse\Matomo\MatomoFactory`, name `matomo`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `url` | string | - | yes | the installation: `https://stats.example.org`, `https://example.matomo.cloud` (trailing slash removed; `/index.php` is added) |
| `site` | string | - | yes | the site's id (`idSite`) |
| `token` | string | `anonymous` | no | a `token_auth` with view access; `anonymous` for reports anyone may view |

## omnipulse/plausible

Factory `Omnipulse\Plausible\PlausibleFactory`, name `plausible`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | a Stats API key |
| `site` | string | - | yes | the site's domain as Plausible knows it: `example.org` |
| `url` | string | `https://plausible.io` | no | plausible.io, or a self-hosted Community Edition |

## omnipulse/ga4

Factory `Omnipulse\Ga4\Ga4Factory`, name `ga4`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `property` | string | - | yes | the property's numeric id (Admin, Property details), not the `G-` measurement id |
| `credentials` | string | `''` | one of the two | a service account's JSON key, or the path of its file; the account given the Viewer role on the property |
| `access_token` | string | `''` | one of the two | a token with the `analytics.readonly` scope, obtained elsewhere |
| `url` | string | `https://analyticsdata.googleapis.com/v1beta` | no | the Data API's base URL |

## omnipulse/google-reviews

Factory `Omnipulse\GoogleReviews\GoogleReviewsFactory(?HttpClientInterface $http = null)`, name
`google-reviews` (`HttpClient::create()` when `null`).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | `null` | one side or the other | the Places API (New) key: anyone's place |
| `language` | string | `null` | no | the reviews' language (`languageCode` of the Places API), unless a call gives its own |
| `client_id` | string | `null` | for the owner's side | an OAuth 2.0 client |
| `client_secret` | string | `null` | for the owner's side | |
| `refresh_token` | string | `null` | for the owner's side | granted once by the owner, scope `business.manage` |

`api_key`, or all three of the owner's, or both; neither is an `InvalidConfigException` (`The
"google-reviews" provider needs an api_key (Places), or the owner's client_id, client_secret and
refresh_token (Business Profile).`). `reply` is given only with the owner's three.

## omnipulse/tripadvisor

Factory `Omnipulse\Tripadvisor\TripadvisorFactory(?HttpClientInterface $http = null)`, name `tripadvisor`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the Content API's partner key |
| `language` | string | `en` | no | the content's language (`fr`, `de`, `ja`...) |
| `referer` | string | `null` | no | the `Referer` header sent: for a key restricted to domains |

## omnipulse/trustpilot

Factory `Omnipulse\Trustpilot\TrustpilotFactory(?HttpClientInterface $http = null)`, name `trustpilot`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the application's key: the TrustScore, the reviews, the links |
| `locale` | string | `fr-FR` | no | the locale of the profile's links (`/web-links`); an invitation is sent in its own `Invitation::$locale`, never this option |
| `api_secret` | string | `null` | for reply and invite | the application's secret: the business user's token |
| `business_user_id` | string | `null` | for reply and invite | the business user who replies and invites |
| `template_id` | string | `null` | no | the invitation e-mail's template |
| `reply_to` | string | `null` | no | the invitation's reply-to address |
| `sender_name` | string | `null` | no | the invitation's sender name |
| `sender_email` | string | `null` | no | the invitation's sender address |

`reply` and `invite` are given only with `api_secret` and `business_user_id`.

## omnipulse/github-sponsors

Factory `Omnipulse\GitHubSponsors\GitHubSponsorsFactory(HttpClientInterface $http)`, name `github-sponsors`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `account` | string | - | yes | the user or organization whose listing is read: `glitchr-studio` |
| `token` | string | - | yes | a GitHub token, sent as `Authorization: Bearer`. GitHub's GraphQL API answers no anonymous call; any token reads a public listing and its public sponsors - a fine-grained token with no permission is enough |
| `endpoint` | string | `https://api.github.com/graphql` | no | GitHub Enterprise: `https://HOST/api/graphql` |
| `user_agent` | string | `omnipulse (+https://github.com/glitchr-omnisense/omnipulse)` | no | sent with every call (GitHub refuses a request without one) |

## omnipulse/opencollective

Factory `Omnipulse\OpenCollective\OpenCollectiveFactory(HttpClientInterface $http)`, name `opencollective`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `account` | string | - | yes | the collective's slug: `webpack` |
| `token` | string | `''` (none) | no | a personal token, sent as `Personal-Token` when set: it raises the rate limit (10 calls a minute without one) |
| `endpoint` | string | `https://api.opencollective.com/graphql/v2` | no | |
| `user_agent` | string | as above | no | |

## omnipulse/liberapay

Factory `Omnipulse\Liberapay\LiberapayFactory(HttpClientInterface $http)`, name `liberapay`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `account` | string | - | yes | the username: `Liberapay` |
| `language` | string | `en` | no | the summary's language, when the account wrote several; else the first one |
| `base_url` | string | `https://liberapay.com` | no | the profile is `<base_url>/<account>/public.json` |
| `user_agent` | string | as above | no | Liberapay asks for one naming the application |

## The HTTP client (funding)

The client given to a factory is used as it is: its timeout, proxy and retries are the
application's. `HttpClient::create(['timeout' => 10])` is a reasonable start; Symfony's
`RetryableHttpClient` may wrap it for Open Collective's 429s. The same goes for every family's
factories.

## Per query (audience)

What a report covers is the query's, not the provider's options: the period, the dimensions, the
metrics, the filters, the limit ([Concepts](concepts.md#the-audiences-query)). `ReachQuery::$site`, when set,
replaces the provider's `site` (or `property`) for that report only.

## The Twig extension

`omnipulse.cache` configures the review functions in Symfony
([Symfony and Twig](symfony.md#configuration)). For a hand-built Twig environment:

```php
new Omnipulse\Bridge\Twig\OmnipulseExtension(Registry $registry, ?Symfony\Contracts\Cache\CacheInterface $cache = null, int $ttl = 3600);
```

## The test providers

### fixed

`Omnipulse\Testing\FixedReachFactory`, name `fixed`, takes its rows in its constructor
(`new FixedReachFactory($searchRows, $audienceRows)`) and one option:

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `site` | string | `https://example.org/` | no | the `site` its reports name |

### fixed-reviews

`Omnipulse\Testing\FixedReviewsFactory()`, no HTTP client ([Testing](testing.md#the-fixed-review-platform)).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `host` | string | - | yes | the host its links point to: `reviews.example` |
| `cache_for` | int or `null` | `3600` | no | the seconds its `Terms` let what it gives be kept: `0` never, `null` not said |

### array

`Omnipulse\Testing\ArrayFactory()`, no HTTP client ([Testing](testing.md#the-array-provider)).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `profile` | `Model\Profile` | - | yes | answered by `profile()`, whatever the account asked for |
| `sponsors` | `list<Model\Sponsor>` | `[]` | no | answered by `sponsors()`, cut to the limit; entries that are not a `Sponsor` are dropped |
| `tiers` | `list<Model\Tier>` | `[]` | no | answered by `tiers()` |

A `profile` that is not a `Profile`, or `sponsors` / `tiers` that are not arrays, is an
`InvalidConfigException` (`The "array" provider needs a Profile, and lists of sponsors and
tiers.`).

