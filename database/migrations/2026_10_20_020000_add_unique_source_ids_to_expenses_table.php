<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep oldest expense per source; drop duplicates before unique index
        $this->dedupe('cashbon_id');
        $this->dedupe('budget_request_id');

        Schema::table('expenses', function (Blueprint $table) {
            $table->unique('cashbon_id');
            $table->unique('budget_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['cashbon_id']);
            $table->dropUnique(['budget_request_id']);
        });
    }

    private function dedupe(string $column): void
    {
        $duplicates = DB::table('expenses')
            ->select($column, DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as first_created'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $keepId = DB::table('expenses')
                ->where($column, $dup->{$column})
                ->orderBy('created_at')
                ->value('id');

            DB::table('expenses')
                ->where($column, $dup->{$column})
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};
