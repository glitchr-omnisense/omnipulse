# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0` with no stability flag, so `composer require omnisense/omnipulse` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

### Added
- omnisense/omnipulse on omnisense/contracts, the kernel every omni family builds on, with usage
  policies. `Config`, `ProviderInterface`, `ProviderFactoryInterface`, `ProviderFactory`
  (`populate()` and `build()` as before), `Registry`, `Http\Redaction`, `Testing\RecordedHttpClient`
  (its `routes()` by method and path end, as before; a route may give headers) and
  `RecordedResponse` keep their names and their API, each a thin subclass of the kernel's (prefix
  `omnipulse`, omnipulse's exceptions); `OmnipulseException` extends the kernel's
  `ExceptionInterface`.
- `Http\JsonClient`, the kernel's JsonClient under omnipulse's name (401/403 an
  `InvalidKeyException`, 404 a `NotFoundException`): the three helpers make their calls through
  it - `JsonApi` (a third constructor argument, the provider's `Backoff`), `Answer::send()` and
  `Json::request()` (a last argument, the same) - each keeping its errors and messages; given the
  Backoff, a 429 and its `Retry-After`, or a `RateLimit` field with nothing left, hold the
  provider's next calls. `Support\Data`, `Answer` and `Json`'s readers stay omnipulse's (their API
  is not the kernel's `Data`'s), and so does `Testing\LoadCheck`, which now allows
  omnisense/contracts' files.
- `Capabilities::policy()`: the provider's `UsagePolicy`, given (`policy:`) or made of `$key`,
  `$terms` and `$limits`, which stay, deprecated, read from the policy when it is given;
  `toArray()` has `policy`. `Model\Terms::rights()`: a review platform's caching and attribution
  as the policy's rights.
- The typed getters give the provider behind its guard (`Policy\GuardedSearchPerformance`,
  `GuardedAudience`, `GuardedRating`, `GuardedReviews`, `GuardedReply`, `GuardedInvite`,
  `GuardedWriteUrl`, `GuardedProfile`, `GuardedSponsors`, `GuardedTiers`; each call an operation
  named after its method; `inner()`): `Exception\UsageLimitReachedException`. A site's `policy`
  (never looser) and `usage` per entry or in its options; `Registry::policy()`, `policies()`,
  `guard()`, `names()` by contract; a shared `RateLimiterInterface` as the registry's third
  argument. `get()`, `all()`, `create()` and `terms()` stay unguarded.
