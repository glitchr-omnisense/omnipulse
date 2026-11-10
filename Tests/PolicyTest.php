<?php

namespace Omnipulse\Tests;

use Omnipulse\Capabilities;
use Omnipulse\Config;
use Omnipulse\Exception\InvalidKeyException;
use Omnipulse\Exception\OmnipulseException;
use Omnipulse\Exception\UnreachableException;
use Omnipulse\Exception\UsageLimitReachedException;
use Omnipulse\Http\Answer;
use Omnipulse\Http\Json;
use Omnipulse\Http\JsonApi;
use Omnipulse\Model\Place;
use Omnipulse\Model\Row;
use Omnipulse\Model\Terms;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Report;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;
use Omnipulse\Registry;
use Omnipulse\SearchPerformanceInterface;
use Omnipulse\Testing\FixedReachFactory;
use Omnipulse\Testing\FixedReviewsFactory;
use Omnipulse\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * The usage policies through omnipulse's registry: a site's limit counted
 * with the call over it never made, a 429 and its Retry-After holding the
 * provider's next calls (through each of the three HTTP helpers), a
 * tightening accepted, a looser limit and a commercial site refused, the
 * older Capabilities fields made into a policy and read from one, a
 * review platform's Terms as rights. No network: recorded answers only.
 */
final class PolicyTest extends TestCase
{
    public function testASitesLimitIsCountedAndTheCallOverItIsNeverMade(): void
    {
        $registry = new Registry([new FixedReachFactory([new Row(['query' => 'q'], ['clicks' => 1])]), new FixedReviewsFactory()], [
            'site' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['searchPerformance']]]]],
            'reviews' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'reviews.example', 'cache_for' => 0, 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'hour']]]]],
        ]);
        $query = ReachQuery::lastDays(7);

        $registry->search('site')->searchPerformance($query);
        $registry->audience('site')->audience($query);
        $registry->audience('site')->audience($query);
        try {
            $registry->search('site')->searchPerformance($query);
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnipulseException::class, $e, 'the family\'s catch holds it');
            self::assertSame('1/day/application/searchPerformance', $e->limit?->id());
            self::assertGreaterThan(0, $e->retryAfter);
        }

        $place = new Place('p1');
        $registry->rating('reviews')->rating($place);
        $registry->reviews('reviews')->reviews($place);
        $provider = $registry->get('reviews');
        self::assertInstanceOf(\Omnipulse\Testing\FixedReviews::class, $provider);
        try {
            $registry->writeUrl('reviews')->writeUrl($place);
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException) {
        }
        self::assertSame(2, $provider->calls, 'the refused call reached nothing');
        self::assertSame(0, $registry->guard('reviews')->remaining('rating'));
        self::assertSame(0, $registry->terms('reviews')->cacheFor, 'terms are read, never counted');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $http = RecordedHttpClient::routes(['GET /v1/stats' => [429, '{"message":"slow down"}', ['retry-after' => ['120']]]]);
        $registry = new Registry([new RecordedSearchFactory($http)], ['api' => ['factory' => 'recorded-search']]);

        try {
            $registry->search('api')->searchPerformance(ReachQuery::lastDays(7));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertSame('recorded-search answered 429 Too Many Requests: try again in 120 s.', $e->getMessage());
        }
        try {
            $registry->search('api')->searchPerformance(ReachQuery::lastDays(7));
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(119, $e->retryAfter);
        }
        self::assertCount(1, $http->requests, 'the wait held the next request back');
    }

    public function testEachHelperHonoursRetryAfterGivenTheProvidersBackoff(): void
    {
        $limiter = new InMemoryRateLimiter();
        foreach (['answer', 'json', 'api'] as $helper) {
            $backoff = new Backoff($limiter, $helper, UsageLimitReachedException::class);
            $http = RecordedHttpClient::routes(['GET /v1/x' => [429, '{}', ['Retry-After' => ['30']]]]);
            $call = match ($helper) {
                'answer' => static fn () => Answer::send($http, $helper, 'GET', 'https://api.example/v1/x', [], $backoff),
                'json' => static fn () => Json::request($http, 'GET', 'https://api.example/v1/x', [], $helper, 'x', $backoff),
                'api' => static fn () => (new JsonApi($http, $helper, $backoff))->request('GET', 'https://api.example/v1/x'),
            };
            foreach ([1, 2] as $attempt) {
                try {
                    $call();
                    self::fail("$helper: a 429 was read as an answer.");
                } catch (UsageLimitReachedException $e) {
                    self::assertGreaterThanOrEqual(29, $e->retryAfter, "$helper, attempt $attempt");
                }
            }
            self::assertCount(1, $http->requests, "$helper: the wait held the next request back");
        }
        try {
            Answer::send(RecordedHttpClient::routes(['GET /v1/x' => [429, 'busy']]), 'answer', 'GET', 'https://api.example/v1/x');
            self::fail('no exception');
        } catch (UnreachableException $e) {
            self::assertSame('[answer] HTTP 429: busy', $e->getMessage(), 'without a Backoff, a 429 is what it was');
        }
        try {
            (new JsonApi(RecordedHttpClient::routes(['GET /v1/x' => [401, '{"message":"Invalid API key"}']]), 'Example'))->request('GET', 'https://api.example/v1/x', [], 'it');
            self::fail('no exception');
        } catch (InvalidKeyException $e) {
            self::assertSame('Example refused the credentials (401) for it: Invalid API key', $e->getMessage());
        }
    }

    public function testATighteningIsAcceptedALooserLimitAndACommercialSiteAreRefused(): void
    {
        $factory = new RecordedSearchFactory(RecordedHttpClient::routes([]));
        $registry = new Registry([$factory], [
            'tight' => ['factory' => 'recorded-search', 'policy' => ['rateLimits' => [['max' => 10, 'window' => 'minute']], 'rights' => ['caching' => ['maxTtl' => 60]]]],
            'loose' => ['factory' => 'recorded-search', 'policy' => ['rateLimits' => [['max' => 500, 'window' => 'minute']]]],
            'commercial' => ['factory' => 'recorded-search', 'usage' => ['commercial' => true]],
        ]);

        $tight = $registry->policy('tight');
        self::assertSame(['100/minute/application/*', '10/minute/application/*'], array_map(static fn ($l) => $l->id(), $tight->rateLimits ?? []));
        self::assertSame(60, $tight->rights->caching->maxTtl);
        try {
            $registry->search('loose');
            self::fail('A looser limit was accepted.');
        } catch (InvalidPolicyException $e) {
            self::assertStringContainsString('100 a minute', $e->getMessage());
        }
        $this->expectException(UsageNotPermitted::class);
        $registry->search('commercial');
    }

    public function testTheOlderCapabilitiesFieldsMakeAPolicyAndAreReadFromOne(): void
    {
        $described = new Capabilities(audience: true, key: true, terms: ['https://example.org/terms'], limits: ['100 calls a day']);
        self::assertNull($described->policy()->rateLimits, 'limits in words are no figure: unknown');
        self::assertTrue($described->policy()->requirements->key);
        self::assertSame('https://example.org/terms', $described->policy()->provenance->terms);
        self::assertStringContainsString('100 calls a day', (string) $described->policy()->provenance->notes);
        self::assertTrue((new Capabilities())->policy()->requirements->key, 'a key not said: the stricter reading');
        self::assertFalse((new Capabilities(key: false))->policy()->requirements->key);

        $declared = new Capabilities(audience: true, policy: RecordedSearch::policy());
        self::assertFalse($declared->key);
        self::assertSame(['https://api.example/terms'], $declared->terms);
        self::assertSame(['100 a minute (the documented quota)'], $declared->limits);
        self::assertSame(RecordedSearch::policy()->toArray(), $declared->toArray()['policy']);
    }

    public function testAReviewPlatformsTermsAreItsPolicysRights(): void
    {
        $never = (new Terms(cacheFor: 0, attribution: 'Example'))->rights();
        self::assertSame(Permission::Forbidden, $never->caching->permission);
        self::assertTrue($never->attribution->required);
        self::assertSame('Example', $never->attribution->text);
        self::assertSame(['display'], $never->attribution->where);
        $day = (new Terms(cacheFor: 86400))->rights();
        self::assertSame([Permission::Allowed, 86400], [$day->caching->permission, $day->caching->maxTtl]);
        self::assertFalse($day->attribution->required);
        self::assertSame(Permission::Unknown, (new Terms(cacheFor: null))->rights()->caching->permission, 'not published: unknown');
        self::assertSame(Permission::Unknown, $day->commercial, 'what the terms do not say stays unknown');
    }
}

