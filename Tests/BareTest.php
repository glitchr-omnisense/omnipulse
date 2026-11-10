<?php

namespace Omnipulse\Tests;

use Omnipulse\Registry;
use Omnipulse\Support\Data;
use Omnipulse\Testing\LoadCheck;
use PHPUnit\Framework\TestCase;

/**
 * omnisense/omnipulse with no framework: in PHP processes of their own
 * (this one has loaded PHPUnit), the harness's bare script asks search
 * and audience reports, a rating, reviews and the terms of a review
 * platform, a funded account's profile, sponsors and tiers through one
 * Registry, and calls a recorded JSON API - and every class of the package
 * but its framework bridges (Bridge/) is loaded. Nothing may come from outside the
 * package but what it requires: Symfony's HTTP client contracts
 * (interfaces only) - no framework, no HTTP client, no application, even
 * in the harness where they are installed beside it.
 */
final class BareTest extends TestCase
{
    private const ALLOWED = ['/vendor/symfony/http-client-contracts/', '/vendor/symfony/service-contracts/'];

    public function testEveryFamilyAskedByHandLoadsNothingButOmnipulseAndWhatItRequires(): void
    {
        $autoload = self::autoload();
        $process = proc_open([\PHP_BINARY, __DIR__.'/../docker/harness/bin/bare'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIPULSE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), $err.$out);
        $report = Data::of(json_decode($out, true));

        self::assertSame(['query' => 'atelier poterie strasbourg'], $report->raw('search.rows.0.dimensions'));
        self::assertSame(2, \count((array) $report->raw('search.rows')), 'the September row is outside the period');
        self::assertSame(['clicks' => 15, 'impressions' => 750], $report->raw('searchTotals'));
        self::assertSame('https://atelier.example.org/', $report->string('search.site'));
        self::assertSame([['/', 260], ['/ateliers', 110]], $report->raw('entries'), 'filtered on the source, projected on the entry page');
        self::assertSame(['2026-09-26', '2026-10-23'], $report->raw('lastDays'));
        self::assertSame(['/ateliers', 120], $report->raw('api'));
        self::assertSame('Example refused the credentials (401) for it: Invalid API key', $report->string('refused'));

        self::assertSame([8.6, 10, 42, 4.3, 'https://reviews.example/p1'], $report->raw('rating'));
        self::assertSame([[5, 'Camille R.', '2026-09-30', 'https://reviews.example/r/1']], $report->raw('reviews'), 'every review dated and linked');
        self::assertSame('https://reviews.example/write/p1', $report->string('writeUrl'));
        self::assertSame(['cacheFor' => 0, 'maxReviews' => 5, 'attribution' => 'Fixed Reviews'], $report->raw('terms'));
        self::assertSame('[fixed-reviews] No answer.', $report->string('down'));

        self::assertSame('Glitch Art', $report->string('profile.name'));
        self::assertSame(['amount' => ['cents' => 1200, 'currency' => 'EUR', 'decimal' => '12.00'], 'period' => 'week'], $report->raw('profile.income'));
        self::assertSame('52.00', $report->string('monthly'), '12 a week, 52 weeks a year: 52 a month');
        self::assertSame('100.00', $report->string('profile.goal.amount.decimal'));
        self::assertSame(['Ada', 'ACME'], $report->raw('sponsors'));
        self::assertSame([['Backer', '5.00', 'month'], ['Once', null, 'once']], $report->raw('tiers'));
        self::assertSame('No "missing" factory for the "nowhere" provider; installed: fixed, fixed-reviews, array.', $report->string('missing'));
        self::assertSame([42, '2016-10-15', null], $report->raw('read'));

        self::assertSame(['site' => ['searchPerformance', 'audience'], 'reviews' => ['rating', 'reviews', 'writeUrl'], 'funding' => ['profile', 'sponsors', 'tiers']], $report->raw('contracts'));
        self::assertSame(['site' => ['audience'], 'reviews' => ['reviews'], 'funding' => ['funding']], $report->raw('families'));

        $symbols = array_values(array_filter((array) $report->raw('loaded.symbols'), 'is_string'));
        self::assertContains('Omnipulse\Registry', $symbols);
        self::assertSame([], LoadCheck::foreign($symbols), 'no class of a framework, an HTTP client or an application');
        $eager = array_map('dirname', array_values(array_filter((array) $report->raw('loaded.eager'), 'is_string')));
        $files = array_values(array_filter((array) $report->raw('loaded.files'), static fn ($f) => \is_string($f) && !\in_array(\dirname($f), $eager, true)));
        self::assertSame([], LoadCheck::outside(array_values(array_filter($files, 'is_string')), \dirname(__DIR__), self::ALLOWED), 'no file but the package and what it requires');
    }

    public function testEveryClassLoadsWithTheRequiredPackagesAlone(): void
    {
        $report = LoadCheck::run(\dirname(__DIR__), 'Omnipulse\\', self::autoload());

        self::assertGreaterThan(20, \count($report['loaded']));
        self::assertContains('Omnipulse\Testing\FixedReach', $report['loaded']);
        self::assertContains('Omnipulse\Testing\FixedReviews', $report['loaded']);
        self::assertContains('Omnipulse\Http\Json', $report['loaded']);
        self::assertSame([], preg_grep('~^Omnipulse\\\\Bridge\\\\~', $report['loaded']), 'the bridges are not loaded: they need what the package only suggests');
        self::assertSame([], $report['foreign']);
        self::assertSame([], LoadCheck::outside($report['files'], \dirname(__DIR__), self::ALLOWED));
    }

    /** The check is not blind: an HTTP client's class, once loaded, is seen. */
    public function testTheReportSeesWhatIsLoaded(): void
    {
        if (!class_exists(\Symfony\Component\HttpClient\MockHttpClient::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
        $report = LoadCheck::run(\dirname(__DIR__), 'Omnipulse\\', self::autoload(), [\Symfony\Component\HttpClient\MockHttpClient::class]);

        self::assertContains(\Symfony\Component\HttpClient\MockHttpClient::class, $report['foreign']);
        self::assertNotEmpty(preg_grep('~/symfony/http-client/~', LoadCheck::outside($report['files'], \dirname(__DIR__), self::ALLOWED)));
    }

    private static function autoload(): string
    {
        return LoadCheck::autoload(Registry::class, self::class, \dirname(__DIR__)) ?? self::markTestSkipped('omnipulse is installed in an application here, or no autoloader knows its tests: the bare check is the package\'s own.');
    }
}
