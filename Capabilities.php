<?php

namespace Omnipulse;

use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Dimension;
use Omnipulse\Model\Metric;
use Omnipulse\Model\ReachQuery;
use Omnisense\Contracts\Policy\Provenance;
use Omnisense\Contracts\Policy\Requirements;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;

/**
 * What a provider is, said before it is asked anything: which contracts it
 * gives as configured - the audience's reports, by which dimensions and
 * metrics; a review platform's rating, reviews, replies, invitations and
 * write link; a funding platform's profile, sponsors and tiers - and the
 * terms and limits whoever runs it must keep.
 *
 * A review platform's rules of display and caching are its Terms
 * (TermsInterface::terms()); its usage policy says them too, as rights
 * (Terms::rights()).
 *
 * The policy is omnisense/contracts' UsagePolicy (policy()). The three
 * fields written before it ($key, $terms, $limits) stay, read from the
 * policy when it is given and they are not: a provider that gives them
 * alone gets a policy made of them, its limits unknown (the words of
 * $limits in its notes).
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    /** The contracts, by family. */
    public const FAMILIES = [
        'audience' => ['searchPerformance', 'audience'],
        'reviews' => ['rating', 'reviews', 'reply', 'invite', 'writeUrl'],
        'funding' => ['profile', 'sponsors', 'tiers'],
    ];

    /**
     * Whether a key or token is needed to read it; null: not said.
     *
     * @deprecated since 1.x, read policy()->requirements->key
     */
    public ?bool $key;

    /**
     * @var list<string> the terms whoever runs it must follow (URLs)
     *
     * @deprecated since 1.x, read policy()->provenance (terms, policy)
     */
    public array $terms;

    /**
     * @var list<string> the rate limits, quotas and freshness the provider states, in words
     *
     * @deprecated since 1.x, read policy()->rateLimits
     */
    public array $limits;

    private UsagePolicy $policy;

    /**
     * @param bool            $searchPerformance it reports search queries, pages and positions (SearchPerformanceInterface)
     * @param bool            $audience          it reports visits, entry pages and sources (AudienceInterface)
     * @param list<Dimension> $dimensions        the dimensions it breaks a report down by
     * @param list<Metric>    $metrics           the metrics it counts
     * @param bool            $combined          dimensions can be combined; false: one at a time, besides the date
     * @param bool            $rating            it gives an establishment's rating (RatingInterface)
     * @param bool            $reviews           it gives its reviews (ReviewsInterface)
     * @param bool            $reply             it replies to a review as the establishment, as configured (ReplyInterface)
     * @param bool            $invite            it invites a customer to review, as configured (InviteInterface)
     * @param bool            $writeUrl          it gives the address to leave a review (WriteUrlInterface)
     * @param bool            $profile           it gives a funded account's profile (ProfileInterface)
     * @param bool            $sponsors          its public sponsors (SponsorsInterface)
     * @param bool            $tiers             the tiers it offers (TiersInterface)
     * @param bool|null       $key               whether a key or token is needed to read it; null: not said
     * @param list<string>    $terms             the terms whoever runs it must follow (URLs)
     * @param list<string>    $limits            the rate limits, quotas and freshness the provider states, in words
     * @param ?UsagePolicy    $policy            its usage policy; made of $key, $terms and $limits when null
     */
    public function __construct(
        public bool $searchPerformance = false,
        public bool $audience = false,
        public array $dimensions = [],
        public array $metrics = [],
        public bool $combined = true,
        public bool $rating = false,
        public bool $reviews = false,
        public bool $reply = false,
        public bool $invite = false,
        public bool $writeUrl = false,
        public bool $profile = false,
        public bool $sponsors = false,
        public bool $tiers = false,
        ?bool $key = null,
        array $terms = [],
        array $limits = [],
        ?UsagePolicy $policy = null,
    ) {
        $this->policy = $policy ?? self::described($key, $terms, $limits);
        $this->key = $key ?? $policy?->requirements->key;
        $this->terms = [] !== $terms || null === $policy ? $terms : array_values(array_unique(array_filter([$policy->provenance->terms, $policy->provenance->policy])));
        $this->limits = [] !== $limits || null === $policy ? $limits : array_map(static fn ($limit) => $limit->describe().(null !== $limit->note ? ' ('.$limit->note.')' : ''), $policy->rateLimits ?? []);
    }

    /** Its usage policy as the provider declares it: rate limits, rights, requirements, provenance. */
    public function policy(): UsagePolicy
    {
        return $this->policy;
    }

    /** @return list<string> what it can be asked, by contract: searchPerformance, audience, rating, reviews, reply, invite, writeUrl, profile, sponsors, tiers */
    public function contracts(): array
    {
        return array_values(array_filter(array_merge(...array_values(self::FAMILIES)), fn (string $contract): bool => $this->gives($contract)));
    }

    /** @return list<string> the families it serves: audience, reviews, funding */
    public function families(): array
    {
        return array_keys(array_filter(self::FAMILIES, fn (array $contracts): bool => [] !== array_filter($contracts, $this->gives(...))));
    }

    /** Whether it gives that contract ("audience", "rating", "sponsors"...), as configured. */
    public function gives(string $contract): bool
    {
        return match ($contract) {
            'searchPerformance' => $this->searchPerformance,
            'audience' => $this->audience,
            'rating' => $this->rating,
            'reviews' => $this->reviews,
            'reply' => $this->reply,
            'invite' => $this->invite,
            'writeUrl' => $this->writeUrl,
            'profile' => $this->profile,
            'sponsors' => $this->sponsors,
            'tiers' => $this->tiers,
            default => false,
        };
    }

    /**
     * Throws when the query asks for a dimension, a filter or a metric the
     * provider does not report, or combines dimensions it cannot.
     *
     * @param list<Metric> $metrics the metrics the report will count (the query's or the defaults)
     */
    public function check(ReachQuery $query, array $metrics, string $provider): void
    {
        $dimensions = [...$query->dimensions, ...array_map(static fn (string $d) => Dimension::from($d), array_keys($query->filters))];
        $missing = [
            ...array_map(static fn (Dimension $d) => 'the dimension "'.$d->value.'"', array_filter($dimensions, fn (Dimension $d) => !\in_array($d, $this->dimensions, true))),
            ...array_map(static fn (Metric $m) => 'the metric "'.$m->value.'"', array_filter($metrics, fn (Metric $m) => !\in_array($m, $this->metrics, true))),
        ];
        if ([] !== $missing) {
            throw new NotSupportedException(\sprintf('%s does not report %s.', $provider, implode(', ', array_unique($missing))));
        }
        if (!$this->combined && \count($query->breakdown()) > 1) {
            throw new NotSupportedException(\sprintf('%s reports one dimension at a time besides the date, not %s.', $provider, implode(' and ', array_map(static fn (Dimension $d) => $d->value, $query->breakdown()))));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contracts' => $this->contracts(),
            'families' => $this->families(),
            'searchPerformance' => $this->searchPerformance,
            'audience' => $this->audience,
            'dimensions' => array_map(static fn (Dimension $d) => $d->value, $this->dimensions),
            'metrics' => array_map(static fn (Metric $m) => $m->value, $this->metrics),
            'combined' => $this->combined,
            'rating' => $this->rating,
            'reviews' => $this->reviews,
            'reply' => $this->reply,
            'invite' => $this->invite,
            'writeUrl' => $this->writeUrl,
            'profile' => $this->profile,
            'sponsors' => $this->sponsors,
            'tiers' => $this->tiers,
            'key' => $this->key,
            'terms' => $this->terms,
            'limits' => $this->limits,
            'policy' => $this->policy->toArray(),
        ];
    }

    /**
     * The policy of a provider that declares none: its limits unknown, a key
     * required unless it says none is (the stricter reading of "not said"),
     * its terms and limits in words kept in the provenance.
     *
     * @param list<string> $terms
     * @param list<string> $limits
     */
    private static function described(?bool $key, array $terms, array $limits): UsagePolicy
    {
        $notes = array_filter([[] === $terms ? null : 'Terms: '.implode(', ', $terms), [] === $limits ? null : 'Limits: '.implode('; ', $limits)]);

        return new UsagePolicy(UsagePolicy::UNNAMED, null, requirements: new Requirements(key: false !== $key), provenance: new Provenance(terms: $terms[0] ?? null, notes: [] === $notes ? null : implode(' ', $notes)));
    }
}
