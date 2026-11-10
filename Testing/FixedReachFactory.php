<?php

namespace Omnipulse\Testing;

use Omnipulse\Config;
use Omnipulse\Model\Row;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;

/** FixedReach under the name "fixed", for a Registry. */
final class FixedReachFactory extends ProviderFactory
{
    /**
     * @param list<Row> $search
     * @param list<Row> $audience
     */
    public function __construct(private readonly array $search = [], private readonly array $audience = [])
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults(['omnipulse.factory_name' => 'fixed', 'omnipulse.factory_title' => 'Fixed', 'omnipulse.required_options' => []]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new FixedReach($this->search, $this->audience, $c->string('site') ?? 'https://example.org/');
    }
}
