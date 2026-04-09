<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->decimal('realized_amount', 15, 2)->nullable()->after('paid_at');
            $table->text('realization_notes')->nullable()->after('realized_amount');
            $table->json('realization_proof_images')->nullable()->after('realization_notes');
            $table->timestamp('realization_submitted_at')->nullable()->after('realization_proof_images');
        });
    }

    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropColumn([
                'realized_amount',
                'realization_notes',
                'realization_proof_images',
                'realization_submitted_at',
            ]);
        });
    }
};
