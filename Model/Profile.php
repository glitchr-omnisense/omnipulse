<?php

namespace Omnipulse\Model;

/**
 * An account where funding is received, as it shows itself: its name and
 * page, how many fund it, what it receives and what it aims for - each null
 * when the provider does not say.
 */
final readonly class Profile
{
    public function __construct(
        public string $provider,
        public string $handle,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?string $avatarUrl = null,
        public ?int $sponsors = null,
        public ?Income $income = null,
        public ?Goal $goal = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['provider' => $this->provider, 'handle' => $this->handle, 'name' => $this->name, 'description' => $this->description, 'url' => $this->url, 'avatarUrl' => $this->avatarUrl, 'sponsors' => $this->sponsors, 'income' => $this->income?->toArray(), 'goal' => $this->goal?->toArray()];
    }
}
