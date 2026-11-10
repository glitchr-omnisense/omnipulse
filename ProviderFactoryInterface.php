<?php

namespace Omnipulse;

/** Builds a provider from its options (a key, a site, a server URL, an account...). */
interface ProviderFactoryInterface extends \Omnisense\Contracts\Provider\ProviderFactoryInterface
{
    /** The name providers are configured with: "search-console", "matomo", "google-reviews", "liberapay"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
