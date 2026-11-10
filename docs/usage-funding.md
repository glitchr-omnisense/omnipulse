---
title: Funding
order: 34
---

# Funding

Who funds a project, on GitHub Sponsors, Open Collective and Liberapay. Every example takes a
`Registry` built as in [Installation](installation.md#plain-php-funding); the models are in
[Models](models.md#funding).

## Profiles

```php
$profile = $registry->profile('liberapay')->profile();          // the configured account
$other = $registry->profile('liberapay')->profile('another');   // another one, same provider

echo $profile->name ?? $profile->handle, ': ', $profile->sponsors, " patrons\n";
if ($monthly = $profile->income?->monthly()) {
    echo $monthly->decimal(), ' ', $monthly->currency, " a month\n";
}
if ($goal = $profile->goal) {
    echo $goal->title ?? $goal->amount?->decimal(), ', ', $goal->percent ?? '?', " %\n";
}
```

What each provider fills:

| `Profile` | GitHub Sponsors | Open Collective | Liberapay |
|---|---|---|---|
| `handle` | the login | the slug | the username |
| `name` | the user's or organization's name | the collective's name | the display name, else the username |
| `description` | the listing's short description | the description | the summary in `language`, else the first one |
| `url` | the listing's page, else the account's | `https://opencollective.com/<slug>` | `<base_url>/<username>` |
| `avatarUrl` | the account's | the logo (`imageUrl`) | the avatar |
| `sponsors` | `sponsors.totalCount` (private ones included) | the backers (`members(role: BACKER).totalCount`) | `npatrons` |
| `income` | `null`: GitHub shows it to the account alone | the yearly budget Open Collective estimates, by the year | `receiving`, by the week; `null` when the account hides it |
| `goal` | the active goal: an amount in dollars a month or a number of sponsors, its title, description and `percentComplete` | `null`: the API gives none in a stable form | `goal`, by the week, its percent computed from what it receives (rounded down); `null` when hidden |

All three providers on recorded answers (run on 2026-10-26 in the [harness](harness.md), with the
packages' own fixtures, before the merge into omnipulse):

```
GitHub Sponsors (profile sponsors tiers): Octo Studio, 3 sponsors, goal $500 a month 12%
  sponsor Ada L. (user)
  sponsor ACME (organization)
  tier $5 a month: 5.00 month
  tier $25 one time: 25.00 once, any amount
Open Collective (profile sponsors tiers): Octo Collective, 3 sponsors, 179852.31 USD a year (14987.69 a month)
  sponsor Ada L. (user), Backer
  sponsor ACME (organization)
  tier Backer: 10.00 month
  tier Donation: - once, any amount
  tier Yearly sponsor: 1000.00 year
Liberapay (profile): Liberapay, 1782 sponsors, 636.28 EUR a week (2757.21 a month), goal 1000.00 63%
```

## Sponsors

```php
foreach ($registry->sponsors('github')->sponsors(limit: 24) as $sponsor) {
    printf('<a href="%s"><img src="%s" alt="%s"></a>', $sponsor->url, $sponsor->avatarUrl, $sponsor->label());
}
```

With no limit every sponsor is read, page after page (100 a page on both platforms); with a limit,
no more pages than needed are asked for, each page no bigger than what is left.

- GitHub Sponsors lists the public sponsors only (a private one is counted, never listed); users
  and organizations; no tier, since or total (GitHub shows them to the sponsor and the maintainer
  alone).
- Open Collective lists the members with the `BACKER` role, with since when, their tier and their
  total given; individuals are `user`, organizations `organization`, other collectives `other`.
- Liberapay shows no patron by name: `$registry->sponsors('liberapay')` throws
  `NotSupportedException` (`The "liberapay" provider (Liberapay) does not list sponsors; it gives:
  profile.`).

## Tiers

```php
foreach ($registry->tiers('oc')->tiers() as $tier) {
    echo $tier->name, ': ', $tier->price?->decimal() ?? 'any amount', ' ', $tier->period->value, "\n";
}
```

- GitHub: monthly or one-time (`isOneTime`), in US cents (`monthlyPriceInCents`), custom amounts
  marked (`isCustomAmount`); the first 100 tiers.
- Open Collective: `MONTHLY` month, `YEARLY` year, `ONETIME` once (anything else month); a
  `FLEXIBLE` amount type or frequency is a custom amount, with its minimum.
- Liberapay has no tiers: `NotSupportedException`.

## Several platforms

```php
use Omnipulse\SponsorsInterface;

$all = [];
foreach ($registry->names() as $name) {
    $provider = $registry->get($name);
    if ($provider instanceof SponsorsInterface) {
        array_push($all, ...$provider->sponsors());
    }
}
```

Check the contract (`instanceof`, or `capabilities()->sponsors`) before asking: the registry's
typed getters throw for a provider that lacks it. Totals across platforms need one period:
`Income::monthly()` brings a weekly or yearly income to a month.

## Caching

Omnipulse keeps nothing: every call asks the platform, except that GitHub Sponsors and Open
Collective keep the account's first answer (profile and tiers come from the same query) in the
provider object for its lifetime. Keep what you show in the application's cache, as arrays
(`toArray()`):

| Platform | Limit |
|---|---|
| GitHub | 5,000 GraphQL points an hour per token; the profile and the tiers cost one query, each page of sponsors one more |
| Open Collective | 10 calls a minute without a token; the same costs as GitHub |
| Liberapay | none published; a non-profit run on donations: one call per profile, and its data changes weekly |
