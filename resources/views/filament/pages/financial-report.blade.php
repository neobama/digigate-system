<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Laporan Keuangan Bulanan</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Fitur ini akan mengekspor laporan keuangan dalam format akuntansi (Debit/Kredit/Saldo) untuk periode yang dipilih.
            </p>
            <ul class="list-disc list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-6">
                <li><strong>Pemasukan:</strong> Invoice Paid + Pemasukan Manual</li>
                <li><strong>Pengeluaran:</strong> Reimbursement Paid + Cashbon Paid + Pengeluaran Manual</li>
                <li><strong>Format:</strong> Excel dengan kolom Tanggal, Jenis, Kategori, Deskripsi, Debit, Kredit, Saldo</li>
            </ul>
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Petunjuk:</strong> Klik tombol "Export ke Excel" di kanan atas untuk memilih bulan dan tahun, lalu download laporan.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
