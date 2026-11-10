---
title: Symfony and Twig
order: 15
---

# Symfony and Twig

Omnipulse runs without a framework ([Installation](installation.md)); in a Symfony application its
bundle does the wiring, for every family: each provider configured by name, built by the registry,
injectable under each contract it may give; and Twig functions for the reviews. The bundle lives in
this package (`Omnipulse\Bridge\Symfony`), not in omnibridge/symfony. Its components -
`symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` (6.4, 7 or 8), and
`twig/twig` and `symfony/cache-contracts` for the Twig functions - are not required by
`omnisense/omnipulse`: the application has them, and nothing of them is loaded outside Symfony.

## Registering the bundle

There is no Flex recipe: add the bundle by hand.

```php
// config/bundles.php
return [
    // ...
    Omnipulse\Bridge\Symfony\OmnipulseBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/omnipulse.yaml
omnipulse:
    providers:
        google:      { factory: search-console, options: { site: 'sc-domain:example.org', credentials: '%env(resolve:GOOGLE_SERVICE_ACCOUNT)%' } }
        stats:       { factory: matomo, options: { url: 'https://stats.example.org', site: '1', token: '%env(MATOMO_TOKEN)%' } }
        reviews:     { factory: google-reviews, options: { api_key: '%env(GOOGLE_PLACES_KEY)%', language: fr } }
        tripadvisor: { factory: tripadvisor, options: { api_key: '%env(TRIPADVISOR_KEY)%', language: fr, referer: 'https://www.example.org/' } }
        liberapay:   { factory: liberapay, options: { account: 'Liberapay' } }
    cache:
        pool: cache.app      # where the Twig review functions keep what they show
        ttl: 3600            # at most; never longer than the platform allows
```

```sh
# .env.local, or bin/console secrets:set
GOOGLE_SERVICE_ACCOUNT=%kernel.project_dir%/config/secrets/search-console.json   # the key's path, or its JSON
```

The whole tree, from `OmnipulseBundle::configure()`:

| Key | Type | Default | |
|---|---|---|---|
| `omnipulse.providers` | map, keyed by the provider's name | `{}` | the platforms the application uses, any family |
| `omnipulse.providers.<name>.factory` | string, required, not empty | - | a factory's name: `search-console`, `matomo`, `google-reviews`, `trustpilot`, `liberapay`..., or an application's own factory's `getName()` |
| `omnipulse.providers.<name>.options` | any value (a map) | `[]` | handed as they are to the factory's `create()` ([Configuration](configuration.md)) |
| `omnipulse.cache.pool` | string or `null` | `cache.app` | the service id of a Symfony cache (`Symfony\Contracts\Cache\CacheInterface`) the Twig review functions keep what they show in; `null`, or an id that does not exist: nothing is kept |
| `omnipulse.cache.ttl` | integer, at least 0 | `3600` | seconds at most; the platform's `Terms::$cacheFor` caps it |

The options are not validated when the container compiles - the tree accepts any value - but
when the provider is built: a required option missing or empty is an `InvalidConfigException`
naming it, the first time the provider is asked for.

## Services

