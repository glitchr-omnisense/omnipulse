<?php

namespace Omnipulse\Policy;

use Omnipulse\Model\Place;
use Omnipulse\WriteUrlInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's link to leave a review behind its guard: each call is the operation
 * "writeUrl".
 *
 * @extends Guarded<WriteUrlInterface>
 */
final class GuardedWriteUrl extends Guarded implements WriteUrlInterface
{
    public function __construct(WriteUrlInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function writeUrl(Place $place): string
    {
        return $this->guard->call('writeUrl', fn () => $this->provider->writeUrl($place));
    }
}
