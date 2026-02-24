<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log an activity
     */
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        $user = Auth::user();
        $userName = 'System';
        
        if ($user) {
            $userName = $user->name ?? ($user->employee?->name ?? 'Unknown User');
        }
        
        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $userName,
            'action' => $action,
            'description' => $description,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
        ]);
    }

    /**
     * Log model creation
     */
    public static function logCreate(Model $model, string $modelName = null): ActivityLog
    {
        $modelName = $modelName ?? class_basename($model);
        $description = "Membuat {$modelName} baru";
        
        if (method_exists($model, 'getActivityDescription')) {
            $description = $model->getActivityDescription('create');
        }
        
        return self::log('create', $description, $model, null, $model->getAttributes());
    }

    /**
     * Log model update
     */
    public static function logUpdate(Model $model, array $oldValues, array $newValues, string $modelName = null): ActivityLog
    {
        $modelName = $modelName ?? class_basename($model);
        $description = "Memperbarui {$modelName}";
        
        if (method_exists($model, 'getActivityDescription')) {
            $description = $model->getActivityDescription('update');
        }
        
        return self::log('update', $description, $model, $oldValues, $newValues);
    }

    /**
     * Log model deletion
     */
    public static function logDelete(Model $model, string $modelName = null): ActivityLog
    {
        $modelName = $modelName ?? class_basename($model);
        $description = "Menghapus {$modelName}";
        
        if (method_exists($model, 'getActivityDescription')) {
            $description = $model->getActivityDescription('delete');
        }
        
        return self::log('delete', $description, $model, $model->getAttributes(), null);
    }

    /**
     * Log view action
     */
    public static function logView(Model $model, string $modelName = null): ActivityLog
    {
        $modelName = $modelName ?? class_basename($model);
        $description = "Melihat detail {$modelName}";
        
        return self::log('view', $description, $model);
    }

    /**
     * Log custom action
     */
    public static function logAction(string $action, string $description, ?Model $model = null): ActivityLog
    {
        return self::log($action, $description, $model);
    }

    /**
     * Log login
     */
    public static function logLogin($user): ActivityLog
    {
        return self::log('login', "User login: {$user->name}", null);
    }

    /**
     * Log logout
     */
    public static function logLogout($user): ActivityLog
    {
        return self::log('logout', "User logout: {$user->name}", null);
    }
}
