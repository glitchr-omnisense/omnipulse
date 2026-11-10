<?php

namespace Omnipulse\Model;

/**
 * Someone who funds the account, as the provider shows them publicly: a
 * handle, a name, a picture, a page; their tier, since when and how much
 * when the provider says.
 */
final readonly class Sponsor
{
    public const USER = 'user';
    public const ORGANIZATION = 'organization';
    public const OTHER = 'other';

    public function __construct(
        public string $handle,
        public ?string $name = null,
        public ?string $avatarUrl = null,
        public ?string $url = null,
        public string $kind = self::USER,
        public ?\DateTimeImmutable $since = null,
        public ?string $tier = null,
        public ?Money $total = null,
    ) {
    }

    /** The name shown: the name, or the handle. */
    public function label(): string
    {
        return $this->name ?? $this->handle;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['handle' => $this->handle, 'name' => $this->name, 'avatarUrl' => $this->avatarUrl, 'url' => $this->url, 'kind' => $this->kind, 'since' => $this->since?->format(\DATE_ATOM), 'tier' => $this->tier, 'total' => $this->total?->toArray()];
    }
}
