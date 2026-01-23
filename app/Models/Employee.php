<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'nik', 'name', 'birth_date', 'position', 'phone_number', 'base_salary', 'bpjs_allowance', 'is_active'];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function cashbons(): HasMany
    {
        return $this->hasMany(Cashbon::class);
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_employee');
    }

    /**
     * Get maximum cashbon allowance per month (35% of base salary)
     */
    public function getMaxCashbonPerMonth(): float
    {
        return $this->base_salary * 0.35;
    }

    /**
     * Get total cashbon amount for current month (pending + approved + paid)
     */
    public function getCurrentMonthCashbonTotal(): float
    {
        $currentMonth = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        return $this->cashbons()
            ->whereBetween('request_date', [$currentMonth, $currentMonthEnd])
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('amount');
    }

    /**
     * Get remaining cashbon allowance for current month
     */
    public function getRemainingCashbonAllowance(): float
    {
        $maxAllowance = $this->getMaxCashbonPerMonth();
        $used = $this->getCurrentMonthCashbonTotal();
        $remaining = $maxAllowance - $used;
        
        return max(0, $remaining); // Don't return negative
    }

    /**
     * Check if cashbon amount exceeds monthly allowance
     */
    public function canRequestCashbon(float $amount): bool
    {
        $remaining = $this->getRemainingCashbonAllowance();
        return $amount <= $remaining;
    }
}
