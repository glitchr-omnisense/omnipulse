<?php

namespace Omnipulse\Tests;

use Omnipulse\Bridge\Symfony\OmnipulseBundle;
use Omnipulse\Registry;
use Omnipulse\Support\Data;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The review platforms outside Symfony: the harness's reviews script
 * (docker/harness/bin/reviews) run in a PHP process of its own - this one
 * has loaded the bundle's tests - builds the registry by hand and asks each
 * review package installed a place's rating, reviews and link, the
 * platforms answering from their recorded answers, then reports every class
 * and file PHP loaded on the way. None may be a framework's; of Symfony,
 * only the HTTP client (the recorded answers are a MockHttpClient's).
 */
final class ReviewPlatformsTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Form|Validator|Routing)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine(?!\\\\Common\\\\Lexer)|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|form|validator|routing|[a-z-]*bundle|[a-z-]*bridge)|doctrine/(?!lexer/)[a-z-]+|twig)/~';

    private static ?Data $report = null;

    private static function report(): Data
    {
        if (null === self::$report) {
            [$status, self::$report] = self::php([__DIR__.'/../docker/harness/bin/reviews', '--json']);
            self::assertSame(0, $status);
        }

        return self::$report;
    }

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        $report = self::report();

        self::assertFalse($report->bool('live'));
        $symbols = self::strings($report->raw('symbols'));
        self::assertContains(Registry::class, $symbols, 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $symfony = array_values(array_unique(array_map(static fn (string $s) => implode('\\', \array_slice(explode('\\', $s), 0, 3)), self::strings(preg_grep('~^Symfony\\\\(?!Polyfill)~', $symbols)))));
        self::assertSame([], array_diff($symfony, ['Symfony\\Component\\HttpClient', 'Symfony\\Contracts\\HttpClient', 'Symfony\\Contracts\\Service']), 'of Symfony, libraries alone');
        foreach (self::installed() as $factory) {
            self::assertContains($factory, $symbols, 'every review package installed, its factory built');
        }
        self::assertCount(\count(self::installed()), (array) $report->raw('platforms'));
    }

    public function testEachPlatformGivesARatingReviewsAndTheirLinks(): void
    {
        $report = self::report();
        if ([] === (array) $report->raw('runs')) {
            self::markTestSkipped('No review package is installed beside this one (they are in the harness).');
        }

        $expected = ['google-reviews' => [4.6, 5, 128], 'tripadvisor' => [4.5, 5, 312], 'trustpilot' => [4.4, 5, 469]];
        foreach ($expected as $name => $rating) {
            $run = $report->data('runs.'.$name);
            if ([] === $run->values) {
                continue;
            }
            self::assertSame($rating, \array_slice((array) $run->raw('rating'), 0, 3), $name);
            self::assertNotNull($run->string('rating.3'), $name.': the rating links to its origin');
            self::assertNotEmpty($run->list('reviews'), $name);
            foreach ($run->list('reviews') as $review) {
                self::assertNotNull($review->string('2'), $name.': every review is dated');
            }
            self::assertStringStartsWith('https://', (string) $run->string('writeUrl'), $name);
        }
        if ($report->has('platforms.google-reviews')) {
            self::assertSame(0, $report->int('platforms.google-reviews.terms.cacheFor'), 'Google: nothing kept but the place ID');
        }
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNIPULSE_AUTOLOAD"); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => get_included_files()]);', '--', OmnipulseBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /** @return list<string> the factories of the review packages installed beside this one */
    private static function installed(): array
    {
        $platforms = (array) require __DIR__.'/../docker/harness/config/review-platforms.php';
        $factories = [];
        foreach ((array) require __DIR__.'/../docker/harness/plugins.php' as $slug => $plugin) {
            if (isset($platforms[$slug]) && \is_array($plugin) && \is_string($plugin['factory'] ?? null) && class_exists($plugin['factory'])) {
                $factories[] = $plugin['factory'];
            }
        }

        return $factories;
    }

    /** @return list<string> the classes, interfaces, traits and files of a framework among those loaded */
    private static function framework(Data $report): array
    {
        // Composer includes every installed package's "files" (installed for the
        // bridges' tests) before anything runs: loaded by the autoloader, not by Omnipulse.
        $eager = require \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName()).'/autoload_files.php';
        $eager = array_map('realpath', \is_array($eager) ? array_values(array_filter($eager, 'is_string')) : []);
        $files = array_values(array_diff(array_map('realpath', self::strings($report->raw('files'))), $eager));

        return [...self::strings(preg_grep(self::FRAMEWORK, self::strings($report->raw('symbols')))), ...self::strings(preg_grep(self::FRAMEWORK_FILES, self::strings($files)))];
    }

    /** @return list<string> */
    private static function strings(mixed $list): array
    {
        return \is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, Data} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIPULSE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, new Data($report)];
    }
}
