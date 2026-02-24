<?php

namespace App\Traits;

use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Boot the trait
     */
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            ActivityLogService::logCreate($model);
        });

        static::updated(function (Model $model) {
            $oldValues = $model->getOriginal();
            $newValues = $model->getChanges();
            
            // Remove timestamps from old values
            unset($oldValues['updated_at']);
            unset($newValues['updated_at']);
            
            if (!empty($newValues)) {
                ActivityLogService::logUpdate($model, $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            ActivityLogService::logDelete($model);
        });
    }
}
