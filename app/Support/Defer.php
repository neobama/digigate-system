<?php

namespace App\Support;

use Closure;

class Defer
{
    public static function afterResponse(Closure $callback): void
    {
        if (app()->runningInConsole()) {
            $callback();

            return;
        }

        dispatch($callback)->afterResponse();
    }
}
