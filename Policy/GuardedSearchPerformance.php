<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\ReachQuery;
use Omnipulse\Model\Report;
use Omnipulse\SearchPerformanceInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's search performance report behind its guard: each call is the operation
 * "searchPerformance".
 *
 * @extends Guarded<SearchPerformanceInterface>
 */
final class GuardedSearchPerformance extends Guarded implements SearchPerformanceInterface
{
    public function __construct(SearchPerformanceInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function searchPerformance(ReachQuery $query): Report
    {
        return $this->guard->call('searchPerformance', fn () => $this->provider->searchPerformance($query));
    }
}
