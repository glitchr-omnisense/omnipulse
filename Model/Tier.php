<?php

namespace Omnipulse\Model;

/** One way to give that an account offers: "$5 a month", "Backer", a custom amount. */
final readonly class Tier
{
    public function __construct(
        public string $name,
        public ?Money $price = null,
        public Period $period = Period::Month,
        public ?string $id = null,
        public ?string $description = null,
        public bool $customAmount = false,
        public ?Money $minimum = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'price' => $this->price?->toArray(), 'period' => $this->period->value, 'description' => $this->description, 'customAmount' => $this->customAmount, 'minimum' => $this->minimum?->toArray()];
    }
}
