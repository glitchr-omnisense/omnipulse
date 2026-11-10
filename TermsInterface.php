<?php

namespace Omnipulse;

use Omnipulse\Model\Terms;

/**
 * A review platform, configured: Google, Tripadvisor, Trustpilot. What it
 * gives is said by the contracts it implements - RatingInterface,
 * ReviewsInterface, ReplyInterface, InviteInterface, WriteUrlInterface -
 * and by its Capabilities, as configured; its Terms say how what it gives
 * may be shown and kept, as the platform's own rules say.
 *
 * It reads and writes on the platform and keeps nothing: the application
 * decides what to show, for how long - within the Terms.
 */
interface TermsInterface extends ProviderInterface
{
    /** How what it gives may be shown and kept, as the platform's rules say. */
    public function terms(): Terms;
}
