<?php

namespace Omnipulse\Testing;

use Omnipulse\Capabilities;
use Omnipulse\Model\Profile;
use Omnipulse\Model\Sponsor;
use Omnipulse\Model\Tier;
use Omnipulse\ProfileInterface;
use Omnipulse\SponsorsInterface;
use Omnipulse\TiersInterface;

/** A provider that answers what it was given: for tests, demos and an application's fixtures. */
final class ArrayProvider implements ProfileInterface, SponsorsInterface, TiersInterface
{
    /**
     * @param list<Sponsor> $sponsors
     * @param list<Tier>    $tiers
     */
    public function __construct(private readonly Profile $profile, private readonly array $sponsors = [], private readonly array $tiers = [])
    {
    }

    public function getName(): string
    {
        return 'array';
    }

    public function getTitle(): string
    {
        return 'Fixed data';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(profile: true, sponsors: true, tiers: true, key: false);
    }

    public function profile(?string $account = null): Profile
    {
        return $this->profile;
    }

    public function sponsors(?string $account = null, ?int $limit = null): array
    {
        return null === $limit ? $this->sponsors : \array_slice($this->sponsors, 0, $limit);
    }

    public function tiers(?string $account = null): array
    {
        return $this->tiers;
    }
}
