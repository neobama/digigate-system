<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vendor_invoice_number')->nullable(); // Nomor invoice dari vendor (opsional)
            $table->text('description'); // Deskripsi pengeluaran
            $table->string('account_code')->nullable(); // Kode akun
            $table->enum('fund_source', ['kas_kecil', 'bank_perusahaan'])->default('kas_kecil'); // Sumber dana
            $table->date('expense_date'); // Tanggal pengeluaran
            $table->decimal('amount', 15, 2); // Nominal pengeluaran
            $table->string('proof_of_payment')->nullable(); // Upload bukti (opsional)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

