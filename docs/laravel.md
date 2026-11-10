---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnipulse provider yet. The
registry is plain PHP: a service provider of the application's builds it once, from a config file,
with a `symfony/http-client` client (the factories take Symfony's HTTP client contracts; Laravel's
own HTTP client is Guzzle's, which they do not take).

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
    ],
];
```

```php
// app/Providers/OmnipulseServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnipulse\Plausible\PlausibleFactory;
use Omnipulse\Registry;
use Omnipulse\SearchConsole\SearchConsoleFactory;
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
}
```

List in the provider every factory the application uses: a hand-built registry knows only the
factories it is given. Configuration caching (`php artisan config:cache`) keeps working: the
options are plain arrays read through `config()`. This page was written from the registry's API;
it was not run in a Laravel application.
