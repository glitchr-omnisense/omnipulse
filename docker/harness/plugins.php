<?php

/*
 * The omnipulse/* providers the harness installs beside the core: their
 * tests' namespace, and what their tests need besides (require-dev).
 */
return [
    'bing-webmaster' => ['Omnipulse\\BingWebmaster\\Tests\\'],
    'ga4' => ['Omnipulse\\Ga4\\Tests\\'],
    'matomo' => ['Omnipulse\\Matomo\\Tests\\'],
    'plausible' => ['Omnipulse\\Plausible\\Tests\\'],
    'search-console' => ['Omnipulse\\SearchConsole\\Tests\\'],
];
