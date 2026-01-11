<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfController extends Controller
{
    /**
     * Get kop surat image as base64 for PDF
     */
    protected function getKopSuratBase64(): ?string
    {
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $path = 'assets/digigate-kop.png';
        
        try {
            if (Storage::disk($disk)->exists($path)) {
                $imageContent = Storage::disk($disk)->get($path);
                $base64 = base64_encode($imageContent);
                return 'data:image/png;base64,' . $base64;
            }
        } catch (\Exception $e) {
            // Fallback to URL if download fails
            try {
                $url = Storage::disk($disk)->url($path);
                return $url;
            } catch (\Exception $e2) {
                return null;
            }
        }
        
        return null;
    }

    public function proforma(Invoice $invoice)
    {
        $kopSurat = $this->getKopSuratBase64();
        $pdf = Pdf::loadView('invoices.proforma', compact('invoice', 'kopSurat'));

        return $pdf->download("proforma-{$invoice->invoice_number}.pdf");
    }

    public function paid(Invoice $invoice)
    {
        $kopSurat = $this->getKopSuratBase64();
        $pdf = Pdf::loadView('invoices.paid', compact('invoice', 'kopSurat'));

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}


