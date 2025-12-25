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
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Nama file asli
            $table->string('file_path'); // Path di S3
            $table->string('file_name'); // Nama file di storage
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size'); // dalam bytes
            $table->string('category')->nullable(); // Kategori: invoice, contract, certificate, etc
            $table->text('description')->nullable();
            $table->uuid('uploaded_by')->nullable(); // User yang upload
            $table->uuid('related_invoice_id')->nullable(); // Link ke invoice (optional)
            $table->enum('access_level', ['public', 'private', 'restricted'])->default('private');
            $table->timestamps();
            
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('related_invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

