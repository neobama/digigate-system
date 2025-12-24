<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Backup Semua Data</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Fitur ini akan mengekspor semua data dari sistem ke dalam file Excel dengan beberapa sheet:
            </p>
            <ul class="list-disc list-inside space-y-2 text-gray-600 dark:text-gray-400 mb-6">
                <li><strong>Invoices</strong> - Semua data invoice (proforma, paid, delivered, cancelled)</li>
                <li><strong>Assemblies</strong> - Semua data assembly produk</li>
                <li><strong>Employees</strong> - Semua data karyawan</li>
                <li><strong>Logbooks</strong> - Semua data logbook karyawan (tanpa foto)</li>
                <li><strong>Cashbons</strong> - Semua data cashbon karyawan</li>
                <li><strong>Components</strong> - Semua data komponen</li>
            </ul>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <strong>Catatan:</strong> Foto bukti kerja tidak termasuk dalam backup. Hanya data teks yang akan diekspor.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
