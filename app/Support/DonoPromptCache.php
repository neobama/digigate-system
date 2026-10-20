<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

final class DonoPromptCache
{
    public const EMPLOYEE_NAMES_KEY = 'dono.employee_names.v1';

    public const EMPLOYEE_NAMES_TTL_SECONDS = 600;

    /**
     * @return array<int, string>
     */
    public static function employeeNames(): array
    {
        return Cache::remember(self::EMPLOYEE_NAMES_KEY, self::EMPLOYEE_NAMES_TTL_SECONDS, function () {
            return Employee::query()
                ->orderBy('name')
                ->limit(200)
                ->pluck('name')
                ->values()
                ->all();
        });
    }

    public static function forgetEmployeeNames(): void
    {
        Cache::forget(self::EMPLOYEE_NAMES_KEY);
    }

    public static function knowledgeBase(): string
    {
        return Cache::rememberForever('dono.knowledge_base.v3', fn () => DonoApplicationCatalog::knowledgeBaseForPrompt());
    }

    public static function jsonSchema(): string
    {
        return Cache::rememberForever('dono.json_schema.v1', fn () => DonoApplicationCatalog::jsonSchemaInstruction());
    }

    public static function taskFieldRules(): string
    {
        return Cache::rememberForever('dono.task_field_rules.v1', fn () => DonoApplicationCatalog::taskFieldRulesForPrompt());
    }

    /**
     * Tanggal konteks berubah tiap hari — cache sampai tengah malam Jakarta.
     */
    public static function taskCalendarContext(): string
    {
        $tz = 'Asia/Jakarta';
        $today = now($tz)->toDateString();
        $ttl = max(60, now($tz)->endOfDay()->diffInSeconds(now($tz)));

        return Cache::remember("dono.calendar_context.{$today}", $ttl, fn () => DonoApplicationCatalog::taskCalendarContextBlock());
    }
}
