<?php

namespace Omnipulse\Model;

/**
 * The establishment on one platform: its identifier there - a Google place
 * ID, a Tripadvisor location ID, a Trustpilot business unit ID -, kept by
 * the application (every platform allows keeping it).
 */
final readonly class Place
{
    /**
     * @param string      $id       the platform's identifier
     * @param string|null $owner    a second identifier the owner's side of the platform needs: Google Business Profile's "accounts/<a>/locations/<l>"
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $owner = null,
    ) {
    }
}
