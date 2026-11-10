<?php

/*
 * The omnipulse/* providers the harness installs beside the core
 * (omnipulse/<slug>, github.com/glitchr-omnisense/omnipulse-<slug>): their
 * tests' namespace, what their tests need besides (require-dev), and
 * their factory.
 */
return [
    // audience
    'bing-webmaster' => ['Omnipulse\\BingWebmaster\\Tests\\', 'factory' => 'Omnipulse\\BingWebmaster\\BingWebmasterFactory'],
    'ga4' => ['Omnipulse\\Ga4\\Tests\\', 'factory' => 'Omnipulse\\Ga4\\Ga4Factory'],
    'matomo' => ['Omnipulse\\Matomo\\Tests\\', 'factory' => 'Omnipulse\\Matomo\\MatomoFactory'],
    'plausible' => ['Omnipulse\\Plausible\\Tests\\', 'factory' => 'Omnipulse\\Plausible\\PlausibleFactory'],
    'search-console' => ['Omnipulse\\SearchConsole\\Tests\\', 'factory' => 'Omnipulse\\SearchConsole\\SearchConsoleFactory'],
    // reviews
    'google-reviews' => ['Omnipulse\\GoogleReviews\\Tests\\', 'factory' => 'Omnipulse\\GoogleReviews\\GoogleReviewsFactory'],
    'tripadvisor' => ['Omnipulse\\Tripadvisor\\Tests\\', 'factory' => 'Omnipulse\\Tripadvisor\\TripadvisorFactory'],
    'trustpilot' => ['Omnipulse\\Trustpilot\\Tests\\', 'factory' => 'Omnipulse\\Trustpilot\\TrustpilotFactory'],
    // funding
    'github-sponsors' => ['Omnipulse\\GitHubSponsors\\Tests\\', 'factory' => 'Omnipulse\\GitHubSponsors\\GitHubSponsorsFactory'],
    'liberapay' => ['Omnipulse\\Liberapay\\Tests\\', 'factory' => 'Omnipulse\\Liberapay\\LiberapayFactory'],
    'opencollective' => ['Omnipulse\\OpenCollective\\Tests\\', 'factory' => 'Omnipulse\\OpenCollective\\OpenCollectiveFactory'],
];
