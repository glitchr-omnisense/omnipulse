<?php

namespace Omnipulse;

use Omnipulse\Model\Invitation;
use Omnipulse\Model\Place;

/** Inviting a customer to leave a review, by the platform's own e-mail. */
interface InviteInterface extends TermsInterface
{
    /** @return string|null the platform's reference for the invitation, when it gives one */
    public function invite(Place $place, Invitation $invitation): ?string;
}
