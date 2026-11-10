<?php

namespace Omnipulse\Bridge\Symfony;

use Omnipulse\AudienceInterface;
use Omnipulse\Bridge\Twig\OmnipulseExtension;
use Omnipulse\InviteInterface;
use Omnipulse\ProfileInterface;
use Omnipulse\ProviderFactoryInterface;
use Omnipulse\ProviderInterface;
use Omnipulse\RatingInterface;
use Omnipulse\Registry;
use Omnipulse\ReplyInterface;
use Omnipulse\ReviewsInterface;
use Omnipulse\SearchPerformanceInterface;
use Omnipulse\SponsorsInterface;
use Omnipulse\TermsInterface;
use Omnipulse\TiersInterface;
use Omnipulse\WriteUrlInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnipulse in a Symfony application: the provider packages installed
 * (omnipulse/search-console, omnipulse/matomo, omnipulse/google-reviews,
 * omnipulse/liberapay...) registered, the providers built from
 * configuration and injectable by their name, and Twig's review functions
 * with the application's cache:
 *
 *     omnipulse:
 *         providers:
 *             stats: { factory: matomo, options: { url: 'https://stats.example.org', site: '1', token: '%env(MATOMO_TOKEN)%' } }
 *             google: { factory: google-reviews, options: { api_key: '%env(GOOGLE_PLACES_KEY)%' } }
 *             liberapay: { factory: liberapay, options: { account: 'Liberapay' } }
 *         cache:
 *             pool: cache.app      # reviews kept no longer than each platform allows
 *             ttl: 3600
 *
 *     public function __construct(AudienceInterface $stats, RatingInterface $google, ProfileInterface $liberapay) {}
 *     {{ omnipulse_reviews_widget('google', place_id) }}
 *
 * Nothing is built, nor checked, when the container compiles. A factory is
 * autowired, the application's http_client given to it; the funding
 * factories need one (framework.http_client), the others create their own
 * when the application has none.
 */
final class OmnipulseBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnipulse';


    /** Every contract a provider is injectable as, by its name. */
    private const CONTRACTS = [
        ProviderInterface::class, SearchPerformanceInterface::class, AudienceInterface::class,
        TermsInterface::class, RatingInterface::class, ReviewsInterface::class, ReplyInterface::class, InviteInterface::class, WriteUrlInterface::class,
        ProfileInterface::class, SponsorsInterface::class, TiersInterface::class,
    ];

    /**
     * The provider packages this bundle knows, registered when installed (by name: none is required).
     *
     * @return list<string>
     */
    public static function factories(): array
    {
        return [
            'Omnipulse\\SearchConsole\\SearchConsoleFactory',
            'Omnipulse\\BingWebmaster\\BingWebmasterFactory',
            'Omnipulse\\Matomo\\MatomoFactory',
            'Omnipulse\\Plausible\\PlausibleFactory',
            'Omnipulse\\Ga4\\Ga4Factory',
            'Omnipulse\\GoogleReviews\\GoogleReviewsFactory',
            'Omnipulse\\Tripadvisor\\TripadvisorFactory',
            'Omnipulse\\Trustpilot\\TrustpilotFactory',
            'Omnipulse\\GitHubSponsors\\GitHubSponsorsFactory',
            'Omnipulse\\OpenCollective\\OpenCollectiveFactory',
            'Omnipulse\\Liberapay\\LiberapayFactory',
        ];
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('providers')
                    ->info('The providers, by name: a factory (search-console, matomo, google-reviews, trustpilot, liberapay...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pool')->defaultValue('cache.app')->info('A Symfony cache (CacheInterface) the Twig review functions keep what they show in - never longer than the platform allows.')->end()
                        ->integerNode('ttl')->defaultValue(3600)->min(0)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * The bundle's resources, Bridge/Twig: TwigBundle registers its templates/ as @Omnipulse
     * after the application's templates/bundles/OmnipulseBundle/, so a widget placed there wins.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__).'/Twig';
    }

    /** @param array{providers: array<string, array{factory: string, options: array<string, mixed>}>, cache: array{pool: ?string, ttl: int}} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(ProviderFactoryInterface::class)->addTag('omnipulse.provider_factory');

        $services = $container->services();
        foreach (self::factories() as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, ProviderFactoryInterface::class)) {
                $definition = $services->set($factory)->autowire()->tag('omnipulse.provider_factory');
                foreach ((new \ReflectionClass($factory))->getConstructor()?->getParameters() ?? [] as $parameter) {
                    if ('http' === $parameter->getName()) {
                        // A factory that can do without (its own client then) gets null when the application has none.
                        $definition->arg('$http', $parameter->allowsNull() ? service('http_client')->nullOnInvalid() : service('http_client'));
                    }
                }
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnipulse.provider_factory'), $config['providers']])
            ->public();

        foreach (array_keys($config['providers']) as $name) {
            $id = 'omnipulse.provider.'.$name;
            $services->set($id, ProviderInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            foreach (self::CONTRACTS as $type) {
                $builder->registerAliasForArgument($id, $type, $name);
            }
        }

        if (class_exists(AbstractExtension::class)) {
            $services->set(OmnipulseExtension::class)
                ->args([service(Registry::class), null !== $config['cache']['pool'] ? service($config['cache']['pool'])->nullOnInvalid() : null, $config['cache']['ttl']])
                ->tag('twig.extension');
        }
    }
}
