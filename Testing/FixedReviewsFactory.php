<?php

namespace Omnipulse\Testing;

use Omnipulse\Config;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;

/**
 * FixedReviews under the name "fixed-reviews", for a Registry:
 *
 *   host       the platform's host its links point to (required): "reviews.example"
 *   cache_for  the seconds its Terms let what it gives be kept: 3600; 0 never, null not said
 */
final class FixedReviewsFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omnipulse.factory_name' => 'fixed-reviews', 'omnipulse.factory_title' => 'Fixed reviews', 'omnipulse.required_options' => ['host'], 'cache_for' => 3600]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $cacheFor = $c->get('cache_for');

        return new FixedReviews($c->string('host') ?? 'reviews.example', \is_int($cacheFor) || is_numeric($cacheFor) ? (int) $cacheFor : null);
    }
}
