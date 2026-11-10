<?php

namespace Omnipulse\Policy;

use Omnipulse\AudienceInterface;
use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Report;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's audience report behind its guard: each call is the operation
 * "audience".
 *
 * @extends Guarded<AudienceInterface>
 */
final class GuardedAudience extends Guarded implements AudienceInterface
{
    public function __construct(AudienceInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function audience(ReachQuery $query): Report
    {
        return $this->guard->call('audience', fn () => $this->provider->audience($query));
    }
}
