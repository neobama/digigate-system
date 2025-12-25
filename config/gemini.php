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
    | Model yang digunakan untuk parsing. Default: gemini-3-pro-preview
    | Options: 
    |   - gemini-3-pro-preview (Latest - terbaru)
    |   - gemini-1.5-pro (Stable - lebih akurat)
    |   - gemini-1.5-flash (Lebih cepat, lebih murah)
    |   - gemini-2.0-flash-exp (Experimental)
    |
    */
    'model' => env('GEMINI_MODEL', 'gemini-3-pro-preview'),

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

