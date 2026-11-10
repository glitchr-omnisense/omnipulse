<?php

namespace Omnipulse\Testing;

use Omnipulse\Config;
use Omnipulse\Exception\InvalidConfigException;
use Omnipulse\Model\Profile;
use Omnipulse\Model\Sponsor;
use Omnipulse\Model\Tier;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;

/**
 * ArrayProvider, from its options:
 *
 *   profile   a Model\Profile (required)
 *   sponsors  a list of Model\Sponsor
 *   tiers     a list of Model\Tier
 */
final class ArrayFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnipulse.factory_name' => 'array',
            'omnipulse.factory_title' => 'Fixed data',
            'omnipulse.required_options' => ['profile'],
            'sponsors' => [],
            'tiers' => [],
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $profile = $c->get('profile');
        $sponsors = $c->get('sponsors');
        $tiers = $c->get('tiers');
        if (!$profile instanceof Profile || !\is_array($sponsors) || !\is_array($tiers)) {
            throw new InvalidConfigException('The "array" provider needs a Profile, and lists of sponsors and tiers.');
        }

        return new ArrayProvider(
            $profile,
            array_values(array_filter($sponsors, static fn ($s) => $s instanceof Sponsor)),
            array_values(array_filter($tiers, static fn ($t) => $t instanceof Tier)),
        );
    }
}
