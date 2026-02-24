<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        ActivityLogService::logLogin($event->user);
    }
