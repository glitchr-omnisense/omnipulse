<?php

namespace Omnipulse;

use Omnipulse\Model\Place;
use Omnipulse\Model\Reply;
use Omnipulse\Model\Review;

/** Answering a review as the establishment: the owner's access to the platform needed. */
interface ReplyInterface extends TermsInterface
{
    /** The reply, as published. */
    public function reply(Place $place, Review $review, string $text): Reply;
}
