<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\Place;
use Omnipulse\Model\Rating;
use Omnipulse\RatingInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's rating behind its guard: each call is the operation
 * "rating".
 *
 * @extends Guarded<RatingInterface>
 */
final class GuardedRating extends Guarded implements RatingInterface
{
    public function __construct(RatingInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function rating(Place $place): Rating
    {
        return $this->guard->call('rating', fn () => $this->provider->rating($place));
    }
}
