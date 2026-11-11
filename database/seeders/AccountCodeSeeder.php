<?php

namespace Database\Seeders;

use App\Models\AccountCode;
use App\Services\AccountCodeCatalog;
use Illuminate\Database\Seeder;

class AccountCodeSeeder extends Seeder
{
    public function run(): void
    {
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
            AccountCode::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }

        AccountCodeCatalog::forgetCache();
    }
}
