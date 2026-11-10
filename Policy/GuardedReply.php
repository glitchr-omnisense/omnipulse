<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\Place;
use Omnipulse\Model\Reply;
use Omnipulse\Model\Review;
use Omnipulse\ReplyInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's replies behind its guard: each call is the operation
 * "reply".
 *
 * @extends Guarded<ReplyInterface>
 */
final class GuardedReply extends Guarded implements ReplyInterface
{
    public function __construct(ReplyInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function reply(Place $place, Review $review, string $text): Reply
    {
        return $this->guard->call('reply', fn () => $this->provider->reply($place, $review, $text));
    }
}
