---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnipulse provider yet, and
the package's bundle is Symfony's. The registry is plain PHP: a service provider of the
application's builds it once, from a config file, with a `symfony/http-client` client (the
factories take Symfony's HTTP client contracts; Laravel's own HTTP client is Guzzle's, which they
do not take). One registry for the three families.

```php
// config/omnipulse.php
return [
    'providers' => [
        'google' => ['factory' => 'search-console', 'options' => [
            'site' => 'sc-domain:example.org',
            'credentials' => storage_path('app/secrets/search-console.json'),
        ]],
        'stats' => ['factory' => 'plausible', 'options' => [
            'api_key' => env('PLAUSIBLE_API_KEY'),
            'site' => 'example.org',
        ]],
        'trustpilot' => ['factory' => 'trustpilot', 'options' => [
            'api_key' => env('TRUSTPILOT_API_KEY'),
        ]],
        'liberapay' => ['factory' => 'liberapay', 'options' => [
            'account' => 'Liberapay',
        ]],
    ],
];
```

```php
// app/Providers/OmnipulseServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnipulse\Liberapay\LiberapayFactory;
use Omnipulse\Plausible\PlausibleFactory;
use Omnipulse\Registry;
use Omnipulse\SearchConsole\SearchConsoleFactory;
use Omnipulse\Trustpilot\TrustpilotFactory;
use Symfony\Component\HttpClient\HttpClient;

final class OmnipulseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, function (): Registry {
            $http = HttpClient::create(['timeout' => 30]);

            return new Registry([
                new SearchConsoleFactory($http),
                new PlausibleFactory($http),
                new TrustpilotFactory($http),
                new LiberapayFactory($http),
            ], config('omnipulse.providers'));
        });
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the
registry where it is needed:

```php
use Illuminate\Support\Facades\Cache;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Registry;

final class AudienceController
{
    public function entries(Registry $reach)
    {
        return Cache::remember('reach.entries.28', now()->addHours(6), fn () => $reach->audience('stats')
            ->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]))
            ->toArray());
    }

    public function rating(Registry $reach)
    {
        $place = new \Omnipulse\Model\Place(config('services.trustpilot.business_unit'));
        $terms = $reach->terms('trustpilot');                       // kept a day at most, credited to Trustpilot

        return Cache::remember('reviews.rating', min(3600, (int) $terms->cacheFor), fn () => [
            'rating' => $reach->rating('trustpilot')->rating($place),
            'attribution' => $terms->attribution,
        ]);
    }
}
```

Keep a review platform's answers no longer than its `Terms::$cacheFor` (`0` or `null`: not at all
- `Cache::remember` with 0 seconds keeps nothing), and show them with their origin, date and link
([Reviews](usage-reviews.md#the-terms)); the Twig widget is Symfony's bundle's, so a Blade view
writes its own markup from the models.

List in the provider every factory the application uses: a hand-built registry knows only the
factories it is given. Configuration caching (`php artisan config:cache`) keeps working: the
options are plain arrays read through `config()`. This page was written from the registry's API;
it was not run in a Laravel application.
