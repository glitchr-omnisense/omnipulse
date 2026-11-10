<?php

namespace Omnipulse;

use Omnipulse\Exception\InvalidConfigException;

/**
 * A provider's configuration while its factory builds it: the options
 * given, the factory's defaults under them, and the keys that must be
 * filled - omnisense/contracts' Config, with omnipulse's prefix
 * ("omnipulse.factory_name", "omnipulse.backoff"...), its
 * InvalidConfigException and list(). Every provider takes two options
 * besides its own: "policy" (the site's tightening of its usage policy)
 * and "usage" (what the site says of itself: commercial, open_source,
 * contract).
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    /** @var string */
    public const PREFIX = 'omnipulse';

    /**
     * A list given as one environment variable arrives as a single
     * comma-separated string: split here, at run time.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        $list = [];
        foreach ((array) $this->get($key, []) as $value) {
            foreach (explode(',', \is_scalar($value) ? (string) $value : '') as $one) {
                if ('' !== trim($one)) {
                    $list[] = trim($one);
                }
            }
        }

        return $list;
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