- The harness mounts omnisense/contracts' checkout (`OMNISENSE_CONTRACTS`) and runs its tests, and
  names its entrypoint (the image may be another family's harness tagged).

### Changed
- A typed getter no longer gives the provider object itself but its guarded wrapper (same
  contract, same answers): code comparing it with `get()` compares `inner()`.

### Changed
- `omnisense/omnireach` is now `omnisense/omnipulse` (2026-11-10: the family is renamed before
  omnireview and omnifund merge into it): the namespace `Omnipulse\` (`OmnireachException` is
  `OmnipulseException`), the configuration keys `omnipulse.factory_name`, `omnipulse.factory_title`
  and `omnipulse.required_options`, the harness's `OMNIPULSE_AUTOLOAD` and `OMNIPULSE_PLUGINS`, the
  providers `omnipulse/<provider>` at `glitchr-omnisense/omnipulse-<provider>`. `composer.json`
  replaces `omnisense/omnireach` (and `omniscient/omnireach`, as before), so that a project never
  installs both; the namespace changed, so code that used `Omnireach\` moves to `Omnipulse\`.
- omnisense/omnireview and omnisense/omnifund merged into omnisense/omnipulse (2026-11-10, Marco's
  decision), their histories kept (the merge commit's other parents): one family for audience,
  reviews and funding. `composer.json` replaces `omnisense/omnireview` (and `omniscient/omnireview`,
  `glitchr/omnireview`) and `omnisense/omnifund` (and `omniscient/omnifund`); `Omnireview\` and
  `Omnifund\` are `Omnipulse\`, so code that used them moves.
- One `Capabilities` for the three families (named arguments: the audience's fields, then
  `rating`, `reviews`, `reply`, `invite`, `writeUrl`, `profile`, `sponsors`, `tiers`, `key`,
  `terms`, `limits`); `Omnireview\Model\Capabilities` is `Omnipulse\Capabilities`, its `gives()`
  list is `contracts()`, where `write-url` is `writeUrl`; `toArray()` has every field, `contracts`
  and `families`. `key` is `?bool`, `null` when a provider does not say (omnifund's was `bool`,
  `false` by default).
- One `Registry`: the audience's, the reviews' and the funding's typed getters, each checking the
  contract and the capability as configured (omnireach's and omnifund's checked the interface
  only), their message `The "<name>" provider (<title>) does not <what>; it gives: <contracts>.`;
  `create()` and `all()` (omnireview's) for every family. An unknown provider is `No "x"
  provider; configured: ...` (was `... audience provider`, `... funding provider`, `... gateway`),
  an unknown factory `No "f" factory for the "x" provider; installed: ...`.
- Omnireview's gateways are providers: `GatewayInterface` is `ProviderInterface` (with
  `TermsInterface` for `terms()`), `GatewayFactory` is `ProviderFactory`, `GatewayFactoryInterface`
  is `ProviderFactoryInterface`; its configuration keys `omnireview.*` and omnifund's `omnifund.*`
  are `omnipulse.*`; `Config`'s missing-option message says `provider` (was `gateway`).
- One set of exceptions. `ProviderException` has `provider` and `providerCode` (omnireview's) and
  the constructors `answered()` (omnireach's and omnifund's, one signature: a status `int|string`,
  a message optional; its code the status when it is a number - omnifund's was 0) and `from()`
  (omnireview's `new ProviderException($provider, $message, $code)`); it is no longer `final`
  (omnifund's was). `UnreachableException` is not a `ProviderException` any more (omnireview's
  was); it has `provider`, `providerCode` and `from()` too. `InvalidConfigException` is an
  `\InvalidArgumentException` (omnireview's was a `\LogicException`, which it still is).
  `NotSupportedException` has `question()`, `operation()` and `capability()`, each saying
  `provider` (was `gateway`, `funding provider`).
- The Symfony bundle is `Omnipulse\Bridge\Symfony\OmnipulseBundle`, alias `omnipulse`, for every
  family: `omnireview.gateways` is `omnipulse.providers`, the services `omnipulse.provider.<name>`
  (were `omnireview.gateway.<name>`) aliased by name under every contract, the factories tagged
  `omnipulse.provider_factory`, the eleven known factories registered when installed (the funding
  ones need the application's `http_client`). Twig: `omnireview_rating()`, `omnireview_reviews()`,
  `omnireview_terms()`, `omnireview_write_url()` are `omnipulse_*()`, `omnireview_widget()` is
  `omnipulse_reviews_widget()`, the template `@Omnipulse/reviews.html.twig` (an override in
  `templates/bundles/OmnipulseBundle/`), its CSS classes `omnireview`, `omnireview__*` are
  `omnipulse-reviews`, `omnipulse-reviews__*`, the cache keys `omnipulse.reviews.*`.
- The Google reviews' factory is `google-reviews` (was `google`), in `omnipulse/google-reviews`
  (was `omnireview/google`); the review providers' classes are `...Provider` and `...Factory`
  (were `...Gateway`, `...GatewayFactory`).
- Testing: omnireview's `Tests\StubFactory` is `Testing\FixedReviews` and
  `Testing\FixedReviewsFactory` (name `fixed-reviews`), shipped beside `FixedReach` (`fixed`) and
  omnifund's `ArrayProvider` (`array`). `bin/load-all` leaves `Bridge/` out (the bridges need what
  the package only suggests).
- One harness for the three families (`docker/`, image `omnisense/omnipulse-harness`): the eleven
  providers, the bridges' Symfony components and Twig; `bare` runs the three families,
  `reviews [--live] [--json]` is omnireview's bare script, its console (`gateways`) is gone:
  `reviews` prints the same table. `OMNIREVIEW_*` and `OMNIFUND_*` settings are `OMNIPULSE_*`
  (`OMNIPULSE_PLUGINS`, `OMNIPULSE_AUTOLOAD`, `OMNIPULSE_GITHUB_TOKEN`).
- One manual (`docs/`), with `models.md`, `usage-reviews.md` and `usage-funding.md`; omnireview's
  `gateways.md` is `usage-reviews.md`, omnifund's `usage.md` is `usage-funding.md`.

### Fixed
- No exception `JsonApi::request()` throws keeps its trace's arguments: an error answered (a refused key,
  nothing there, a server error, a body that is not JSON) kept the request's options - the query,
  headers and token with the key - in its trace (`zend.exception_ignore_args` off), as the
  `UnreachableException` did before its fix.
- An `UnreachableException`'s chain no longer holds the key. Its previous exception was the HTTP
  client's own, whose message quotes the whole URL (Bing's `apikey=`, a URL's password), and every
  trace kept the arguments of the calls under it, the request's query, headers and bearer token
  among them. The previous is now a copy of the client's exception (`Http\Redaction::copy()`: the
  same class and code, the key's parameters, the URL's user part and the request's secrets masked
  as `***`), and no trace of the chain keeps its arguments (`Redaction::withoutArguments()`).
- An `UnreachableException` no longer quotes the URL called: its message names the method and the
  path (`... could not be reached for GetQueryStats (GET /json/GetQueryStats): ...`) and the HTTP
  client's reason without the address it quotes. Symfony's transport messages end with the whole
  URL, so Bing's `apikey=` stood in the message.

### Added
- `TermsInterface`: a review platform's `terms()`, which the review contracts extend.
- `Capabilities::families()`, `Capabilities::gives(string $contract)`,
  `Capabilities::FAMILIES`.
- `Registry::terms(string $name)`: a review platform's `Terms`.
- `Config::list()` (omnireview's) for every family.
- `ext-mbstring` in `composer.json`'s requirements: `Http\Answer` shortens bodies with
  `mb_substr()`.
- `Omnipulse\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`),
  a failure's copy to wrap in its place (`copy()`), a trace without arguments
  (`withoutArguments()`).
- `Omnipulse\Http\JsonApi::reason()`: an HTTP client's message without the addresses it quotes.

### From omnisense/omnireview

Before the merge, under `Omnireview\` (the names below are omnipulse's).

#### Fixed

- An `UnreachableException` no longer carries the request's secrets. `Answer::send()` put the HTTP
  client's message, the whole URL with a key in its query, in its message, wrapped the client's own
  exception as its previous, and every trace of the chain kept the arguments of the calls under it
  (the query, headers and body with the key, when `zend.exception_ignore_args` is off). The message
  and the previous - now a copy of the client's exception, the same class and code - have the key's
  parameters, the URL's user part and the request's secrets masked as `***` (`Http\Redaction`),
  and no trace of the chain keeps its arguments, a 5xx's or a 429's included.
- The registry's typed getters (`rating()`, `reviews()`, `replies()`, `invitations()`,
  `writeUrl()`) check the provider's `capabilities()` as well as its interface: Trustpilot without a
  business user was given by `replies()`, and its `reply()` then threw; the getter now throws the
  `NotSupportedException` naming what the provider gives.
- `omnipulse_write_url()` gives `null`, and `omnipulse_reviews_widget()` no write link, when the platform
  does not answer (`UnreachableException`): Tripadvisor and Trustpilot fetch the link, and their
  `HTTP 503` reached the template, where the extension shows nothing of a platform down.
  `OmnipulseExtension::writeUrl()` returns `?string`.
- An application's `templates/bundles/OmnipulseBundle/reviews.html.twig` overrides the widget:
  the bundle prepended its templates as a `twig.paths` entry, which TwigBundle registers before the
  bundles' override directories, so the bundle's own template kept winning. The bundle's path is
  now `Bridge/Twig` (its `templates/` found by TwigBundle after the override) and nothing is
  prepended to `twig`.

### From omnisense/omnifund

Before the merge, under `Omnifund\` (the names below are omnipulse's).

#### Fixed

- No exception `Json::request()` throws keeps its trace's arguments: an error answered (a refused key,
  nothing there, a server error, a body that is not JSON) kept the request's options - the query,
  headers and token with the key - in its trace (`zend.exception_ignore_args` off), as the
  `UnreachableException` did before its fix.
- An `UnreachableException` no longer carries the request's secrets. `Http\Json::request()` put the
  HTTP client's message, the whole URL, in its message, wrapped the client's own exception as its
  previous, and every trace of the chain kept the arguments of the calls under it (the query and
  headers with the token, when `zend.exception_ignore_args` is off). The message and the previous -
  now a copy of the client's exception, the same class and code - have the key's parameters, the
  URL's user part and the request's secrets masked as `***` (`Http\Redaction`), and no trace of
  the chain keeps its arguments.
