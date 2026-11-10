<?php

namespace Omnipulse\Model;

/**
 * One review, with what showing it requires: its author as the platform
 * names them, its date, the link to it on the platform.
 */
final readonly class Review
{
    /**
     * @param string      $id          the platform's identifier: what a reply needs
     * @param float       $rating      on the platform's scale
     * @param string|null $url         the review on the platform, or the establishment's page: link it
     * @param bool        $translated  the text is the platform's translation
     */
    public function __construct(
        public string $platform,
        public string $id,
        public float $rating,
        public ?string $text = null,
        public ?string $title = null,
        public ?string $language = null,
        public ?string $author = null,
        public ?string $authorUrl = null,
        public ?string $authorPhoto = null,
        public ?\DateTimeImmutable $publishedAt = null,
        public ?string $url = null,
        public ?Reply $reply = null,
        public int $scale = 5,
        public bool $translated = false,
        public ?string $image = null,
    ) {
    }
}
