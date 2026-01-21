<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Task extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'status',
        'created_by',
        'is_self_assigned',
        'proof_images',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_self_assigned' => 'boolean',
        'proof_images' => 'array',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_employee')
            ->withPivot('proof_images', 'notes', 'proof_uploaded_at')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check and update task status if it's past end_date
     * Returns true if status was updated
     */
    public function checkAndUpdateLateStatus(): bool
    {
        // Only check if status is pending or in_progress
        if (!in_array($this->status, ['pending', 'in_progress'])) {
            return false;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->end_date)->startOfDay();

        // If task is past end_date, check proof submission
        if ($today->greaterThan($endDate)) {
            $this->load('employees');
            $allEmployees = $this->employees;

            // Check if any employee has submitted proof on the end_date
            $employeesWithProofOnEndDate = $allEmployees->filter(function ($employee) use ($endDate) {
                $proofImagesRaw = $employee->pivot->proof_images ?? [];
                // Ensure it's an array (handle JSON string case)
                if (is_string($proofImagesRaw)) {
                    $pivotProof = json_decode($proofImagesRaw, true) ?? [];
                } elseif (is_array($proofImagesRaw)) {
                    $pivotProof = $proofImagesRaw;
                } else {
                    $pivotProof = [];
                }
                $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;

                // Check if proof exists
                if (empty($pivotProof) || !is_array($pivotProof) || count($pivotProof) === 0) {
                    return false;
                }

                // If proof_uploaded_at exists, check if it's on the same day as task end_date
                if ($proofUploadedAt) {
                    $uploadDate = Carbon::parse($proofUploadedAt)->startOfDay();
                    return $uploadDate->isSameDay($endDate);
                }

                return false;
            });

            // If no employees have submitted proof on the end_date, mark as late
            if ($employeesWithProofOnEndDate->count() === 0) {
                if ($this->status !== 'late') {
                    $this->update(['status' => 'late']);
                    return true;
                }
            } elseif ($employeesWithProofOnEndDate->count() < $allEmployees->count()) {
                // Some employees submitted proof on end_date, but not all → mark as failed
                if ($this->status !== 'failed') {
                    $this->update(['status' => 'failed']);
                    return true;
                }
            }
        }

        return false;
    }
}
