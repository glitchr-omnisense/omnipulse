<?php

namespace Omnipulse\Http;

use Omnipulse\Exception\InvalidKeyException;
use Omnipulse\Exception\NotFoundException;
use Omnipulse\Exception\ProviderException;
use Omnipulse\Exception\UnreachableException;
use Omnipulse\Support\Data;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A JSON API called through the application's HTTP client (Symfony's
 * contracts: its own client, or a MockHttpClient in a test), the answer
 * turned into Data and every failure into one of omnipulse's exceptions:
 * not reached (UnreachableException), the key refused (401, 403:
 * InvalidKeyException), nothing there (404: NotFoundException), any other
 * error or an answer that is not JSON (ProviderException) - with the
 * provider's own message when it gives one. The audience's providers call
 * through it.
 *
 * The call is the family's JsonClient's (omnisense/contracts'): given the
 * provider's Backoff, a 429 and its Retry-After, or a RateLimit field with
 * nothing left, hold the provider's next calls (UsageLimitReachedException).
 *
 * An UnreachableException's message names the method and the path, and the
 * HTTP client's reason without the address it quotes: never the query, where
 * a key may travel (Bing's apikey). Its previous exception is a copy of the
 * client's (Redaction::copy(): same class and code, the key masked in its
 * message), and neither keeps its trace's arguments, where the request's
 * query and headers stood.
 */
final class JsonApi
{
    private readonly JsonClient $client;

    public function __construct(HttpClientInterface $http, private readonly string $provider, ?Backoff $backoff = null)
    {
        $this->client = new JsonClient($http, $provider, [], $backoff);
    }

    /** @param array<string, mixed> $options the HTTP client's options (query, headers, json, body, auth_bearer...) */
    public function request(string $method, string $url, array $options = [], string $what = 'the request'): Data
    {
        try {
            $answer = $this->client->exchange($method, $url, $options);
        } catch (UnreachableException $e) {
            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s (%s %s): %s', $this->provider, $what, $method, parse_url($url, \PHP_URL_PATH) ?: '/', self::reason((string) $e->getPrevious()?->getMessage())), 0, $e->getPrevious()));
        }
        $status = $answer->status;
        $decoded = json_decode($answer->body, true);
        $message = self::message(Data::of($decoded));
        if (401 === $status || 403 === $status) {
            throw Redaction::withoutArguments(InvalidKeyException::refused($this->provider, $what, $status, $message));
        }
        if (404 === $status) {
            throw Redaction::withoutArguments(NotFoundException::of($this->provider, $what, $message));
        }
        if ($status >= 400) {
            throw Redaction::withoutArguments(ProviderException::answered($this->provider, $what, $status, $message));
        }
        if (!\is_array($decoded)) {
            throw Redaction::withoutArguments(ProviderException::answered($this->provider, $what, $status, 'an answer that is not JSON'));
        }

        return new Data($decoded);
    }

    /**
     * An HTTP client's message without the addresses it quotes: Symfony's
     * ' for "<url>"' dropped, any other address cut to its scheme, host and
     * path (no credentials, no query, no fragment) - omnisense/contracts'
     * JsonClient::reason().
     */
    public static function reason(string $message): string
    {
        return JsonClient::reason($message);
    }

    /** The error message an answer carries, under the names APIs give it. */
    public static function message(Data $answer): ?string
    {
        foreach (['error_description', 'error.message', 'message', 'Message', 'detail', 'errors.0.message', 'errors.0.detail', 'fault.faultstring', 'error'] as $path) {
            if (null !== ($message = $answer->string($path))) {
                return $message;
            }
        }

        return null;
    }
}
