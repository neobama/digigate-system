<?php

namespace App\Support;

use Closure;

class Defer
{
    /**
     * Jalankan callback setelah response dikirim, tanpa bergantung pada queue worker.
     */
    public static function afterResponse(Closure $callback): void
    {
        if (app()->runningInConsole()) {
            $callback();

            return;
        }

        // Langsung terminating — hindari dispatch()->afterResponse() yang bisa
        // bermasalah di request Livewire/Filament + QUEUE_CONNECTION=database.
        app()->terminating($callback);
    }
}
