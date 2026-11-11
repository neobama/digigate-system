<?php

namespace App\Services;

use App\Models\AccountCode;
use Illuminate\Support\Facades\Cache;

class AccountCodeCatalog
{
    private const CACHE_TTL = 600;

    public static function forgetCache(): void
    {
        Cache::forget('account_code_catalog.options.all');
        Cache::forget('account_code_catalog.options.active');
        Cache::forget('account_code_catalog.options.expense');
        Cache::forget('account_code_catalog.labels');
    }

    /**
     * @return array<string, string> code => "CODE — Name"
     */
    public static function options(bool $activeOnly = true): array
    {
        $key = 'account_code_catalog.options.'.($activeOnly ? 'active' : 'all');

        return Cache::remember($key, self::CACHE_TTL, function () use ($activeOnly) {
            $query = AccountCode::query()->ordered();
            if ($activeOnly) {
                $query->active();
            }

            return $query->get()
                ->mapWithKeys(fn (AccountCode $code) => [$code->code => $code->label()])
                ->all();
        });
    }

    /**
     * Options for expense forms (HPP / Beban / Lainnya only).
     *
     * @return array<string, string>
     */
    public static function expenseOptions(): array
    {
        return Cache::remember('account_code_catalog.options.expense', self::CACHE_TTL, function () {
            return AccountCode::query()
                ->active()
                ->forExpenses()
                ->ordered()
                ->get()
                ->mapWithKeys(fn (AccountCode $code) => [$code->code => $code->label()])
                ->all();
        });
    }

    public static function label(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $labels = Cache::remember('account_code_catalog.labels', self::CACHE_TTL, function () {
            return AccountCode::query()
                ->ordered()
                ->get()
                ->mapWithKeys(fn (AccountCode $row) => [$row->code => $row->label()])
                ->all();
        });

        return $labels[$code] ?? $code;
    }
}
