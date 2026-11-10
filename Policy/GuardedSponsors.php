<?php

namespace Omnipulse\Policy;

use Omnipulse\SponsorsInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's sponsors behind its guard: each call is the operation
 * "sponsors".
 *
 * @extends Guarded<SponsorsInterface>
 */
final class GuardedSponsors extends Guarded implements SponsorsInterface
{
    public function __construct(SponsorsInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function sponsors(?string $account = null, ?int $limit = null): array
    {
        return $this->guard->call('sponsors', fn () => $this->provider->sponsors($account, $limit));
    }
}
