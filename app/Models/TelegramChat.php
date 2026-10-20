<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramChat extends Model
{
    use HasUuids;

    protected $fillable = [
        'chat_id',
        'phone_number',
        'employee_id',
        'is_admin',
        'username',
        'first_name',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
