<?php

namespace Omnipulse;

use Omnipulse\Model\Tier;

/** The ways to give the account offers. */
interface TiersInterface extends ProviderInterface
{
    /**
     * @param string|null $account another account than the configured one
     *
     * @return list<Tier>
     */
    public function tiers(?string $account = null): array;
}
