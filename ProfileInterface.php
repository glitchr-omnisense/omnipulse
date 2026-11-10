<?php

namespace Omnipulse;

use Omnipulse\Model\Profile;

/** The account as it shows itself: name, page, how many fund it, what it receives, its goal. */
interface ProfileInterface extends ProviderInterface
{
    /** @param string|null $account another account than the configured one */
    public function profile(?string $account = null): Profile;
}
