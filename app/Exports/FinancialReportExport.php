<?php

namespace App\Exports;

use App\Models\Cashbon;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Reimbursement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FinancialReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnFormatting
{
    protected $month;
    protected $year;

    public function __construct($month, $year)
    {
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        // Combine all financial data
        $data = collect();

        // Incomes from Invoices
        // Include both 'paid' and 'delivered' status (delivered means already paid)
        // Use paid_date instead of invoice_date for revenue recognition
        $invoices = Invoice::whereIn('status', ['paid', 'delivered'])
            ->whereNotNull('paid_date')
            ->whereMonth('paid_date', $this->month)
            ->whereYear('paid_date', $this->year)
            ->get();

        foreach ($invoices as $invoice) {
            $data->push([
                'type' => 'Pemasukan',
                'category' => 'Invoice',
                'date' => \Carbon\Carbon::parse($invoice->paid_date),
                'description' => 'Invoice #' . $invoice->invoice_number . ' - ' . $invoice->client_name,
                'amount' => $invoice->total_amount,
                'debit' => $invoice->total_amount,
                'credit' => 0,
            ]);
        }

        // Manual Incomes
        $incomes = Income::whereMonth('income_date', $this->month)
            ->whereYear('income_date', $this->year)
            ->get();

        foreach ($incomes as $income) {
            $data->push([
                'type' => 'Pemasukan',
                'category' => 'Manual',
                'date' => \Carbon\Carbon::parse($income->income_date),
                'description' => $income->description,
                'amount' => $income->amount,
                'debit' => $income->amount,
                'credit' => 0,
            ]);
        }

        // Expenses from Reimbursements
        // Only get reimbursements that still exist and have valid employee relationship
        $reimbursements = Reimbursement::with('employee')
            ->where('status', 'paid')
            ->whereMonth('expense_date', $this->month)
            ->whereYear('expense_date', $this->year)
            ->whereHas('employee') // Only include reimbursements with valid employee
            ->get();

        foreach ($reimbursements as $reimbursement) {
            // Double check: only add if employee still exists
            if ($reimbursement->employee) {
                $data->push([
                    'type' => 'Pengeluaran',
                    'category' => 'Reimbursement',
                    'date' => \Carbon\Carbon::parse($reimbursement->expense_date),
                    'description' => $reimbursement->purpose . ' - ' . $reimbursement->employee->name,
                    'amount' => $reimbursement->amount,
                    'debit' => 0,
                    'credit' => $reimbursement->amount,
                ]);
            }
        }

        // Expenses from Cashbons
        // Only get cashbons that still exist and have valid employee relationship
        $cashbons = Cashbon::with('employee')
            ->where('status', 'paid')
            ->whereMonth('request_date', $this->month)
            ->whereYear('request_date', $this->year)
            ->whereHas('employee') // Only include cashbons with valid employee
            ->get();

        foreach ($cashbons as $cashbon) {
            // Double check: only add if employee still exists
            if ($cashbon->employee) {
                $data->push([
                    'type' => 'Pengeluaran',
                    'category' => 'Cashbon',
                    'date' => \Carbon\Carbon::parse($cashbon->request_date),
                    'description' => $cashbon->reason . ' - ' . $cashbon->employee->name,
                    'amount' => $cashbon->amount,
                    'debit' => 0,
                    'credit' => $cashbon->amount,
                ]);
            }
        }

        // Manual Expenses
        $expenses = Expense::whereMonth('expense_date', $this->month)
            ->whereYear('expense_date', $this->year)
            ->get();

        foreach ($expenses as $expense) {
            $data->push([
                'type' => 'Pengeluaran',
                'category' => 'Manual',
                'date' => \Carbon\Carbon::parse($expense->expense_date),
                'description' => $expense->description . ($expense->account_code ? ' (Kode: ' . $expense->account_code . ')' : ''),
                'amount' => $expense->amount,
                'debit' => 0,
                'credit' => $expense->amount,
            ]);
        }

        return $data->sortBy(function ($item) {
            return $item['date']->timestamp;
        })->values();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Jenis',
            'Kategori',
            'Deskripsi',
            'Debit',
            'Kredit',
            'Saldo',
        ];
    }

    protected $balance = 0;

    public function map($row): array
    {
        $this->balance += ($row['debit'] - $row['credit']);

        // Date is already Carbon instance from collection
        $date = $row['date'] instanceof \Carbon\Carbon 
            ? $row['date'] 
            : \Carbon\Carbon::parse($row['date']);

        // Return numbers as-is (not formatted strings) so Excel can properly interpret them
        return [
            $date->format('d/m/Y'),
            $row['type'],
            $row['category'],
            $row['description'],
            $row['debit'] > 0 ? (float) $row['debit'] : null,
            $row['credit'] > 0 ? (float) $row['credit'] : null,
            (float) $this->balance,
        ];
    }
    
    public function columnFormats(): array
    {
        // Format kolom Debit (E), Kredit (F), dan Saldo (G) sebagai angka dengan format Indonesia
        // Format: #,##0 untuk angka tanpa desimal dengan pemisah ribuan
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Debit
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Kredit
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Saldo
        ];
    }

    public function title(): string
    {
        return 'Laporan Keuangan ' . \Carbon\Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

