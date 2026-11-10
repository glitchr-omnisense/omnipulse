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
 * A provider's calls over the application's HTTP client: omnisense/contracts'
 * JsonClient, under omnipulse's name and with its exceptions - the
 * credentials refused (401, 403) an InvalidKeyException, nothing there (404,
 * when raw() is not told to give it back) a NotFoundException, any other
 * error status a ProviderException with the provider's own message when it
 * gives one, a network failure an UnreachableException; none of them
 * carrying the request's secrets.
 *
 * Given the provider's Backoff (Config::backoff() in a factory's build()),
 * it waits while the provider asked to and reads every response's
 * Retry-After and RateLimit fields: a 429 is a UsageLimitReachedException
 * with the wait asked.
 *
 * The family's three older helpers make their calls through it: JsonApi
 * (the audience's providers), Answer::send() (the review platforms') and
 * Json::request() (the funding platforms'), each keeping its own errors
 * and messages.
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    /** @param array<string, string|list<string>> $headers sent with every request */
    public function __construct(HttpClientInterface $http, string $provider, array $headers = [], ?Backoff $backoff = null)
    {
        parent::__construct($http, $provider, $headers, $backoff);
    }

    /**
     * The call made by one of the family's helpers: every status given back
     * for it to read, the request's own headers sent as they are.
     *
     * @internal
     *
     * @param array<string, mixed> $options the HTTP client's options, "headers" among them
     */
    public function exchange(string $method, string $url, array $options): \Omnisense\Contracts\Http\Answer
    {
        $headers = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
        unset($options['headers']);
        $named = [];
        foreach ($headers as $name => $value) {
            if (null === $value) {
                continue;
            }
            if (\is_string($name)) {
                $named[$name] = \is_array($value) ? array_values(array_map(static fn ($one): string => \is_scalar($one) ? (string) $one : '', $value)) : (\is_scalar($value) ? (string) $value : '');
            } elseif (\is_string($value) && str_contains($value, ':')) {
                [$name, $value] = explode(':', $value, 2);
                $named[trim($name)] = trim($value);
            }
        }

        return $this->raw($method, $url, $options, $named, range(400, 599));
    }

    protected function answered(string $what, int $status, string $detail): \Throwable
    {
        $message = JsonApi::message(Data::of(json_decode($detail, true)));

        return match (true) {
            401 === $status, 403 === $status => InvalidKeyException::refused($this->provider, $what, $status, $message),
            404 === $status => NotFoundException::of($this->provider, $what, $message),
            default => ProviderException::answered($this->provider, $what, 200 === $status ? 'something that is not JSON' : $status, $message),
        };
    }

    protected function unreachable(string $message, \Throwable $previous): \Throwable
    {
        return new UnreachableException($message, 0, $previous);
    }
}
