<?php

namespace Omnipulse\Testing;

/**
 * An HTTP client that answers from what it was given - a callable of the
 * method, the URL and the options, returning [status, body, headers] - and
 * keeps every request made ($requests): a provider's calls checked with no
 * network. omnisense/contracts' RecordedHttpClient, under omnipulse's name,
 * with omnipulse's routes(): by method and the end of the path.
 */
final class RecordedHttpClient extends \Omnisense\Contracts\Testing\RecordedHttpClient
{
    /**
     * Answers by route: "GET /api/v2/query" => the body (status 200) or
     * [status, body] or [status, body, headers] (a 429's Retry-After) - the
     * first route whose method matches and whose path ends the URL's path;
     * 404 for anything not recorded.
     *
     * @param array<string, string|array{0: int, 1: string, 2?: array<string, list<string>>}> $routes
     */
    public static function routes(array $routes): static
    {
        return new static(static function (string $method, string $url) use ($routes): array {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            foreach ($routes as $route => $answer) {
                [$routeMethod, $routePath] = explode(' ', $route, 2) + [1 => ''];
                if ($routeMethod === $method && str_ends_with($path, $routePath)) {
                    return \is_string($answer) ? [200, $answer] : $answer;
                }
            }

            return [404, '{"error":"not recorded: '.$method.' '.$path.'"}'];
        });
    }
}
