<?php

namespace Omnipulse\Model;

/**
 * What an account says it aims for: an amount (Liberapay, GitHub's
 * MONTHLY_SPONSORSHIP_AMOUNT) or a number of sponsors (GitHub's
 * TOTAL_SPONSORS_COUNT), and how far it has got when the provider says.
 */
final readonly class Goal
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?Money $amount = null,
        public ?Period $period = null,
        public ?int $sponsors = null,
        public ?int $percent = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['title' => $this->title, 'description' => $this->description, 'amount' => $this->amount?->toArray(), 'period' => $this->period?->value, 'sponsors' => $this->sponsors, 'percent' => $this->percent];
    }
}
