<?php

namespace Omnipulse;

use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Terms;
use Omnipulse\Policy\Guarded;
use Omnipulse\Policy\GuardedAudience;
use Omnipulse\Policy\GuardedInvite;
use Omnipulse\Policy\GuardedProfile;
use Omnipulse\Policy\GuardedRating;
use Omnipulse\Policy\GuardedReply;
use Omnipulse\Policy\GuardedReviews;
use Omnipulse\Policy\GuardedSearchPerformance;
use Omnipulse\Policy\GuardedSponsors;
use Omnipulse\Policy\GuardedTiers;
use Omnipulse\Policy\GuardedWriteUrl;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderInterface as BaseProvider;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's providers by name, each built once from its factory
 * and options - whichever family it serves - each call through a contract
 * guarded by the provider's usage policy:
 *
 *   $registry = new Registry([new SearchConsoleFactory($http), new MatomoFactory($http), new GoogleReviewsFactory($http), new LiberapayFactory($http)], [
 *       'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '...']],
 *       'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
 *       'reviews' => [
 *           'factory' => 'google-reviews',
 *           'options' => ['api_key' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 200, 'window' => 'day', 'operations' => ['rating', 'reviews']]]],
 *       ],
 *       'liberapay' => ['factory' => 'liberapay', 'options' => ['account' => 'Liberapay']],
 *   ], $limiter);
 *
 *   $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));
 *   $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]));
 *   $registry->rating('reviews')->rating(new Place('ChIJ...'));
 *   $registry->profile('liberapay')->profile()->sponsors;
 *
 * A typed getter gives the provider when it implements the contract and
 * its capabilities() say it gives it as configured (Trustpilot replies
 * only with a business user): otherwise a NotSupportedException.
 *
 * The typed getters give the provider behind its guard (Policy\Guarded*,
 * inner() the provider itself): each call is checked against the policy's
 * rate limits for its operation - the contract's method name,
 * "searchPerformance", "audience", "rating", "reviews", "reply", "invite",
 * "writeUrl", "profile", "sponsors", "tiers" - then counted, and refused
 * with Exception\UsageLimitReachedException once a limit is reached or
 * while the provider asks to wait (a 429 and its Retry-After). get(),
 * all(), create() and terms() give the provider itself, unguarded.
 * "policy" tightens the provider's policy, never loosens it; "usage" says
 * what the site is (omnisense/contracts' Registry): policy(), policies(),
 * guard().
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    /** @var array<string, Guarded<ProviderInterface>> the guarded contracts, by "contract name" */
    private array $guarded = [];

    private readonly ?RateLimiterInterface $shared;

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                         $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                      $limiter   where every provider's calls are counted: an in-memory one by default, a shared one for an application served by many processes
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        parent::__construct($factories, $config, $limiter);
        $this->shared = $limiter;
    }

    public function get(string $name): ProviderInterface
    {
        $provider = parent::get($name);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" provider (%s) is no omnipulse provider.', $name, $provider::class));
    }

    /** The provider, as a search engine's report. */
    public function search(string $name): SearchPerformanceInterface
    {
        return $this->guarded($name, SearchPerformanceInterface::class, 'searchPerformance', 'report search performance', static fn (SearchPerformanceInterface $p, PolicyGuard $g) => new GuardedSearchPerformance($p, $g));
    }

    /** The provider, as an analytics report. */
    public function audience(string $name): AudienceInterface
    {
        return $this->guarded($name, AudienceInterface::class, 'audience', 'report the audience', static fn (AudienceInterface $p, PolicyGuard $g) => new GuardedAudience($p, $g));
    }

    /** The provider, as what gives an establishment's rating. */
    public function rating(string $name): RatingInterface
    {
        return $this->guarded($name, RatingInterface::class, 'rating', 'give its rating', static fn (RatingInterface $p, PolicyGuard $g) => new GuardedRating($p, $g));
    }

    /** The provider, as what gives an establishment's reviews. */
    public function reviews(string $name): ReviewsInterface
    {
        return $this->guarded($name, ReviewsInterface::class, 'reviews', 'give its reviews', static fn (ReviewsInterface $p, PolicyGuard $g) => new GuardedReviews($p, $g));
    }

    /** The provider, as what replies to a review. */
    public function replies(string $name): ReplyInterface
    {
        return $this->guarded($name, ReplyInterface::class, 'reply', 'give replying to a review', static fn (ReplyInterface $p, PolicyGuard $g) => new GuardedReply($p, $g));
    }

    /** The provider, as what invites a customer to review. */
    public function invitations(string $name): InviteInterface
    {
        return $this->guarded($name, InviteInterface::class, 'invite', 'give inviting a customer to review', static fn (InviteInterface $p, PolicyGuard $g) => new GuardedInvite($p, $g));
    }

    /** The provider, as what gives the address to leave a review. */
    public function writeUrl(string $name): WriteUrlInterface
    {
        return $this->guarded($name, WriteUrlInterface::class, 'writeUrl', 'give the address to leave a review', static fn (WriteUrlInterface $p, PolicyGuard $g) => new GuardedWriteUrl($p, $g));
    }

    /** How what a review platform gives may be shown and kept (its policy's rights say it too: Terms::rights()). */
    public function terms(string $name): Terms
    {
        $provider = $this->get($name);

        return $provider instanceof TermsInterface ? $provider->terms() : throw NotSupportedException::question($provider, $name, 'give terms of display');
    }

    /** The provider, as a funded account's profile. */
    public function profile(string $name): ProfileInterface
    {
        return $this->guarded($name, ProfileInterface::class, 'profile', 'show a profile', static fn (ProfileInterface $p, PolicyGuard $g) => new GuardedProfile($p, $g));
    }

    /** The provider, as the account's public sponsors. */
    public function sponsors(string $name): SponsorsInterface
    {
        return $this->guarded($name, SponsorsInterface::class, 'sponsors', 'list sponsors', static fn (SponsorsInterface $p, PolicyGuard $g) => new GuardedSponsors($p, $g));
    }

    /** The provider, as the tiers the account offers. */
    public function tiers(string $name): TiersInterface
    {
        return $this->guarded($name, TiersInterface::class, 'tiers', 'list tiers', static fn (TiersInterface $p, PolicyGuard $g) => new GuardedTiers($p, $g));
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
        $provider = $this->factory($name)->create(array_replace($this->config[$name]['options'] ?? [], $overrides) + [self::PREFIX.'.name' => $name] + (null !== $this->shared ? [self::PREFIX.'.limiter' => $this->shared] : []));

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" provider (%s) is no omnipulse provider.', $name, $provider::class));
    }

    /**
     * The configured providers' names, in the configuration's order; those
     * keeping a contract ("audience", "rating", "sponsors"...) when one is
     * named.
     *
     * @return list<string>
     */
    public function names(?string $contract = null): array
    {
        return parent::names($contract);
    }

    /** @return array<string, ProviderInterface> every configured provider, built */
    public function all(): array
    {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(BaseProvider $provider, string $name, string $capability): \Throwable
    {
        return $provider instanceof ProviderInterface ? NotSupportedException::question($provider, $name, $capability) : NotSupportedException::capability($provider, $name, $capability);
    }

    /**
     * The provider behind its guard, when it implements the contract and its
     * capabilities() say it gives it as configured.
     *
     * @template T of ProviderInterface
     *
     * @param class-string<T>                  $contract
     * @param \Closure(T, PolicyGuard): Guarded<T> $wrap
     *
     * @return T
     */
    private function guarded(string $name, string $contract, string $capability, string $question, \Closure $wrap): ProviderInterface
    {
        $key = $capability.' '.$name;
        if (!isset($this->guarded[$key])) {
            $provider = $this->get($name);
            if (!$provider instanceof $contract || !$provider->capabilities()->gives($capability)) {
                throw NotSupportedException::question($provider, $name, $question);
            }
            $this->guarded[$key] = $wrap($provider, $this->guard($name));
        }
        $guarded = $this->guarded[$key];

        return $guarded instanceof $contract ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }
}
