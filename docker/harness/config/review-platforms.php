<?php

/**
 * The review platforms, by their package's slug, their options from the
 * environment (.env), for bin/reviews: a platform is
 * configured only when every key under "needs" is set. "example" is what
 * the bare script uses instead - the platforms then answer from their
 * packages' recorded answers. "place" is the place asked about.
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>, example: array<string, mixed>, place: array{string, string|null}, examplePlace: array{string, string|null}}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'google-reviews' => [
        'factory' => 'google-reviews',
        'needs' => ['GOOGLE_PLACES_KEY', 'GOOGLE_PLACE_ID'],
        'options' => ['api_key' => $env('GOOGLE_PLACES_KEY'), 'language' => 'fr', 'client_id' => $env('GOOGLE_CLIENT_ID'), 'client_secret' => $env('GOOGLE_CLIENT_SECRET'), 'refresh_token' => $env('GOOGLE_REFRESH_TOKEN')],
        'example' => ['api_key' => 'example', 'language' => 'fr'],
        'place' => [(string) $env('GOOGLE_PLACE_ID'), $env('GOOGLE_LOCATION')],
        'examplePlace' => ['ChIJLU7jZClu5kcR4PcOOO6p3I0', null],
    ],
    'tripadvisor' => [
        'factory' => 'tripadvisor',
        'needs' => ['TRIPADVISOR_KEY', 'TRIPADVISOR_LOCATION'],
        'options' => ['api_key' => $env('TRIPADVISOR_KEY'), 'language' => 'fr', 'referer' => $env('TRIPADVISOR_REFERER')],
        'example' => ['api_key' => 'example', 'language' => 'fr'],
        'place' => [(string) $env('TRIPADVISOR_LOCATION'), null],
        'examplePlace' => ['1234567', null],
    ],
    'trustpilot' => [
        'factory' => 'trustpilot',
        'needs' => ['TRUSTPILOT_API_KEY', 'TRUSTPILOT_BUSINESS_UNIT'],
        'options' => ['api_key' => $env('TRUSTPILOT_API_KEY'), 'api_secret' => $env('TRUSTPILOT_API_SECRET'), 'business_user_id' => $env('TRUSTPILOT_BUSINESS_USER_ID')],
        'example' => ['api_key' => 'example'],
        'place' => [(string) $env('TRUSTPILOT_BUSINESS_UNIT'), null],
        'examplePlace' => ['46d6a890000064000500e0c3', null],
    ],
];
