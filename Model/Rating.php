<?php

namespace Omnipulse\Model;

/** A rating as the platform gives it, with what showing it requires. */
final readonly class Rating
{
    /**
     * @param float       $value     the score, on the platform's scale
     * @param int         $scale     the scale's top: 5
     * @param int         $count     the reviews, or ratings, it rests on
     * @param string|null $url       the establishment's page on the platform: link it
     * @param string|null $image     the platform's own picture of the score (Tripadvisor's bubbles), to show as it is, from its address
     */
    public function __construct(
        public string $platform,
        public float $value,
        public int $count,
        public int $scale = 5,
        public ?string $url = null,
        public ?string $image = null,
        public \DateTimeImmutable $fetchedAt = new \DateTimeImmutable(),
    ) {
    }

    /** The score on 5, rounded to a tenth. */
    public function outOfFive(): float
    {
        return round($this->value * 5 / max(1, $this->scale), 1);
    }
}
