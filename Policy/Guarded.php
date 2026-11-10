<?php

namespace Omnipulse\Policy;

use Omnipulse\Capabilities;
use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Terms;
use Omnipulse\ProviderInterface;
use Omnipulse\TermsInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider behind its guard: what every Guarded* shares - its name, title,
 * capabilities and a review platform's terms as they are (no call is
 * counted for them), the guard, the provider itself (inner()).
 *
 * @template-covariant P of ProviderInterface the contract it keeps
 */
abstract class Guarded implements ProviderInterface
{
    /** @param P $provider */
    public function __construct(protected readonly ProviderInterface $provider, protected readonly PolicyGuard $guard)
    {
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getTitle(): string
    {
        return $this->provider->getTitle();
    }

    public function capabilities(): Capabilities
    {
        return $this->provider->capabilities();
    }

    /** A review platform's terms of display and caching, read from the provider as they are. */
    public function terms(): Terms
    {
        return $this->provider instanceof TermsInterface ? $this->provider->terms() : throw NotSupportedException::operation($this->provider->getName(), 'give terms of display');
    }

    /** @return P the provider, unguarded */
    public function inner(): ProviderInterface
    {
        return $this->provider;
    }

    public function guard(): PolicyGuard
    {
        return $this->guard;
    }
}
