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
    | Model yang digunakan untuk parsing. Default: gemini-3-pro
    | Options: 
    |   - gemini-3-pro (Latest - terbaru)
    |   - gemini-1.5-pro (Stable - lebih stabil)
    |   - gemini-1.5-flash-latest (Lebih cepat)
    |
    */
    'model' => env('GEMINI_MODEL', 'gemini-3-pro'),

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

