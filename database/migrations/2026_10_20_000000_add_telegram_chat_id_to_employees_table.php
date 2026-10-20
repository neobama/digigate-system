<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('phone_number');
        });

        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chat_id')->unique();
            $table->string('phone_number')->nullable()->index();
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_admin')->default(false)->index();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
