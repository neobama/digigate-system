<div class="space-y-4">
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
        <div class="mb-4">
            <svg class="mx-auto h-16 w-16 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
            Dokumen Invoice Belum Tersedia
        </h3>
        <p class="text-yellow-700 dark:text-yellow-300 mb-4">
            Dokumen invoice untuk <strong>Invoice #{{ $invoice->invoice_number }}</strong> belum diupload.
            Silakan upload dokumen invoice melalui tombol "Upload Dokumen" atau melalui halaman Settings → Dokumen.
        </p>
        <div class="flex gap-2 justify-center">
            <p class="text-sm text-yellow-600 dark:text-yellow-400">
                Gunakan tombol "Upload Dokumen" di action menu untuk mengupload dokumen invoice ini.
            </p>
        </div>
    </div>
    
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-semibold mb-2">Informasi Invoice:</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium">Nomor Invoice:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $invoice->invoice_number }}</p>
            </div>
            <div>
                <span class="font-medium">Client:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $invoice->client_name }}</p>
            </div>
            <div>
                <span class="font-medium">Tanggal:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $invoice->invoice_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <span class="font-medium">Status:</span>
                <p class="text-gray-700 dark:text-gray-300">
                    <span class="px-2 py-1 rounded text-xs font-medium
                        @if($invoice->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($invoice->status === 'proforma') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @elseif($invoice->status === 'delivered') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                        @endif">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </p>
            </div>
            <div>
                <span class="font-medium">Total:</span>
                <p class="text-gray-700 dark:text-gray-300">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>
</div>

