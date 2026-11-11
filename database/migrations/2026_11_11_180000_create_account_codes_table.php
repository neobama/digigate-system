<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 50); // Pendapatan|HPP|Beban|Lainnya
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        $now = now();
        $rows = [
            ['4100', 'Penjualan perangkat', 'Pendapatan', null, 10],
            ['4200', 'Pendapatan jasa', 'Pendapatan', null, 20],
            ['4900', 'Pendapatan lain-lain', 'Pendapatan', null, 30],
            ['5100', 'Pembelian perangkat & komponen (chassis, CPU, RAM, SSD, unit jadi)', 'HPP', 'Pembelian Macan/Maleo/Komodo (chassis) masuk ke sini.', 40],
            ['5300', 'Ongkir dan ambil barang masuk', 'HPP', null, 50],
            ['5400', 'Biaya pekerjaan lapangan', 'HPP', null, 60],
            ['6100', 'Gaji karyawan', 'Beban', null, 70],
            ['6200', 'Perjalanan dinas (UPD)', 'Beban', null, 80],
            ['6210', 'BBM dan transport lokal', 'Beban', null, 90],
            ['6220', 'Perawatan kendaraan operasional', 'Beban', null, 100],
            ['6300', 'Pengiriman ke pelanggan', 'Beban', null, 110],
            ['6400', 'Listrik dan utilitas', 'Beban', null, 120],
            ['6410', 'Langganan software dan cloud', 'Beban', null, 130],
            ['6500', 'Rumah tangga kantor', 'Beban', null, 140],
            ['6510', 'ATK dan cetak', 'Beban', null, 150],
            ['6520', 'Pemeliharaan kantor', 'Beban', null, 160],
            ['6600', 'Konsumsi dan jamuan', 'Beban', null, 170],
            ['6700', 'Pemasaran dan kemasan', 'Beban', null, 180],
            ['6800', 'Iuran dan sumbangan', 'Beban', null, 190],
            ['6900', 'Biaya bank dan marketplace', 'Beban', null, 200],
            ['7100', 'Kasbon karyawan', 'Lainnya', 'Lainnya (piutang)', 210],
            ['7300', 'Transfer antar kas dan modal', 'Lainnya', null, 220],
            ['7900', 'Lain-lain (wajib keterangan)', 'Lainnya', 'Wajib isi keterangan di deskripsi pengeluaran.', 230],
        ];

        foreach ($rows as [$code, $name, $type, $description, $sort]) {
            DB::table('account_codes')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'description' => $description,
                'is_active' => true,
                'is_system' => false,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Migrate legacy magic salary code to chart of accounts 6100.
        DB::table('expenses')->where('account_code', 'GAJI')->update(['account_code' => '6100']);
    }

    public function down(): void
    {
        DB::table('expenses')->where('account_code', '6100')->update(['account_code' => 'GAJI']);
        Schema::dropIfExists('account_codes');
    }
};
