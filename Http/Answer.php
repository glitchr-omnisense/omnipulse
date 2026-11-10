<?php

namespace Omnipulse\Http;

use Omnipulse\Exception\ProviderException;
use Omnipulse\Exception\UnreachableException;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A provider's answer, read whole: its status, headers (lower-case names)
 * and body. send() is the one place the review platforms' providers call
 * through: no connection, a timeout, a server error or a quota exceeded is
 * an UnreachableException - the provider said nothing about the request -
 * and anything else comes back for the provider to read. The request's secrets
 * stay out of what it throws: the HTTP client's message with the key's
 * parameters masked (Redaction::text(), api_key=***), its exception wrapped
 * as a copy (Redaction::copy()), no trace keeping the arguments where the
 * options stood.
 *
 * The call is the family's JsonClient's (omnisense/contracts'): given the
 * provider's Backoff, a 429 and its Retry-After, or a RateLimit field with
 * nothing left, hold the provider's next calls (UsageLimitReachedException)
 * - without one, a 429 is unreachable as before.
 */
final readonly class Answer
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        private string $provider,
    ) {
    }

    /** @param array<string, mixed> $options the HTTP client's options: body, json, query, headers, timeout */
    public static function send(HttpClientInterface $http, string $provider, string $method, string $url, array $options = [], ?Backoff $backoff = null): self
    {
        try {
            $answer = (new JsonClient($http, $provider, [], $backoff))->exchange($method, $url, $options + ['timeout' => 10]);
        } catch (UnreachableException $e) {
            throw Redaction::withoutArguments(UnreachableException::from($provider, 'No answer: '.$e->getPrevious()?->getMessage(), null, $e->getPrevious()));
        }
        [$status, $headers, $body] = [$answer->status, $answer->headers, $answer->body];
        if ($status >= 500 || 429 === $status) {
            throw Redaction::withoutArguments(UnreachableException::from($provider, \sprintf('HTTP %d: %s', $status, mb_substr(trim($body), 0, 200)), (string) $status));
        }

        return new self($status, $headers, $body, $provider);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * The body as a JSON object (or list).
     *
     * @return array<mixed>
     */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!\is_array($data)) {
            throw ProviderException::from($this->provider, \sprintf('HTTP %d, not a JSON object: %s', $this->status, mb_substr(trim($this->body), 0, 200)));
        }

        return $data;
    }
}
