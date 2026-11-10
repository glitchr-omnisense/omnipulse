<?php

namespace Omnipulse\Policy;

use Omnipulse\TiersInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's tiers behind its guard: each call is the operation
 * "tiers".
 *
 * @extends Guarded<TiersInterface>
 */
final class GuardedTiers extends Guarded implements TiersInterface
{
    public function __construct(TiersInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function tiers(?string $account = null): array
    {
        return $this->guard->call('tiers', fn () => $this->provider->tiers($account));
    }
}
