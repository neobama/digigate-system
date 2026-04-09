<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API Key
    |--------------------------------------------------------------------------
    |
    | API key: https://aistudio.google.com/apikey
    |
    | GOOGLE_API_KEY dipakai jika GEMINI_API_KEY kosong (nama umum di dokumentasi Google).
    |
    | Setelah mengubah .env di server, jalankan: php artisan config:clear
    | (wajib jika pernah menjalankan config:cache — tanpa ini, key baru tidak terbaca).
    |
    */
    'api_key' => env('GEMINI_API_KEY') ?: env('GOOGLE_API_KEY') ?: '',

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | Model harus ada di Generative Language API. Contoh: gemini-2.0-flash, gemini-1.5-flash, gemini-1.5-pro
    |
    */
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

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
