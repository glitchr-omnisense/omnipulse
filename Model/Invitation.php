<?php

namespace Omnipulse\Model;

/** A customer to invite: their e-mail and name, what they bought (a reference), when and in which language to ask. */
final readonly class Invitation
{
    public function __construct(
        public string $email,
        public string $name,
        public ?string $reference = null,
        public string $locale = 'fr-FR',
        public ?\DateTimeImmutable $sendAt = null,
        public ?string $redirectUrl = null,
    ) {
    }
}
