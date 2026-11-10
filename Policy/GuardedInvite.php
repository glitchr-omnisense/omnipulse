<?php

namespace Omnipulse\Policy;

use Omnipulse\InviteInterface;
use Omnipulse\Model\Invitation;
use Omnipulse\Model\Place;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's invitations behind its guard: each call is the operation
 * "invite".
 *
 * @extends Guarded<InviteInterface>
 */
final class GuardedInvite extends Guarded implements InviteInterface
{
    public function __construct(InviteInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function invite(Place $place, Invitation $invitation): ?string
    {
        return $this->guard->call('invite', fn () => $this->provider->invite($place, $invitation));
    }
}
