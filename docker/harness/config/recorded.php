<?php

/**
 * The platforms as their packages' tests answer them: a MockHttpClient
 * callback serving each call from the Tests/Fixtures of the package that
 * makes it - every answer written from the platform's documentation. Nothing
 * leaves the machine.
 *
 * @return Closure(string, string, array<string, mixed>): Symfony\Component\HttpClient\Response\MockResponse
 */

use Symfony\Component\HttpClient\Response\MockResponse;

$fixture = static function (string $factory, string $file, int $status = 200): MockResponse {
    return new MockResponse((string) file_get_contents(\dirname((string) (new ReflectionClass($factory))->getFileName()).'/Tests/Fixtures/'.$file), ['http_code' => $status]);
};

return static function (string $method, string $url, array $options) use ($fixture): MockResponse {
    $path = (string) parse_url($url, \PHP_URL_PATH);
    $go = 'Omnipulse\\GoogleReviews\\GoogleReviewsFactory';
    $ta = 'Omnipulse\\Tripadvisor\\TripadvisorFactory';
    $tp = 'Omnipulse\\Trustpilot\\TrustpilotFactory';

    return match (true) {
        // Google
        str_starts_with($url, 'https://places.googleapis.com/') => $fixture($go, 'place.json'),
        str_starts_with($url, 'https://oauth2.googleapis.com/') => $fixture($go, 'token.json'),
        str_starts_with($url, 'https://mybusiness.googleapis.com/') && 'PUT' === $method => $fixture($go, 'reply.json'),
        str_starts_with($url, 'https://mybusiness.googleapis.com/') => $fixture($go, 'business-reviews.json'),
        // Tripadvisor
        str_contains($url, 'tripadvisor.com') && str_ends_with($path, '/details') => $fixture($ta, 'details.json'),
        str_contains($url, 'tripadvisor.com') && str_ends_with($path, '/reviews') => $fixture($ta, 'reviews.json'),
        // Trustpilot
        str_starts_with($url, 'https://datasolutions.trustpilot.com/') => $fixture($tp, 'business-unit.json'),
        str_contains($url, 'trustpilot.com') && str_ends_with($path, '/accesstoken') => $fixture($tp, 'token.json'),
        str_contains($url, 'trustpilot.com') && str_ends_with($path, '/web-links') => $fixture($tp, 'web-links.json'),
        str_contains($url, 'trustpilot.com') && str_ends_with($path, '/reviews') => $fixture($tp, 'reviews.json'),
        str_contains($url, 'trustpilot.com') && str_ends_with($path, '/find') => $fixture($tp, 'find.json'),
        str_contains($url, 'trustpilot.com') && str_ends_with($path, '/email-invitations') => $fixture($tp, 'invitation.json', 202),
        default => new MockResponse('{"error":"not recorded"}', ['http_code' => 404]),
    };
};