/** A search report over a recorded JSON API, its calls through JsonApi with the provider's Backoff. */
final class RecordedSearch implements SearchPerformanceInterface
{
    public function __construct(private readonly JsonApi $api)
    {
    }

    public static function policy(): UsagePolicy
    {
        return UsagePolicy::fromArray([
            'provider' => 'recorded-search',
            'rateLimits' => [['max' => 100, 'window' => 'minute', 'note' => 'the documented quota']],
            'rights' => ['commercial' => 'forbidden'],
            'requirements' => ['key' => false],
            'provenance' => ['terms' => 'https://api.example/terms', 'verifiedAt' => '2026-11-10'],
        ]);
    }

    public function getName(): string
    {
        return 'recorded-search';
    }

    public function getTitle(): string
    {
        return 'Recorded search';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(searchPerformance: true, policy: self::policy());
    }

    public function searchPerformance(ReachQuery $query): Report
    {
        $this->api->request('GET', 'https://api.example/v1/stats', [], 'the statistics');

        return new Report('recorded-search', 'https://example.org/', $query, []);
    }
}

final class RecordedSearchFactory extends ProviderFactory
{
    public function __construct(private readonly RecordedHttpClient $http)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults(['omnipulse.factory_name' => 'recorded-search', 'omnipulse.factory_title' => 'Recorded search', 'omnipulse.required_options' => []]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new RecordedSearch(new JsonApi($this->http, 'Recorded search', $c->backoff()));
    }
}
