<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API Key
    |--------------------------------------------------------------------------
    |
    | API key untuk Google Gemini API. Dapatkan dari:
    | https://makersuite.google.com/app/apikey
    |
    */
    'api_key' => env('GEMINI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | Model yang digunakan untuk parsing. Default: gemini-1.5-flash
    | Options: gemini-1.5-flash, gemini-1.5-pro
    |
    */
    'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout dalam detik untuk API request
    |
    */
    'timeout' => env('GEMINI_TIMEOUT', 30),
];

