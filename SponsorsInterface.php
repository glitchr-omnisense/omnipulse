<?php

namespace Omnipulse;

use Omnipulse\Model\Sponsor;

/** Who funds the account, as far as the platform shows them publicly. */
interface SponsorsInterface extends ProviderInterface
{
    /**
     * @param string|null $account another account than the configured one
     * @param int|null    $limit   at most this many; null: every one, page after page
     *
     * @return list<Sponsor>
     */
    public function sponsors(?string $account = null, ?int $limit = null): array;
}
