---
title: Models
order: 25
---

# Models

The reviews' and the funding's models, all `final readonly` classes in `Omnipulse\Model`, built
with named arguments. The audience's (`ReachQuery`, `Dimension`, `Metric`, `Row`, `Report`) are in
[Concepts](concepts.md#the-audiences-query), with the query they answer.

- Reviews: [Place](#place), [Rating](#rating), [Review](#review), [Reply](#reply),
  [Invitation](#invitation), [Terms](#terms).
- Funding: [Money](#money), [Period](#period), [Profile](#profile),
  [Income and Goal](#income-and-goal), [Sponsor](#sponsor), [Tier](#tier).

## Reviews

### Place

The establishment on one platform, as the application keeps it (every platform allows keeping its
identifier).

| Property | Type | Default | |
|---|---|---|---|
| `id` | string | required | the platform's identifier: a Google place ID (`ChIJ...`), a Tripadvisor location ID (`1234567`), a Trustpilot business unit ID |
| `name` | ?string | `null` | the establishment's name; Google uses it in the Maps search link of its owner's side |
| `owner` | ?string | `null` | a second identifier the owner's side needs: Google Business Profile's `accounts/<a>/locations/<l>` |

### Rating

| Property | Type | Default | |
|---|---|---|---|
| `platform` | string | required | the platform: `google`, `tripadvisor`, `trustpilot` (the platform's name, not the factory's: `google-reviews` is Google's) |
| `value` | float | required | the score, on the platform's scale |
| `count` | int | required | the reviews (or ratings) it rests on |
| `scale` | int | `5` | the scale's top (5 on every platform today) |
| `url` | ?string | `null` | the establishment's page on the platform: link it |
| `image` | ?string | `null` | the platform's own picture of the score (Tripadvisor's bubbles), shown from its address |
| `fetchedAt` | DateTimeImmutable | now | when it was read |

`outOfFive(): float` - the score on 5, rounded to a tenth (`8.6` on 10 is `4.3`).

### Review

| Property | Type | Default | |
|---|---|---|---|
| `platform` | string | required | the platform: `google`, `tripadvisor`, `trustpilot` (the platform's name, not the factory's: `google-reviews` is Google's) |
| `id` | string | required | the platform's identifier: what a reply needs |
| `rating` | float | required | on the platform's scale |
| `text` | ?string | `null` | as its author wrote it, or the platform's translation |
| `title` | ?string | `null` | Tripadvisor's and Trustpilot's |
| `language` | ?string | `null` | the text's language code |
| `author` | ?string | `null` | as the platform names them |
| `authorUrl` | ?string | `null` | their profile on the platform |
| `authorPhoto` | ?string | `null` | their picture's address |
| `publishedAt` | ?DateTimeImmutable | `null` | |
| `url` | ?string | `null` | the review on the platform, or the establishment's page when the platform gives no review an address of its own: link it |
| `reply` | ?Reply | `null` | the establishment's answer |
| `scale` | int | `5` | |
| `translated` | bool | `false` | the text is the platform's translation |
| `image` | ?string | `null` | the platform's picture of this review's score |

A review's text is the platform's: show it escaped, within quotation marks, unaltered - shortened
only with a link to the whole.

### Reply

| Property | Type | Default | |
|---|---|---|---|
| `text` | string | required | the establishment's answer |
| `at` | ?DateTimeImmutable | `null` | when it was published or last changed |

### Invitation

A customer to invite.

| Property | Type | Default | |
|---|---|---|---|
| `email` | string | required | |
| `name` | string | required | |
| `reference` | ?string | `null` | what they bought: an order number |
| `locale` | string | `fr-FR` | the invitation's language |
| `sendAt` | ?DateTimeImmutable | `null` | when the platform sends it; `null`: not sent to the platform, which then sends at its own default time |
| `redirectUrl` | ?string | `null` | where the customer lands after writing their review |

### Terms

A platform's rules for what it gives, declared by its package from the platform's documentation and
read through `TermsInterface::terms()`, or `Registry::terms($name)`. Not to be confused with
`Capabilities::$terms`, the addresses of the terms that apply to any provider.

| Property | Type | Default | |
|---|---|---|---|
| `cacheFor` | ?int | `0` | seconds what it gives may be kept before asking again: `0` never, `null` "as the platform's caching policy allows" (not published, so treated as never) |
| `maxReviews` | ?int | `null` | the reviews one call gives at most |
| `attribution` | string | `''` | what must be shown with it: the platform's name or logo |
| `authors` | bool | `true` | each review shown with its author as the platform names them |
| `link` | bool | `true` | a link back to the platform shown with it |
| `noIndex` | bool | `false` | pages that show it are kept out of search engines |
| `rules` | list of strings | `[]` | the other rules, in words |
| `source` | ?string | `null` | where they are written |

`allowsCaching(int $seconds): bool` - whether it may be kept `$seconds`: `false` when `cacheFor`
is `null`, else `$seconds <= cacheFor`.

What each provider declares:

| | `cacheFor` | `maxReviews` | `attribution` | `noIndex` | `source` |
|---|---|---|---|---|---|
| Google | `0` | `5` | `Google Maps` | `false` | developers.google.com/maps/documentation/places/web-service/policies |
| Tripadvisor | `null` | `5` | `Tripadvisor` | `true` | tripadvisor-content-api.readme.io/reference/display-requirements |
| Trustpilot | `86400` | `100` | `Trustpilot` | `false` | developers.trustpilot.com/ds-caching-best-practices/ |

Every provider declares `authors: true` and `link: true`; their `rules` are listed in their
manuals.

## Funding

### Money

`Omnipulse\Model\Money` (readonly): `int $cents`, hundredths of the currency's unit, as the
platforms count them (GitHub's `monthlyPriceInCents`, Open Collective's `valueInCents`,
Liberapay's `"636.28"`), and `string $currency`, ISO 4217, upper-cased.

| Method | |
|---|---|
| `__construct(int $cents, string $currency)` | `InvalidConfigException` when the currency is not three letters |
| `fromDecimal(string\|int\|float $amount, string $currency): self` | from `"636.28"`, `636.28`, `5`; `InvalidConfigException` when it is not numeric |
| `decimal(): string` | `"636.28"`, with a `-` for a negative amount |
| `multiply(float $factor): self` | rounded to the cent |
| `toArray()` | `{cents, currency, decimal}` |

Every currency is counted in hundredths, whatever its minor unit: the providers' own amounts are.

### Period

`Omnipulse\Model\Period` (string enum): `Once` (`once`), `Week` (`week`), `Month` (`month`),
`Year` (`year`). `perMonth(): ?float` is how many of this period make a month: 52/12 for a week
(52 weeks a year), 1 for a month, 1/12 for a year, `null` for once.

### Profile

`Omnipulse\Model\Profile` (readonly), the account as it shows itself; each field `null` when the
provider does not say:

| Property | Type | |
|---|---|---|
| `provider` | string | the factory's name |
| `handle` | string | the account's login or slug, as the platform writes it |
| `name` | `?string` | its display name |
| `description` | `?string` | its short description, or summary |
| `url` | `?string` | its funding page |
| `avatarUrl` | `?string` | its picture |
| `sponsors` | `?int` | how many fund it (private ones included where the platform counts them) |
| `income` | `?Income` | what it receives, by period |
| `goal` | `?Goal` | what it aims for |

`toArray()` gives the same, nested models as arrays.

### Income and Goal

`Income` (readonly): `Money $amount` and `Period $period`. `monthly(): ?Money` is the same
income by the month (`null` for a one-time amount): 636.28 a week is 2757.21 a month.

`Goal` (readonly), what an account aims for - an amount, or a number of sponsors:

| Property | Type | |
|---|---|---|
| `title` | `?string` | GitHub's goal title |
| `description` | `?string` | |
| `amount` | `?Money` | an amount goal (Liberapay, GitHub's `MONTHLY_SPONSORSHIP_AMOUNT`) |
| `period` | `?Period` | the amount's period |
| `sponsors` | `?int` | a number of sponsors (GitHub's `TOTAL_SPONSORS_COUNT`) |
| `percent` | `?int` | how far it has got, when the provider says (or computes) |

### Sponsor

`Omnipulse\Model\Sponsor` (readonly), someone who funds the account, as the platform shows them
publicly:

| Property | Type | Default | |
|---|---|---|---|
| `handle` | string | - | their login or slug |
| `name` | `?string` | `null` | |
| `avatarUrl` | `?string` | `null` | |
| `url` | `?string` | `null` | their page |
| `kind` | string | `Sponsor::USER` | `user`, `organization` or `other` (the constants `USER`, `ORGANIZATION`, `OTHER`) |
| `since` | `?\DateTimeImmutable` | `null` | since when (Open Collective) |
| `tier` | `?string` | `null` | their tier's name (Open Collective) |
| `total` | `?Money` | `null` | what they gave in all (Open Collective) |

`label()` is the name, else the handle. Sponsors' names and pictures are personal data: show only
what the platform shows publicly.

### Tier

`Omnipulse\Model\Tier` (readonly), one way to give:

| Property | Type | Default | |
|---|---|---|---|
| `name` | string | - | "$5 a month", "Backer" |
| `price` | `?Money` | `null` | `null` for an amount left to the giver |
| `period` | `Period` | `Month` | |
| `id` | `?string` | `null` | the platform's id |
| `description` | `?string` | `null` | |
| `customAmount` | bool | `false` | the giver chooses the amount |
| `minimum` | `?Money` | `null` | the least accepted (Open Collective) |

