<?php

namespace App\Providers;

use App\Models\Cashbon;
use App\Models\Invoice;
use App\Models\Reimbursement;
use App\Models\Task;
use App\Observers\CashbonObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ReimbursementObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS jika APP_ENV production dan APP_URL menggunakan HTTPS
        if (config('app.env') === 'production' && str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Register observers
        Reimbursement::observe(ReimbursementObserver::class);
        Cashbon::observe(CashbonObserver::class);
        Task::observe(TaskObserver::class);
        Invoice::observe(InvoiceObserver::class);
        
        // Schedule task to check late and failed tasks every hour
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('tasks:check-failed')->hourly();
        });
    }
}
