---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` composes this core and every provider of the three families (`docker/harness/plugins.php`)
into one installation, PHP 8.4 with Composer, in the image `omnisense/omnipulse-harness`:

```sh
cd docker && cp .env.dist .env               # the platforms' keys and places, when you have them
docker compose run --rm omnipulse test       # every package's tests, no network
docker compose run --rm omnipulse bare       # the bare script: the three families in plain PHP, and what PHP loaded
docker compose run --rm omnipulse reviews    # the review platforms installed, on their recorded answers
docker compose run --rm omnipulse live       # the checks that reach a platform (Matomo's demo, Open Collective, Liberapay, GitHub with a token)
docker compose run --rm omnipulse phpstan    # PHPStan at the highest level, each package
docker compose down -v                       # the harness's volume gone
```

It replaces the three harnesses of the former families (omnireach's, omnireview's and omnifund's),
whose commands it keeps: omnireview's `bare` is `reviews` here, its console (`gateways`) is gone -
`reviews` prints the same table of what each platform gives and on which terms.

## What it installs

At each start, `docker/harness/setup.php` writes the harness's `composer.json` and `phpunit.xml`
in the volume (`/harness`), and the entrypoint runs `composer install` the first time, `composer
update` when the `composer.json` changed:

- the core from its checkout (mounted at `/omnipulse/core`, a path repository, symlinked);
- each provider from a checkout under `OMNIPULSE_PLUGINS` when one is there whose `composer.json`
  names it - set it to `../..` in `docker/.env` (git-ignored) when the core sits in the workspace
  beside the providers - else from GitHub
  (`https://github.com/glitchr-omnisense/omnipulse-<slug>.git`, branch 1.x, a plain git clone);
- `phpunit/phpunit` 11, `phpstan/phpstan` 2.1, `symfony/http-client`, and for the bridges' tests
  `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/cache` and
  `twig/twig` (the bare script proves that none is needed);
- one PHPUnit suite per package (`core`, `bing-webmaster`, `ga4`, `matomo`, `plausible`,
  `search-console`, `google-reviews`, `tripadvisor`, `trustpilot`, `github-sponsors`,
  `liberapay`, `opencollective`) and a link `/harness/packages/<slug>` to each provider's
  directory.

`plugins.php` gives each provider its tests' namespace and its factory class. The service uses
Docker's default bridge network (no network of its own) and one named volume,
`omnipulse-harness_harness`, which keeps the installation and Composer's cache between runs.

## The commands

| Command | Runs |
|---|---|
| `test` (the default) | `vendor/bin/phpunit --exclude-group live`, extra arguments passed on (`test --filter Matomo`) |
| `live` | `vendor/bin/phpunit --group live`: Matomo's public demo, Open Collective and Liberapay without a key, GitHub Sponsors when `OMNIPULSE_GITHUB_TOKEN` is set |
| `bare` | `docker/harness/bin/bare`: one registry with the three fixed providers - a search report, an audience report with a filter, `lastDays()`; a rating, reviews, the write link, the terms, a platform down; a profile, sponsors, tiers, a factory not installed - then a recorded JSON API, a refused key and `Json`'s typed reads; it prints the results and every class and file PHP loaded, as JSON |
| `reviews` | `docker/harness/bin/reviews`: the review packages installed, each asked a place's rating, three reviews and its write link on its recorded answers; `--live` asks for real each platform whose key and place are set in `.env`; `--json` prints the whole report; exits 1 when a class of a framework was loaded |
| `phpstan` | PHPStan on the core and each provider with its own `phpstan.neon`; fails when one fails |
| `composer ...`, `php ...`, `sh` | as they are, in `/harness` |

```
$ docker compose run --rm omnipulse test
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27
Configuration: /harness/phpunit.xml

...............................................................  63 / 112 ( 56%)
.................................................               112 / 112 (100%)

Time: 00:00.455, Memory: 40.00 MB

OK (112 tests, 632 assertions)
```

(2026-11-10, every provider from its checkout)

