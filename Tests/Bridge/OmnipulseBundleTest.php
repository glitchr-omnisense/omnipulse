<?php

namespace Omnipulse\Tests\Bridge;

use Omnipulse\Bridge\Symfony\OmnipulseBundle;
use Omnipulse\Model\Place;
use Omnipulse\RatingInterface;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReviewsFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

final class OmnipulseBundleTest extends TestCase
{
    public function testTheProvidersConfiguredAreBuiltAndInjectableByName(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(FixedReviewsFactory::class)->setAutoconfigured(true);
        $container->register(Restaurant::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension(self::extension(new OmnipulseBundle()));
        $container->loadFromExtension('omnipulse', ['providers' => [
            'platform' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'reviews.example']],
            'other' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'other.example']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertSame(['platform', 'other'], array_keys($registry->all()));
        $restaurant = $container->get(Restaurant::class);
        self::assertInstanceOf(Restaurant::class, $restaurant);
        self::assertSame('https://reviews.example/p1', $restaurant->platform->rating(new Place('p1'))->url);
        self::assertSame('https://other.example/p1', $restaurant->other->rating(new Place('p1'))->url);
    }

    public function testThePackagesInstalledAreRegisteredWithTheApplicationsClient(): void
    {
        if (!class_exists(\Omnipulse\GoogleReviews\GoogleReviewsFactory::class)) {
            self::markTestSkipped('omnipulse/google-reviews is not installed.');
        }
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->registerExtension(self::extension(new OmnipulseBundle()));
        $container->loadFromExtension('omnipulse', ['providers' => ['platform' => ['factory' => 'google-reviews', 'options' => ['api_key' => 'k']]]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertContains('google-reviews', $registry->factories());
        self::assertSame(0, $registry->terms('platform')->cacheFor);
    }

    /**
     * TwigBundle (TwigExtension::getBundleTemplatePaths()) gives @Omnipulse the application's
     * templates/bundles/OmnipulseBundle/ first, then the bundle's getPath().'/templates': the
     * widget must sit there, and no twig.paths entry be prepended, which TwigBundle registers
     * before both and which would win over the override.
     */
    public function testTheWidgetIsWhereTwigBundleLetsAnApplicationOverrideIt(): void
    {
        $bundle = new OmnipulseBundle();
        self::assertFileExists($bundle->getPath().'/templates/reviews.html.twig');

        $container = new ContainerBuilder();
        $container->registerExtension(new class extends \Symfony\Component\DependencyInjection\Extension\Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        });
        $extension = self::extension($bundle);
        $container->registerExtension($extension);
        if ($extension instanceof \Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface) {
            $extension->prepend($container);
        }
        self::assertSame([], array_merge(...array_map(static fn (array $c): array => \is_array($c['paths'] ?? null) ? $c['paths'] : [], $container->getExtensionConfig('twig'))));
    }

    /** One bundle for the three families: an audience provider is configured and injected as a review platform is. */
    public function testAnyFamilysProviderIsInjectableByName(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(\Omnipulse\Testing\FixedReachFactory::class)->setAutoconfigured(true);
        $container->register(FixedReviewsFactory::class)->setAutoconfigured(true);
        $container->register(Site::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension(self::extension(new OmnipulseBundle()));
        $container->loadFromExtension('omnipulse', ['providers' => [
            'stats' => ['factory' => 'fixed', 'options' => ['site' => 'https://atelier.example.org/']],
            'reviews' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'reviews.example']],
        ]]);
        $container->compile();

        $site = $container->get(Site::class);
        self::assertInstanceOf(Site::class, $site);
        self::assertSame(['searchPerformance', 'audience'], $site->stats->capabilities()->contracts());
        self::assertSame('https://reviews.example/write/p1', $site->reviews->writeUrl(new Place('p1')));
    }

    private static function extension(OmnipulseBundle $bundle): \Symfony\Component\DependencyInjection\Extension\ExtensionInterface
    {
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}

final class Site
{
    public function __construct(public readonly \Omnipulse\AudienceInterface $stats, public readonly \Omnipulse\WriteUrlInterface $reviews)
    {
    }
}

final class Restaurant
{
    public function __construct(public readonly RatingInterface $platform, public readonly RatingInterface $other)
    {
    }
}
