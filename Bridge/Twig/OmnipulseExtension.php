<?php

namespace Omnipulse\Bridge\Twig;

use Omnipulse\Exception\UnreachableException;
use Omnipulse\Model\Place;
use Omnipulse\Model\Rating;
use Omnipulse\Model\Review;
use Omnipulse\Model\Terms;
use Omnipulse\Registry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A review platform's ratings and reviews in a template, always with their
 * origin, date and link - never as JSON-LD:
 *
 *     {{ omnipulse_reviews_widget('google', place_id) }}                {# @Omnipulse/reviews.html.twig: override it to dress it #}
 *     {% set rating = omnipulse_rating('trustpilot', business_unit_id) %}
 *     {% for review in omnipulse_reviews('tripadvisor', location_id, 3) %}...{% endfor %}
 *     <a href="{{ omnipulse_write_url('google', place_id) }}">Leave a review</a>
 *
 * Kept in the application's cache (symfony/cache-contracts) no longer than
 * the platform allows (Terms::$cacheFor): not at all for Google and
 * Tripadvisor, a day for Trustpilot. A platform that does not answer shows
 * nothing rather than an error.
 */
final class OmnipulseExtension extends AbstractExtension
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttl = 3600,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('omnipulse_rating', $this->rating(...)),
            new TwigFunction('omnipulse_reviews', $this->reviews(...)),
            new TwigFunction('omnipulse_terms', $this->terms(...)),
            new TwigFunction('omnipulse_write_url', $this->writeUrl(...)),
            new TwigFunction('omnipulse_reviews_widget', $this->widget(...), ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    public function rating(string $provider, Place|string $place): ?Rating
    {
        $place = \is_string($place) ? new Place($place) : $place;

        return $this->kept($provider, 'rating:'.$place->id, fn () => $this->registry->rating($provider)->rating($place));
    }

    /** @return list<Review> */
    public function reviews(string $provider, Place|string $place, int $limit = 5, ?string $language = null): array
    {
        $place = \is_string($place) ? new Place($place) : $place;

        return $this->kept($provider, 'reviews:'.$place->id.':'.$limit.':'.$language, fn () => $this->registry->reviews($provider)->reviews($place, $limit, $language)) ?? [];
    }

    public function terms(string $provider): Terms
    {
        return $this->registry->terms($provider);
    }

    /** null when the platform, which Tripadvisor and Trustpilot ask for the link, does not answer. */
    public function writeUrl(string $provider, Place|string $place): ?string
    {
        $place = \is_string($place) ? new Place($place) : $place;
        try {
            return $this->registry->writeUrl($provider)->writeUrl($place);
        } catch (UnreachableException) {
            return null;
        }
    }

    public function widget(Environment $twig, string $provider, Place|string $place, int $limit = 3, ?string $language = null): string
    {
        $capabilities = $this->registry->get($provider)->capabilities();

        return $twig->render('@Omnipulse/reviews.html.twig', [
            'provider' => $this->registry->get($provider),
            'terms' => $this->terms($provider),
            'rating' => $capabilities->rating ? $this->rating($provider, $place) : null,
            'reviews' => $capabilities->reviews ? $this->reviews($provider, $place, $limit, $language) : [],
            'write_url' => $capabilities->writeUrl ? $this->writeUrl($provider, $place) : null,
        ]);
    }

    /**
     * @template T
     *
     * @param \Closure(): T $fetch
     *
     * @return T|null
     */
    private function kept(string $provider, string $key, \Closure $fetch): mixed
    {
        $allowed = $this->registry->terms($provider)->cacheFor;
        $ttl = null === $allowed ? 0 : min($this->ttl, $allowed);
        try {
            if (null === $this->cache || $ttl <= 0) {
                return $fetch();
            }

            return $this->cache->get('omnipulse.reviews.'.hash('xxh128', $provider.'|'.$key), static function (ItemInterface $item) use ($fetch, $ttl) {
                $item->expiresAfter($ttl);

                return $fetch();
            });
        } catch (UnreachableException) {
            return null;
        }
    }
}
