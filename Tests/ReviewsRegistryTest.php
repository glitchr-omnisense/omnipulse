<?php

namespace Omnipulse\Tests;

use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Capabilities;
use Omnipulse\Model\Rating;
use Omnipulse\Model\Terms;
use Omnipulse\Registry;
use Omnipulse\Testing\FixedReviewsFactory;
use PHPUnit\Framework\TestCase;

final class ReviewsRegistryTest extends TestCase
{
    public function testEachCapabilityIsAskedOfAProviderThatGivesIt(): void
    {
        $registry = new Registry([new FixedReviewsFactory()], ['stub' => ['factory' => 'fixed-reviews', 'options' => ['host' => 'reviews.example']], 'broken' => ['factory' => 'fixed-reviews']]);

        $rating = $registry->rating('stub');
        self::assertInstanceOf(\Omnipulse\Policy\Guarded::class, $rating, 'the provider behind its usage policy\'s guard');
        self::assertSame($registry->get('stub'), $rating->inner());
        self::assertSame('https://reviews.example/write/p1', $registry->writeUrl('stub')->writeUrl(new \Omnipulse\Model\Place('p1')));
        try {
            $registry->replies('stub');
            self::fail();
        } catch (NotSupportedException $e) {
            self::assertSame('The "stub" provider (Fixed reviews) does not give replying to a review; it gives: rating, reviews, writeUrl.', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->get('broken');
    }

    /** Trustpilot without a business user implements ReplyInterface, but does not reply as configured: its capabilities say so. */
    public function testAGetterRefusesWhatTheProviderDoesNotGiveAsConfigured(): void
    {
        $provider = new class implements \Omnipulse\RatingInterface, \Omnipulse\ReplyInterface {
            public function getName(): string
            {
                return 'trustpilot';
            }

            public function getTitle(): string
            {
                return 'Trustpilot';
            }

            public function capabilities(): Capabilities
            {
                return new Capabilities(rating: true, reply: false);
            }

            public function terms(): Terms
            {
                return new Terms();
            }

            public function rating(\Omnipulse\Model\Place $place): Rating
            {
                return new Rating('trustpilot', 4.5, 10);
            }

            public function reply(\Omnipulse\Model\Place $place, \Omnipulse\Model\Review $review, string $text): \Omnipulse\Model\Reply
            {
                throw NotSupportedException::operation('trustpilot', 'reply');
            }
        };
        $factory = new class($provider) implements \Omnipulse\ProviderFactoryInterface {
            public function __construct(private readonly \Omnipulse\ProviderInterface $provider)
            {
            }

            public function getName(): string
            {
                return 'trustpilot';
            }

            public function create(array $options = []): \Omnipulse\ProviderInterface
            {
                return $this->provider;
            }
        };
        $registry = new Registry([$factory], ['trustpilot' => ['factory' => 'trustpilot']]);

        $rating = $registry->rating('trustpilot');
        self::assertInstanceOf(\Omnipulse\Policy\Guarded::class, $rating, 'the provider behind its usage policy\'s guard');
        self::assertSame($provider, $rating->inner());
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('The "trustpilot" provider (Trustpilot) does not give replying to a review; it gives: rating.');
        $registry->replies('trustpilot');
    }

    public function testRatingsTermsAndCapabilities(): void
    {
        self::assertSame(4.3, (new Rating('x', 8.6, 42, 10))->outOfFive());
        self::assertTrue((new Terms(cacheFor: 86400))->allowsCaching(3600));
        self::assertFalse((new Terms(cacheFor: 0))->allowsCaching(1));
        self::assertFalse((new Terms(cacheFor: null))->allowsCaching(1), 'unknown: not cached');
        self::assertSame(['rating', 'invite'], (new Capabilities(rating: true, invite: true))->toArray()['contracts']);
        self::assertSame(['reviews'], (new Capabilities(rating: true, invite: true))->families());
    }
}