| Service id | Class | Autowiring | |
|---|---|---|---|
| `Omnipulse\Registry` | `Registry` | `Registry $registry` | every configured provider by name. Public. |
| `omnipulse.provider.<name>` | `ProviderInterface` | `<Contract> $<name>` for each of `ProviderInterface`, `SearchPerformanceInterface`, `AudienceInterface`, `TermsInterface`, `RatingInterface`, `ReviewsInterface`, `ReplyInterface`, `InviteInterface`, `WriteUrlInterface`, `ProfileInterface`, `SponsorsInterface`, `TiersInterface` | one provider, built by `Registry::get('<name>')` the first time it is injected |
| the factories of the provider packages installed (`OmnipulseBundle::factories()`: the eleven of this family) | the provider's factory | by its class | registered when its package is installed (the class exists); autowired, tagged `omnipulse.provider_factory`; its `$http` argument the application's `http_client` service ([below](#the-http-client)) |
| `Omnipulse\Bridge\Twig\OmnipulseExtension` | the Twig extension | - | registered when `twig/twig` is installed; tagged `twig.extension`; given the registry, the cache pool and the ttl |

```php
use Omnipulse\AudienceInterface;
use Omnipulse\ProfileInterface;
use Omnipulse\RatingInterface;

final class AboutController
{
    public function __construct(
        private readonly AudienceInterface $stats,         // the "stats" provider
        private readonly RatingInterface $reviews,         // the "reviews" provider
        private readonly ProfileInterface $liberapay,      // the "liberapay" provider
    ) {
    }
}
```

The argument's name selects the provider (Symfony's named autowiring aliases,
`registerAliasForArgument()`). A provider name that is not a valid PHP variable name is still
reachable by its service id or by the registry.

> [!NOTE]
> An alias is registered under each of the twelve interfaces for every provider, whatever it
> implements: `ReplyInterface $tripadvisor` compiles, and fails with PHP's `TypeError` when the
> service is built (Tripadvisor's provider implements no `ReplyInterface`). Ask the registry
> (`$registry->replies('tripadvisor')`) for a `NotSupportedException` that names what it gives.

Nothing is built, nor checked, when the container compiles: a key missing shows on first use. Keys
typed in a back office rather than set in the environment: `$registry->create('trustpilot',
['api_secret' => $stored])`, a provider built afresh with those options over the configured ones and
not kept by the registry.

### The HTTP client

Each factory's `$http` argument is FrameworkBundle's `http_client` service (its `http_client`
section, enabled by default when `symfony/http-client` is installed):

- the audience's and the review platforms' factories take a nullable client: without an
  `http_client` service they get `null` - the review platforms then make their own, the audience's
  refuse to build their provider (`InvalidConfigException`);
- the funding platforms' factories take a client that is not nullable: they need the
  `http_client` service, and the container does not compile without it once one of
  `omnipulse/github-sponsors`, `omnipulse/opencollective`, `omnipulse/liberapay` is installed.
  Enable `framework.http_client`.

## An application's own provider

A class of the application that implements `Omnipulse\ProviderFactoryInterface` (usually by
extending `Omnipulse\ProviderFactory`) is autoconfigured: tagged `omnipulse.provider_factory`, it
joins the registry, and its `getName()` can be named as a `factory` ([Writing a
provider](providers.md)):

```yaml
omnipulse:
    providers:
        guestbook:
            factory: guestbook     # App\Reviews\GuestbookFactory::getName()
```

The bundle registers no command, event, route or controller. A report or a funding profile is not
cached by Omnipulse: keep `Report::toArray()` or `Profile::toArray()` in the application's cache
(the platforms' quotas make it worth it); the Twig functions below cache the reviews within each
platform's terms.

## Twig

| Function | Returns | |
|---|---|---|
| `omnipulse_reviews_widget(provider, place, limit = 3, language = null)` | HTML (safe) | `@Omnipulse/reviews.html.twig` rendered: the rating, the reviews, the link to write one - each only when `capabilities()` gives it |
| `omnipulse_rating(provider, place)` | `Rating` or `null` | `null` when the platform does not answer (`UnreachableException`) |
| `omnipulse_reviews(provider, place, limit = 5, language = null)` | `list<Review>` | empty when the platform does not answer |
| `omnipulse_write_url(provider, place)` | ?string | the address to write a review; `null` when the platform does not answer |
| `omnipulse_terms(provider)` | `Terms` | the platform's rules; nothing called |

`provider` is the configured name of a review platform; `place` the platform's identifier (a string, made a `Place`) or
a `Place` (needed for Google's owner side, which reads `Place::$owner`).

```twig
{{ omnipulse_reviews_widget('google', restaurant.googlePlaceId) }}

{% set rating = omnipulse_rating('trustpilot', '46d6a890000064000500e0c3') %}
{% if rating %}{{ rating.value }}/{{ rating.scale }} ({{ rating.count }}){% endif %}

<a href="{{ omnipulse_write_url('google', restaurant.googlePlaceId) }}">Leave a review on Google</a>
```

The widget for a Trustpilot business unit, one review, on the recorded answers:

```
<section class="omnipulse-reviews omnipulse-reviews--trustpilot">
    <header class="omnipulse__origin">                        <strong class="omnipulse__score">4.4</strong><span class="omnipulse__scale">/5</span>
            <span class="omnipulse__count">469</span>        <a class="omnipulse__platform" href="https://fr.trustpilot.com/review/www.maison-erable.example" rel="noopener" target="_blank">Trustpilot</a>    </header>
            <article class="omnipulse__review">
            <p class="omnipulse__author">
                                Camille R.                <time datetime="2026-09-30T12:00:00+00:00">30/09/2026</time>                <span class="omnipulse__stars">5/5</span>            </p>
            <p class="omnipulse__title">Parfait</p>            <blockquote class="omnipulse__text">“Livraison rapide, produit conforme.”</blockquote>            <p class="omnipulse__reply">Merci Camille !</p>            <a class="omnipulse__link" href="https://www.trustpilot.com/reviews/507f191e810c19729de860ea" rel="noopener" target="_blank">Trustpilot</a>        </article>
        <a class="omnipulse__write" href="https://fr.trustpilot.com/evaluate/www.maison-erable.example" rel="noopener" target="_blank">Trustpilot</a></section>
```

(run on 2026-10-26 in the harness's image, before the merge: `OmnireviewExtension` on a Twig
`Environment`, an `ArrayAdapter` cache, the providers' recorded answers; shown here with the
classes the template has since 2026-11-10, `omnipulse-reviews` for `omnireview`, which
`Tests/Bridge/OmnipulseExtensionTest.php` checks)

### What the widget shows

- A `<section class="omnipulse-reviews omnipulse-reviews--<factory name>">`, with `data-nosnippet` when
  `terms.noIndex` is set (Tripadvisor).
- The rating: the platform's own picture of the score when it gives one (`Rating::$image`,
  Tripadvisor's bubbles, from Tripadvisor's address), the score with one decimal, the scale, the
  count; then `terms.attribution` linked to `Rating::$url` (plain text without a rating or a URL).
- Each review: the author's photo (32 x 32) and name (linked to `authorUrl`, `rel="noopener
  nofollow"`), its date (`<time datetime>` in ISO 8601, shown `d/m/Y`), the platform's picture of
  its score or `rating/scale`, its title, its text escaped within quotation marks, the
  establishment's reply, and `terms.attribution` linked to the review on the platform.
- The link to write one, labelled with the provider's title.

There is **no JSON-LD**: reviews collected by a third party are not marked up as the site's own
`AggregateRating` (`Tests/Bridge/OmnipulseExtensionTest.php` checks it).

`data-nosnippet` keeps the widget out of search snippets only: for Tripadvisor, **the page
itself** must carry `<meta name="robots" content="noindex">` - the application's layout decides
that, testing `omnipulse_terms('tripadvisor').noIndex`.

### Dressing it

The bundle's path is `Bridge/Twig` (`OmnipulseBundle::getPath()`), so TwigBundle gives Twig's
`@Omnipulse` namespace the application's `templates/bundles/OmnipulseBundle/` first, then the
bundle's `Bridge/Twig/templates`. To dress the widget, copy `reviews.html.twig` to
`templates/bundles/OmnipulseBundle/reviews.html.twig` and change it (clear the cache the first
time the directory is created); `@!Omnipulse/reviews.html.twig` still names the bundle's own,
to extend it. Keep what `omnipulse_terms(provider)` requires: `attribution` shown and linked,
each author when `authors`, a link back when `link`.

An application can also write its own markup from the other four functions:

```twig
{% set terms = omnipulse_terms('google') %}
{% set rating = omnipulse_rating('google', place_id) %}
<aside class="reviews">
    {% if rating %}<a href="{{ rating.url }}">{{ rating.value|number_format(1) }}/{{ rating.scale }} - {{ rating.count }} reviews on {{ terms.attribution }}</a>{% endif %}
    {% for review in omnipulse_reviews('google', place_id, 3) %}
        <blockquote>“{{ review.text }}” - {{ review.author }}, <time datetime="{{ review.publishedAt|date('c') }}">{{ review.publishedAt|date('d/m/Y') }}</time></blockquote>
    {% endfor %}
</aside>
```

### Caching

`omnipulse_rating()` and `omnipulse_reviews()` keep what they fetch in the cache `pool` for
`min(ttl, Terms::$cacheFor)` seconds, under a key made of the provider's name, the place, and for
reviews the limit and language: not at all for Google (`cacheFor` 0) nor Tripadvisor (`null`: its
Caching Policy is not public), at most `ttl` (3600 by default, a day at the very most) for
Trustpilot. Without a pool nothing is kept. `omnipulse_write_url()` and `omnipulse_terms()` are
not cached; `TripadvisorProvider` keeps its location details for its own lifetime, so the rating and
the write link of a Tripadvisor widget are one call.

### A platform that does not answer

`omnipulse_rating()` and `omnipulse_write_url()` give `null`, `omnipulse_reviews()` an empty
list, when the platform raises `UnreachableException` (no connection, a timeout, a 5xx, a 429):
the write link is fetched on Tripadvisor (`/details`) and Trustpilot (`/web-links`), so a widget
of a platform down shows neither score nor write link, and no error. Any other failure - a key
refused (`InvalidKeyException`), a place unknown (`ProviderException`), a provider not configured -
reaches the template as an error.

Test the link before writing it:

```twig
{% set url = omnipulse_write_url('trustpilot', business_unit_id) %}
{% if url %}<a href="{{ url }}">Leave a review on Trustpilot</a>{% endif %}
```
