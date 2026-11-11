<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('device_type_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_type_id')->constrained('device_types')->cascadeOnDelete();
            $table->string('slot_key');
            $table->string('component_name');
            $table->string('label')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['device_type_id', 'slot_key']);
            $table->index('component_name');
            $table->index('slot_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_type_components');
        Schema::dropIfExists('device_types');
    }
};
