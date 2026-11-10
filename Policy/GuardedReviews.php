<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\Place;
use Omnipulse\ReviewsInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's reviews behind its guard: each call is the operation
 * "reviews".
 *
 * @extends Guarded<ReviewsInterface>
 */
final class GuardedReviews extends Guarded implements ReviewsInterface
{
    public function __construct(ReviewsInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function reviews(Place $place, int $limit = 5, ?string $language = null): array
    {
        return $this->guard->call('reviews', fn () => $this->provider->reviews($place, $limit, $language));
    }
}
