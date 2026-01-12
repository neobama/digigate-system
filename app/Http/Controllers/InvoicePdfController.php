<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Document;
use App\Models\Assembly;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

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

    /**
     * Save PDF to S3 and create Document record
     */
    protected function savePdfToDocument(Invoice $invoice, string $type = 'proforma'): ?Document
    {
        $kopSurat = $this->getKopSuratBase64();
        $viewName = $type === 'proforma' ? 'invoices.proforma' : 'invoices.paid';
        $fileName = $type === 'proforma' ? "proforma-{$invoice->invoice_number}.pdf" : "invoice-{$invoice->invoice_number}.pdf";
        $documentName = $type === 'proforma' ? "Proforma Invoice {$invoice->invoice_number}" : "Invoice {$invoice->invoice_number}";
        
        // Generate PDF
        $pdf = Pdf::loadView($viewName, compact('invoice', 'kopSurat'));
        $pdfContent = $pdf->output();
        
        // Determine storage disk
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $directory = 'documents/invoices';
        $filePath = $directory . '/' . $fileName;
        
        try {
            // Save PDF to S3/public storage
            Storage::disk($disk)->put($filePath, $pdfContent, 'public');
            
            // Get file info
            $fileSize = Storage::disk($disk)->size($filePath);
            $mimeType = 'application/pdf';
            
            // Check if document already exists for this invoice (by type)
            $existingDocument = Document::where('related_invoice_id', $invoice->id)
                ->where('category', 'invoice')
                ->where(function($query) use ($invoice, $type) {
                    if ($type === 'proforma') {
                        $query->where('name', 'like', "%Proforma%{$invoice->invoice_number}%")
                              ->orWhere('file_name', 'like', "proforma-{$invoice->invoice_number}.pdf");
                    } else {
                        $query->where('name', 'like', "%Invoice {$invoice->invoice_number}%")
                              ->where('name', 'not like', '%Proforma%')
                              ->orWhere('file_name', 'like', "invoice-{$invoice->invoice_number}.pdf");
                    }
                })
                ->first();
            
            if ($existingDocument) {
                // Update existing document
                $existingDocument->update([
                    'name' => $documentName,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'uploaded_by' => auth()->check() ? auth()->id() : null,
                ]);
                
                return $existingDocument;
            } else {
                // Create new document
                $document = Document::create([
                    'name' => $documentName,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'category' => 'invoice',
                    'description' => "Generated {$type} invoice for {$invoice->client_name}",
                    'related_invoice_id' => $invoice->id,
                    'uploaded_by' => auth()->check() ? auth()->id() : null,
                    'access_level' => 'private',
                ]);
                
                return $document;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to save PDF to storage: ' . $e->getMessage());
            return null;
        }
    }

    public function proforma(Invoice $invoice)
    {
        // Save PDF to S3 and create/update document
        $document = $this->savePdfToDocument($invoice, 'proforma');
        
        if (!$document) {
            \Log::error("Failed to save proforma invoice document for invoice: {$invoice->id}");
            // Fallback: generate and download directly if save fails
            $kopSurat = $this->getKopSuratBase64();
            $pdf = Pdf::loadView('invoices.proforma', compact('invoice', 'kopSurat'));
            return $pdf->download("proforma-{$invoice->invoice_number}.pdf");
        }
        
        \Log::info("Proforma invoice document saved successfully: {$document->id} for invoice: {$invoice->id}");
        
        // Download from S3
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $filePath = $document->file_path;
        
        if (Storage::disk($disk)->exists($filePath)) {
            return Storage::disk($disk)->download($filePath, $document->file_name);
        }
        
        \Log::warning("Proforma invoice file not found at: {$filePath}");
        // Fallback if file doesn't exist
        $kopSurat = $this->getKopSuratBase64();
        $pdf = Pdf::loadView('invoices.proforma', compact('invoice', 'kopSurat'));
        return $pdf->download("proforma-{$invoice->invoice_number}.pdf");
    }

    public function paid(Invoice $invoice)
    {
        // Save PDF to S3 and create/update document
        $document = $this->savePdfToDocument($invoice, 'paid');
        
        if (!$document) {
            \Log::error("Failed to save paid invoice document for invoice: {$invoice->id}");
            // Fallback: generate and download directly if save fails
            $kopSurat = $this->getKopSuratBase64();
            $pdf = Pdf::loadView('invoices.paid', compact('invoice', 'kopSurat'));
            return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
        }
        
        \Log::info("Paid invoice document saved successfully: {$document->id} for invoice: {$invoice->id}");
        
        // Download from S3
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $filePath = $document->file_path;
        
        if (Storage::disk($disk)->exists($filePath)) {
            return Storage::disk($disk)->download($filePath, $document->file_name);
        }
        
        \Log::warning("Paid invoice file not found at: {$filePath}");
        // Fallback if file doesn't exist
        $kopSurat = $this->getKopSuratBase64();
        $pdf = Pdf::loadView('invoices.paid', compact('invoice', 'kopSurat'));
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Generate nomor surat jalan: SJ/GDI/(TAHUN)/(BULAN ROMAWI)/(NOMOR URUT)
     */
    protected function generateSuratJalanNumber(): string
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;
        
        // Convert month to Roman numeral
        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $romanMonth = $romanMonths[$month] ?? 'I';
        
        // Count existing surat jalan documents this month
        $count = Document::where('category', 'surat_jalan')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
        
        $nomorUrut = $count + 1;
        
        return "SJ/GDI/{$year}/{$romanMonth}/{$nomorUrut}";
    }

    /**
     * Save Surat Jalan PDF to S3 and create Document record
     */
    protected function saveSuratJalanToDocument(Invoice $invoice): ?Document
    {
        // Get assemblies for this invoice
        $assemblies = $invoice->assemblies;
        
        if ($assemblies->isEmpty()) {
            \Log::warning("No assemblies found for invoice: {$invoice->id}");
            return null;
        }
        
        $kopSurat = $this->getKopSuratBase64();
        $suratJalanNumber = $this->generateSuratJalanNumber();
        $tanggal = Carbon::now();
        
        // Create safe filename for storage (replace / with -)
        $safeFileName = str_replace('/', '-', $suratJalanNumber);
        $fileName = "surat-jalan-{$safeFileName}.pdf";
        $documentName = "Surat Jalan {$suratJalanNumber}";
        
        // Generate PDF
        $pdf = Pdf::loadView('invoices.surat-jalan', compact(
            'invoice', 
            'kopSurat', 
            'suratJalanNumber', 
            'tanggal', 
            'assemblies'
        ));
        $pdfContent = $pdf->output();
        
        // Determine storage disk
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $directory = 'documents/surat-jalan';
        $filePath = $directory . '/' . $fileName;
        
        try {
            // Save PDF to S3/public storage
            Storage::disk($disk)->put($filePath, $pdfContent, 'public');
            
            // Get file info
            $fileSize = Storage::disk($disk)->size($filePath);
            $mimeType = 'application/pdf';
            
            // Check if document already exists for this invoice
            $existingDocument = Document::where('related_invoice_id', $invoice->id)
                ->where('category', 'surat_jalan')
                ->first();
            
            if ($existingDocument) {
                // Update existing document
                $existingDocument->update([
                    'name' => $documentName,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'uploaded_by' => auth()->check() ? auth()->id() : null,
                ]);
                
                return $existingDocument;
            } else {
                // Create new document
                $document = Document::create([
                    'name' => $documentName,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'category' => 'surat_jalan',
                    'description' => "Surat jalan untuk invoice {$invoice->invoice_number} - {$invoice->client_name}",
                    'related_invoice_id' => $invoice->id,
                    'uploaded_by' => auth()->check() ? auth()->id() : null,
                    'access_level' => 'private',
                ]);
                
                return $document;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to save surat jalan PDF to storage: ' . $e->getMessage());
            return null;
        }
    }

    public function suratJalan(Invoice $invoice)
    {
        // Check if invoice has assemblies
        if ($invoice->assemblies->isEmpty()) {
            return redirect()->back()->with('error', 'Invoice belum memiliki assembly. Silakan buat assembly terlebih dahulu.');
        }
        
        // Save PDF to S3 and create/update document
        $document = $this->saveSuratJalanToDocument($invoice);
        
        if (!$document) {
            \Log::error("Failed to save surat jalan document for invoice: {$invoice->id}");
            // Fallback: generate and download directly if save fails
            $kopSurat = $this->getKopSuratBase64();
            $suratJalanNumber = $this->generateSuratJalanNumber();
            $tanggal = Carbon::now();
            $assemblies = $invoice->assemblies;
            
            $pdf = Pdf::loadView('invoices.surat-jalan', compact(
                'invoice', 
                'kopSurat', 
                'suratJalanNumber', 
                'tanggal', 
                'assemblies'
            ));
            return $pdf->download("surat-jalan-{$suratJalanNumber}.pdf");
        }
        
        \Log::info("Surat jalan document saved successfully: {$document->id} for invoice: {$invoice->id}");
        
        // Download from S3
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
        $filePath = $document->file_path;
        
        if (Storage::disk($disk)->exists($filePath)) {
            // Create safe download filename (replace / with -)
            $safeDownloadName = str_replace('/', '-', $document->file_name);
            return Storage::disk($disk)->download($filePath, $safeDownloadName);
        }
        
        \Log::warning("Surat jalan file not found at: {$filePath}");
        // Fallback if file doesn't exist
        $kopSurat = $this->getKopSuratBase64();
        $suratJalanNumber = $this->generateSuratJalanNumber();
        $tanggal = Carbon::now();
        $assemblies = $invoice->assemblies;
        
        $pdf = Pdf::loadView('invoices.surat-jalan', compact(
            'invoice', 
            'kopSurat', 
            'suratJalanNumber', 
            'tanggal', 
            'assemblies'
        ));
        // Create safe download filename
        $safeDownloadName = str_replace('/', '-', $suratJalanNumber);
        return $pdf->download("surat-jalan-{$safeDownloadName}.pdf");
    }
}


