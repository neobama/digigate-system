<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfController extends Controller
{
    public function proforma(Invoice $invoice)
    {
        $pdf = Pdf::loadView('invoices.proforma', compact('invoice'));

        return $pdf->download("proforma-{$invoice->invoice_number}.pdf");
    }

    public function paid(Invoice $invoice)
    {
        $pdf = Pdf::loadView('invoices.paid', compact('invoice'));

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}


