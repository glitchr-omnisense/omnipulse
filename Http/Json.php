<?php

namespace Omnipulse\Http;

use Omnipulse\Exception\ProviderException;
use Omnipulse\Exception\UnreachableException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A JSON request on the application's HTTP client, its failures turned into
 * omnipulse's exceptions; and typed reads of what came back, for providers
 * that walk an answer they do not control: the funding platforms' (GraphQL
 * and JSON). The audience's providers call through JsonApi, the review
 * platforms' through Answer.
 */
final class Json
{
    /**
     * @param array<string, mixed> $options the client's options (headers, json, query...)
     *
     * @return array<mixed> the decoded answer
     */
    public static function request(HttpClientInterface $http, string $method, string $url, array $options, string $provider, string $what): array
    {
        try {
            $response = $http->request($method, $url, $options);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            // The client's message and exception with the request's secrets masked, no trace keeping the options.
            $secrets = Redaction::secrets($url, $options);

            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s: %s', $provider, $what, Redaction::text($e->getMessage(), $secrets)), 0, Redaction::copy($e, $secrets)));
        }
        if ($status >= 300) {
            throw Redaction::withoutArguments(ProviderException::answered($provider, $what, $status));
        }
        $data = json_decode($body, true);

        return \is_array($data) ? $data : throw Redaction::withoutArguments(ProviderException::answered($provider, $what, 'something that is not JSON'));
    }

    /** The value at a path of keys; null when any step is missing. */
    public static function get(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /** A non-empty string at the path, or null. */
    public static function string(mixed $data, string|int ...$path): ?string
    {
        $value = self::get($data, ...$path);

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    public static function int(mixed $data, string|int ...$path): ?int
    {
        $value = self::get($data, ...$path);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function bool(mixed $data, string|int ...$path): bool
    {
        return true === self::get($data, ...$path);
    }

    /** @return list<mixed> the list at the path; empty when there is none */
    public static function list(mixed $data, string|int ...$path): array
    {
        $value = self::get($data, ...$path);

        return \is_array($value) ? array_values($value) : [];
    }

    /** A date at the path, or null. */
    public static function date(mixed $data, string|int ...$path): ?\DateTimeImmutable
    {
        $value = self::string($data, ...$path);
        try {
            return null === $value ? null : new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
