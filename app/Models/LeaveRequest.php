<?php

namespace App\Models;

use App\Enums\LeaveRequestType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LeaveRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'hours',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'type' => LeaveRequestType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'hours' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function countsAsPresentDay(): bool
    {
        return $this->status === 'approved'
            && ($this->type?->countsAsPresentDay() ?? false);
    }

    /**
     * @return list<string> Y-m-d dates inclusive within the given month
     */
    public function datesInMonth(int $month, int $year): array
    {
        if (! $this->countsAsPresentDay()) {
            return [];
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $start = Carbon::parse($this->start_date)->startOfDay()->max($monthStart);
        $end = Carbon::parse($this->end_date)->startOfDay()->min($monthEnd);

        if ($start->greaterThan($end)) {
            return [];
        }

        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
