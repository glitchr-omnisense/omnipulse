<?php

namespace Omnipulse\Model;

/**
 * How what a platform gives may be shown and kept - its own rules, read in
 * its documentation and declared by its package. The family's display
 * follows them; an application that shows things its own way must too.
 */
final readonly class Terms
{
    /**
     * @param int|null     $cacheFor    seconds what it gives may be kept before asking again: 0 never, null "as the platform's caching policy allows" (not published)
     * @param int|null     $maxReviews  the reviews one call gives at most
     * @param string       $attribution what must be shown with it: the platform's name or logo
     * @param bool         $authors     each review shown with its author as the platform names them
     * @param bool         $link        a link back to the platform shown with it
     * @param bool         $noIndex     pages that show it are kept out of search engines
     * @param list<string> $rules       the other rules, in words
     * @param string|null  $source      where they are written
     */
    public function __construct(
        public ?int $cacheFor = 0,
        public ?int $maxReviews = null,
        public string $attribution = '',
        public bool $authors = true,
        public bool $link = true,
        public bool $noIndex = false,
        public array $rules = [],
        public ?string $source = null,
    ) {
    }

    /** Whether it may be kept $seconds. */
    public function allowsCaching(int $seconds): bool
    {
        return null !== $this->cacheFor && $seconds <= $this->cacheFor;
    }
}
