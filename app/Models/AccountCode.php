<?php

namespace App\Models;

use App\Services\AccountCodeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountCode extends Model
{
    use HasUuids;

    public const TYPE_PENDAPATAN = 'Pendapatan';

    public const TYPE_HPP = 'HPP';

    public const TYPE_BEBAN = 'Beban';

    public const TYPE_LAINNYA = 'Lainnya';

    public const TYPES = [
        self::TYPE_PENDAPATAN => 'Pendapatan',
        self::TYPE_HPP => 'HPP',
        self::TYPE_BEBAN => 'Beban',
        self::TYPE_LAINNYA => 'Lainnya',
    ];

    /** Legacy + chart code used for payroll expenses. */
    public const SALARY_CODES = ['6100', 'GAJI'];

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => AccountCodeCatalog::forgetCache());
        static::deleted(fn () => AccountCodeCatalog::forgetCache());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function scopeForExpenses(Builder $query): Builder
    {
        return $query->whereIn('type', [
            self::TYPE_HPP,
            self::TYPE_BEBAN,
            self::TYPE_LAINNYA,
        ]);
    }

    public function label(): string
    {
        return "{$this->code} — {$this->name}";
    }

    public static function isSalaryCode(?string $code): bool
    {
        return filled($code) && in_array($code, self::SALARY_CODES, true);
    }
}
