<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\Profile;
use Omnipulse\ProfileInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's profile behind its guard: each call is the operation
 * "profile".
 *
 * @extends Guarded<ProfileInterface>
 */
final class GuardedProfile extends Guarded implements ProfileInterface
{
    public function __construct(ProfileInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function profile(?string $account = null): Profile
    {
        return $this->guard->call('profile', fn () => $this->provider->profile($account));
    }
}
