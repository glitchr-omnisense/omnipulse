<?php

namespace Omnipulse\Model;

/** The establishment's answer to a review. */
final readonly class Reply
{
    public function __construct(
        public string $text,
        public ?\DateTimeImmutable $at = null,
    ) {
    }
}
