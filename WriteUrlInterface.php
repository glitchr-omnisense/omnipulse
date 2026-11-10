<?php

namespace Omnipulse;

use Omnipulse\Model\Place;

/** Where a customer leaves a review: a link to give them - a button, an e-mail, a receipt. */
interface WriteUrlInterface extends TermsInterface
{
    public function writeUrl(Place $place): string;
}
