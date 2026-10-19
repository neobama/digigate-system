<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Happy path garansi memakai "damaged"; samakan data lama.
        DB::table('components')
            ->where('status', 'warranty_claim')
            ->update(['status' => 'damaged']);
    }

    public function down(): void
    {
        // Irreversible without knowing which rows were legacy vs newly damaged.
    }
};
