<?php

namespace Omnipulse\Testing;

use Omnipulse\Capabilities;
use Omnipulse\Exception\UnreachableException;
use Omnipulse\Model\Place;
use Omnipulse\Model\Rating;
use Omnipulse\Model\Review;
use Omnipulse\Model\Terms;
use Omnipulse\RatingInterface;
use Omnipulse\ReviewsInterface;
use Omnipulse\WriteUrlInterface;

/**
 * A review platform kept in memory: an application's tests, a demo, the
 * harness's bare script. It gives a rating of 8.6/10 from 42 reviews, one
 * review and the link to write one; it counts the calls it answers
 * ($calls); the place "down" does not answer (UnreachableException), as a
 * platform down.
 */
final class FixedReviews implements RatingInterface, ReviewsInterface, WriteUrlInterface
{
    public int $calls = 0;

    public function __construct(public readonly string $host = 'reviews.example', private readonly ?int $cacheFor = 3600)
    {
    }

    public function getName(): string
    {
        return 'fixed-reviews';
    }

    public function getTitle(): string
    {
        return 'Fixed reviews';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(rating: true, reviews: true, writeUrl: true, key: false);
    }

    public function terms(): Terms
    {
        return new Terms(cacheFor: $this->cacheFor, maxReviews: 5, attribution: 'Fixed Reviews');
    }

    public function rating(Place $place): Rating
    {
        ++$this->calls;
        if ('down' === $place->id) {
            throw UnreachableException::from('fixed-reviews', 'No answer.');
        }

        return new Rating('fixed-reviews', 8.6, 42, 10, 'https://'.$this->host.'/'.$place->id);
    }

    public function reviews(Place $place, int $limit = 5, ?string $language = null): array
    {
        ++$this->calls;

        return \array_slice([new Review('fixed-reviews', 'r1', 5, 'Parfait <b>vraiment</b>', 'Super', 'fr', 'Camille R.', 'https://'.$this->host.'/u/camille', publishedAt: new \DateTimeImmutable('2026-09-30'), url: 'https://'.$this->host.'/r/1')], 0, max(0, $limit));
    }

    public function writeUrl(Place $place): string
    {
        if ('down' === $place->id) {
            throw UnreachableException::from('fixed-reviews', 'HTTP 503.', '503');
        }

        return 'https://'.$this->host.'/write/'.$place->id;
    }
}
