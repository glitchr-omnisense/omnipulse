<?php

namespace Omnipulse;

use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Terms;

/**
 * The application's providers by name, each built once from its factory
 * and options - whichever family it serves:
 *
 *   $registry = new Registry([new SearchConsoleFactory($http), new MatomoFactory($http), new GoogleReviewsFactory($http), new LiberapayFactory($http)], [
 *       'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '...']],
 *       'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
 *       'reviews' => ['factory' => 'google-reviews', 'options' => ['api_key' => '...']],
 *       'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
 *   ]);
 *
 *   $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));
 *   $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]));
 *   $registry->rating('reviews')->rating(new Place('ChIJ...'));
 *   $registry->profile('liberapay')->profile()->sponsors;
 *
 * A typed getter gives the provider when it implements the contract and
 * its capabilities() say it gives it as configured (Trustpilot replies
 * only with a business user): otherwise a NotSupportedException.
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        return $this->providers[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The provider, as a search engine's report. */
    public function search(string $name): SearchPerformanceInterface
    {
        return $this->typed($name, SearchPerformanceInterface::class, 'searchPerformance', 'report search performance');
    }

    /** The provider, as an analytics report. */
    public function audience(string $name): AudienceInterface
    {
        return $this->typed($name, AudienceInterface::class, 'audience', 'report the audience');
    }

    /** The provider, as what gives an establishment's rating. */
    public function rating(string $name): RatingInterface
    {
        return $this->typed($name, RatingInterface::class, 'rating', 'give its rating');
    }

    /** The provider, as what gives an establishment's reviews. */
    public function reviews(string $name): ReviewsInterface
    {
        return $this->typed($name, ReviewsInterface::class, 'reviews', 'give its reviews');
    }

    /** The provider, as what replies to a review. */
    public function replies(string $name): ReplyInterface
    {
        return $this->typed($name, ReplyInterface::class, 'reply', 'give replying to a review');
    }

    /** The provider, as what invites a customer to review. */
    public function invitations(string $name): InviteInterface
    {
        return $this->typed($name, InviteInterface::class, 'invite', 'give inviting a customer to review');
    }

    /** The provider, as what gives the address to leave a review. */
    public function writeUrl(string $name): WriteUrlInterface
    {
        return $this->typed($name, WriteUrlInterface::class, 'writeUrl', 'give the address to leave a review');
    }

    /** How what a review platform gives may be shown and kept. */
    public function terms(string $name): Terms
    {
        $provider = $this->get($name);

        return $provider instanceof TermsInterface ? $provider->terms() : throw NotSupportedException::question($provider, $name, 'give terms of display');
    }

    /** The provider, as a funded account's profile. */
    public function profile(string $name): ProfileInterface
    {
        return $this->typed($name, ProfileInterface::class, 'profile', 'show a profile');
    }

    /** The provider, as the account's public sponsors. */
    public function sponsors(string $name): SponsorsInterface
    {
        return $this->typed($name, SponsorsInterface::class, 'sponsors', 'list sponsors');
    }

    /** The provider, as the tiers the account offers. */
    public function tiers(string $name): TiersInterface
    {
        return $this->typed($name, TiersInterface::class, 'tiers', 'list tiers');
    }

    /** @return array<string, mixed> the options a provider is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /**
     * A provider built afresh, its configured options with $overrides over
     * them - a key typed in a back office, say. Not kept: get() still gives
     * the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): ProviderInterface
    {
        return $this->factory($name)->create(array_replace($this->config[$name]['options'] ?? [], $overrides));
    }

    /** @return list<string> the configured providers' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, ProviderInterface> every configured provider, built */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->config) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    /**
     * @template T of ProviderInterface
     *
     * @param class-string<T> $contract
     *
     * @return T
     */
    private function typed(string $name, string $contract, string $capability, string $question): ProviderInterface
    {
        $provider = $this->get($name);

        return $provider instanceof $contract && $provider->capabilities()->gives($capability) ? $provider : throw NotSupportedException::question($provider, $name, $question);
    }

    private function factory(string $name): ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