`bare` prints, among the rest (2026-11-10):

```
    "rating": [8.6, 10, 42, 4.3, "https://reviews.example/p1"],
    "down": "[fixed-reviews] No answer.",
    "monthly": "52.00",
    "missing": "No \"missing\" factory for the \"nowhere\" provider; installed: fixed, fixed-reviews, array.",
    "families": {"site": ["audience"], "reviews": ["reviews"], "funding": ["funding"]},
    "refused": "Example refused the credentials (401) for it: Invalid API key",
```

and its `loaded.symbols` hold, besides PHP's own classes and PHPStan's PHAR autoloader, only
`Omnipulse\...` and `Symfony\Contracts\HttpClient\HttpClientInterface` and `ResponseInterface`:
no framework class, no HTTP client. `Tests/BareTest.php` checks it.

`reviews` prints (2026-11-10):

```
The review platforms in bare PHP: the registry built by hand, no bundle, no container, no Twig; the platforms' recorded answers.

  google-reviews Google: gives rating reviews writeUrl; kept never, 5 reviews at most, credited to "Google Maps"
  tripadvisor    Tripadvisor: gives rating reviews writeUrl; kept as its policy says, 5 reviews at most, credited to "Tripadvisor", pages not indexed
  trustpilot     Trustpilot: gives rating reviews writeUrl; kept 86400 s, 100 reviews at most, credited to "Trustpilot"

  google-reviews 4.6/5 from 128 reviews, https://maps.google.com/?cid=10223305813012340704
  ...
Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle or bridge, Doctrine, Twig): none
```

Of Symfony it loads the HTTP client (the recorded answers are a `MockHttpClient`'s, and the review
providers require `symfony/http-client`): `Tests/ReviewPlatformsTest.php` runs `reviews --json`
and checks that nothing else was, and - so that the check is not blind - that loading the bundle
does name the framework.

## Settings

`docker/.env` (copied from `.env.dist`, git-ignored):

| Variable | |
|---|---|
| `OMNIPULSE_PLUGINS` | where the providers' checkouts are (`../..` in the workspace); unset, they come from GitHub |
| `OMNISENSE_CONTRACTS` | where omnisense/contracts' checkout is (`../../../omnisense-contracts` in the workspace), its tests run with the family's and PHPStan on it too; unset, it comes from GitHub |
| `OMNIPULSE_GITHUB_TOKEN` | a GitHub token for omnipulse/github-sponsors' live test (passed into the container by `compose.yml`); unset, that test is skipped |
| `GOOGLE_PLACES_KEY`, `GOOGLE_PLACE_ID` | the Places API key and a place: `google-reviews` is configured with both |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REFRESH_TOKEN`, `GOOGLE_LOCATION` | the owner's side: the OAuth client, the refresh token, `accounts/<a>/locations/<l>` |
| `TRIPADVISOR_KEY`, `TRIPADVISOR_LOCATION`, `TRIPADVISOR_REFERER` | the Content API key, a location ID, the `Referer` |
| `TRUSTPILOT_API_KEY`, `TRUSTPILOT_BUSINESS_UNIT` | the key and a business unit ID |
| `TRUSTPILOT_API_SECRET`, `TRUSTPILOT_BUSINESS_USER_ID` | replying and inviting |

`docker/harness/config/review-platforms.php` reads the review platforms' variables: a platform is
configured from them only when every variable of its `needs` is set, else with example options
(`api_key: example`) and an example place; `reviews` uses the example options with the recorded
answers (`docker/harness/config/recorded.php`) unless `--live` is given and the variables are set.
`OMNIPULSE_AUTOLOAD` makes `bare` and `reviews` use another autoloader.

## Running a script of your own

Mount a directory and run PHP with the harness's autoloader (`/harness/vendor/autoload.php`):

```sh
docker compose run --rm -v "$PWD/scratch:/scratch" omnipulse php /scratch/report.php
```

The examples of this manual were run that way. A provider with an account is called the same way,
its keys in `docker/.env` or given with `-e NAME=value`.
