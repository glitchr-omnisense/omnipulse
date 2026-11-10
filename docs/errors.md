---
title: Errors
order: 50
---

# Errors

Every exception Omnipulse throws implements `Omnipulse\Exception\OmnipulseException` (a
`\Throwable`), whichever family the provider serves:

| Exception | Extends | When |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | an audience query no report can answer: the period ends before it starts, a limit below 1, a filter on no dimension |
| `InvalidConfigException` | `\InvalidArgumentException` | a provider not configured, a factory not installed, a required option missing or empty, an audience factory given no HTTP client, Google credentials missing or unreadable, Google reviews with neither its key nor the owner's access, a `Money` in no currency or of no amount |
| `NotSupportedException` | `\LogicException` | a provider asked for what it cannot do: a dimension, a filter or a metric it does not report, dimensions it cannot combine; a typed getter of the registry for a contract it does not give as configured; a `reply()` or `invite()` without the owner's or business user's access. Thrown before any call |
| `ProviderException` | `\RuntimeException` | the platform answered, and not with what was asked: an error status, a body that is not JSON, an error inside a 200 (Matomo), a GraphQL `errors` member, no such account or listing, a place it does not know, a write link it does not give, no token from Google |
| `InvalidKeyException` | `ProviderException` | the platform refused the credentials: 401 or 403, Matomo's "access"/"token" error, a refresh token or client credentials refused, Google's Places without an `api_key` |
| `NotFoundException` | `ProviderException` | nothing under that identifier: 404 (a site or property that does not exist, or not this account's), from `JsonApi` |
| `UnreachableException` | `\RuntimeException` | the platform could not be reached: a network error, a timeout; for a review platform also an HTTP status of 500 or more, or 429 (it said nothing about the request) |

Two changes from the former families: `UnreachableException` is **not** a `ProviderException`
any more (omnireview's was) - nothing was answered, so code that caught `ProviderException` to
show nothing of a review platform down catches `UnreachableException` too; and
`InvalidConfigException` is an `\InvalidArgumentException` everywhere (omnireview's was a
`\LogicException`): `catch (\LogicException)` still catches it, `\InvalidArgumentException` being
one.

## What an exception carries

`ProviderException` (and so `InvalidKeyException`, `NotFoundException`) and
`UnreachableException` have two readonly properties beside the message:

| Property | |
|---|---|
| `provider` | who answered, when the thrower names it: the provider's title (`Plausible`, `GitHub`) for `answered()`, the platform's name (`google`, `tripadvisor`, `trustpilot`) for `from()`; `null` otherwise |
| `providerCode` | the platform's own code, when it gave one: the HTTP status, Google's `status` (`NOT_FOUND`, `PERMISSION_DENIED`), Tripadvisor's `error.code` |

They are built two ways, and the message tells which:

| Constructor | Message | Code |
|---|---|---|
| `ProviderException::answered(string $provider, string $what, int\|string $status, ?string $message = null)` | `<Provider> answered <status> for <what>.` or `...: <message>` | the status when it is a number, else 0 |
| `ProviderException::from(string $provider, string $message, ?string $providerCode = null, ?\Throwable $previous = null)` | `[<provider>] <message>` | 0 |
| `InvalidKeyException::refused(string $provider, string $what, int $status, ?string $message = null)` | `<Provider> refused the credentials (<status>) for <what>.` or `...: <message>` | the status |
| `NotFoundException::of(string $provider, string $what, ?string $message = null)` | `<Provider> has no <what>.` or `...: <message>` | 404 |
| `UnreachableException::from(string $provider, string $message, ?string $providerCode = null, ?\Throwable $previous = null)` | `[<provider>] <message>` | 0 |

`answered()`, `refused()` and `of()` are the audience's and the funding's (`JsonApi`, `Json`),
`from()` the review platforms' (`Answer`): their messages stay as they were before the merge. Any
`ProviderException` subclass's `from()` and `answered()` give that subclass. The standard
constructor (`new ProviderException($message, $code, $previous)`) works too; a provider package
may throw it, `provider` and `providerCode` then `null`.

`NotSupportedException` has three constructors:

| Constructor | Message |
|---|---|
| `question(ProviderInterface $provider, string $name, string $question)` (the registry's typed getters) | `The "<name>" provider (<title>) does not <question>; it gives: <contracts>.` |
| `operation(string $provider, string $operation, string $why = '')` (a review provider without the access) | `The "<provider>" provider does not <operation>: <why>.` |
| `capability(object $provider, string $name, string $capability)` (kept for providers that use it) | `The "<name>" provider (<class>) does not <capability>.` |

## The messages

Audience, seen in the [harness](harness.md) on 2026-10-26 (Plausible's provider on recorded
answers, Bing's on a host that does not exist), and the registry's on 2026-11-10:

```
401       InvalidKeyException (code 401): Plausible refused the credentials (401) for the query: Invalid API key or site ID
404       NotFoundException (code 404): Plausible has no the query: not found
429       ProviderException (code 429): Plausible answered 429 for the query: Too many requests
500 html  ProviderException (code 500): Plausible answered 500 for the query.
200 html  ProviderException (code 200): Plausible answered 200 for the query: an answer that is not JSON
network   UnreachableException: Bing Webmaster could not be reached for GetQueryStats (GET /json/GetQueryStats): Could not resolve host: bing.invalid.
query     InvalidArgumentException: The period ends (2026-10-01) before it starts (2026-10-02).
query     InvalidArgumentException: A report has at least one row: limit 0.
query     InvalidArgumentException: No "city" dimension to filter on.
config    InvalidConfigException: The "plausible" provider needs: api_key.
config    InvalidConfigException: The "plausible" provider needs an HTTP client: give one to PlausibleFactory.
config    InvalidConfigException: A Google API needs a service account's key or an access token.
config    InvalidConfigException: The credentials are not a service account's JSON key (client_email, private_key), nor the path of one.
registry  InvalidConfigException: No "y" provider; configured: x.
registry  InvalidConfigException: No "matomo" factory for the "x" provider; installed: none.
registry  NotSupportedException: The "stats" provider (Matomo) does not report search performance; it gives: audience.
```

The tool's own message is added after a colon when the answer carries one
([Concepts](concepts.md#jsonapi-audience)).

Reviews, by thrower:

| Where | Message |
|---|---|
| `Answer::send()` | `[<provider>] No answer: <transport error, its secrets masked>` / `[<provider>] HTTP <status>: <first 200 characters of the body>` (an `UnreachableException`; `providerCode` the status) |
| `Answer::json()` | `[<provider>] HTTP <status>, not a JSON object: <body>` |
| the providers | `[google] HTTP 404, NOT_FOUND: ...`, `[tripadvisor] The location gives no write_review link.`, `[trustpilot] The API key and secret were refused for a token.` |
| `GoogleReviewsFactory` | `The "google-reviews" provider needs an api_key (Places), or the owner's client_id, client_secret and refresh_token (Business Profile).` |
| `NotSupportedException::question()` | `The "trustpilot" provider (Trustpilot) does not give replying to a review; it gives: rating, reviews, writeUrl.` |
| `Registry::terms()` | `The "liberapay" provider (Liberapay) does not give terms of display; it gives: profile.` |

Funding, as the providers write them (run on recorded answers on 2026-10-26; the registry's on
2026-11-10):

```
The "github-sponsors" provider needs: token.
GitHub answered 401 for the listing of x.
GitHub answered Could not resolve to a RepositoryOwner with the login of 'nobody-here-0'. for the listing of x.
GitHub answered no GitHub Sponsors listing for the listing of x.
GitHub answered something that is not JSON for the listing of x.
Liberapay could not be reached for the account x: Connection timed out
The "liberapay" provider (Liberapay) does not list sponsors; it gives: profile.
```

Open Collective writes `Open Collective answered 429 for the backers of webpack.`, `... answered no
such collective for the collective x.`; Liberapay `Liberapay answered 404 for the account x.`. A
funding platform's status is not told apart further: a 401 (a bad GitHub token) and a 429 (a
rate limit) are both a `ProviderException`, the status in its message and its code.

## What is not an exception

- A platform that gives fewer reviews, rows or sponsors than asked, or none: a shorter list.
- A field the platform leaves out: `null` in the model (`0` for a review's score or count), a
  metric left out of a row (never zero).
- A date the platform writes in a form PHP cannot read: `null`.
- In Twig, `UnreachableException` from `omnipulse_rating()`, `omnipulse_reviews()` and
  `omnipulse_write_url()`: `null`, `[]`, `null` ([Symfony and Twig](symfony.md#a-platform-that-does-not-answer)).

The HTTP client's own exceptions never reach the application: the helpers make them
`UnreachableException`s. PHP's `TypeError` comes from injecting, in Symfony, a provider under an
interface it does not implement ([Symfony](symfony.md#services)).

## No secret in a failure

The three HTTP helpers keep the request's secrets out of what they throw. An
`UnreachableException` from `JsonApi` names the method and the path called, and the HTTP client's
reason without the address it quotes (`JsonApi::reason()`): never the query string, where Bing's
API key travels; one from `Answer` or `Json` quotes the client's message with the URL's key
parameters, a password in its user part and every secret of the request's options masked
(`api_key=***`, `Http\Redaction::text()`). In all three the previous exception is a copy of the
client's (`Redaction::copy()`: the same class and code, the message masked, its own previous copied
the same way), and no trace of the chain keeps its arguments, where the request's query, headers,
body and bearer token stood: a log that writes the whole chain, arguments included
(`zend.exception_ignore_args` off), holds no key. Nor does an error answered (a refused key,
nothing there, a server error, a body that is not JSON): every exception `JsonApi::request()` and
`Json::request()` throw leaves its trace's arguments out, as does `Answer::send()` for a 5xx or a
429.

## What to catch where

| Where | Catch |
|---|---|
| a dashboard panel, a page that shows reviews or sponsors | `OmnipulseException`, or `UnreachableException` and `ProviderException`: show "unavailable" or nothing, and the last list cached where the platform's terms allow it; log the message |
| a screen that offers several providers | check `capabilities()` first (`contracts()`, `gives()`); `NotSupportedException` is then a programming error |
| a scheduled export or refresh | `UnreachableException` and a `ProviderException` of code 429 or 5xx: retry later; `InvalidKeyException`, or a funding `ProviderException` of code 401: alert someone, the key was revoked or lost its access |
| building the registry, a deploy | `InvalidConfigException`: a configuration error, to fix, not to retry |
| a form that builds an audience query | `Omnipulse\Exception\InvalidArgumentException`: the period or the limit typed is wrong |

```php
use Omnipulse\Exception\InvalidKeyException;
use Omnipulse\Exception\ProviderException;
use Omnipulse\Exception\UnreachableException;

try {
    $rating = $registry->rating('trustpilot')->rating($place);
} catch (UnreachableException) {
    $rating = null;                                     // show nothing: the platform is down or asked to slow down
} catch (InvalidKeyException $e) {
    $logger->critical($e->getMessage());                // the configuration is wrong
    $rating = null;
} catch (ProviderException $e) {
    $logger->error($e->getMessage(), ['code' => $e->providerCode]);   // an unknown place
    $rating = null;
}
```

A platform's message is the platform's, untranslated: show a message of the application's, keep
the platform's for the logs.
