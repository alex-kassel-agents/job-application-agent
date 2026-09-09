<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chromium/Edge Binary Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to a Chromium-based browser (msedge, chrome, chromium, brave).
    | When left null, the BrowserFinder service will automatically scan standard
    | system paths across Windows, Linux, and macOS.
    |
    */
    'browser_binary' => env('JOB_APPLICATION_BROWSER_BIN', env('CHROME_BIN', env('BROWSER_BIN'))),

    /*
    |--------------------------------------------------------------------------
    | Template Directory
    |--------------------------------------------------------------------------
    |
    | Directory containing DIN 5008 HTML, JSON, and Markdown templates.
    |
    */
    'templates_path' => env('JOB_APPLICATION_TEMPLATES_PATH', __DIR__.'/../resources/templates'),

    /*
    |--------------------------------------------------------------------------
    | Candidate Profile Path
    |--------------------------------------------------------------------------
    |
    | Path to candidate profile files (e.g. from private identity package).
    |
    */
    'profile_path' => env('JOB_APPLICATION_PROFILE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | DIN 5008 Settings
    |--------------------------------------------------------------------------
    |
    | Default city, standard form (A or B), and signoff conventions.
    |
    */
    'din5008' => [
        'form' => 'B',
        'default_city' => env('JOB_APPLICATION_DEFAULT_CITY', null),
        'default_signoff' => 'Mit freundlichen Grüßen',
    ],
];
