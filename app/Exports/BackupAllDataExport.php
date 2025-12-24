<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Models\Assembly;
use App\Models\Employee;
use App\Models\Logbook;
use App\Models\Cashbon;
use App\Models\Component;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class BackupAllDataExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new InvoicesSheet(),
            new AssembliesSheet(),
            new EmployeesSheet(),
            new LogbooksSheet(),
            new CashbonsSheet(),
            new ComponentsSheet(),
        ];
    }
}

class InvoicesSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Invoice::all()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client_name,
                'po_number' => $invoice->po_number ?? '',
                'invoice_date' => $invoice->invoice_date,
                'items' => json_encode($invoice->items ?? []),
                'subtotal' => collect($invoice->items ?? [])->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1)),
                'discount' => $invoice->discount ?? 0,
                'shipping_cost' => $invoice->shipping_cost ?? 0,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nomor Invoice',
            'Nama Client',
            'Nomor PO',
            'Tanggal Invoice',
            'Items (JSON)',
            'Subtotal',
            'Diskon',
            'Ongkir',
            'Total',
            'Status',
            'Dibuat',
            'Diupdate',
        ];
    }
}

class AssembliesSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Assembly::with('invoice')->get()->map(function ($assembly) {
            return [
                'id' => $assembly->id,
                'invoice_number' => $assembly->invoice->invoice_number ?? '',
                'product_type' => $assembly->product_type,
                'serial_number' => $assembly->serial_number ?? '',
                'sn_details' => json_encode($assembly->sn_details ?? []),
                'assembly_date' => $assembly->assembly_date,
                'created_at' => $assembly->created_at,
                'updated_at' => $assembly->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nomor Invoice',
            'Tipe Produk',
            'Serial Number',
            'SN Details (JSON)',
            'Tanggal Assembly',
            'Dibuat',
            'Diupdate',
        ];
    }
}

class EmployeesSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Employee::with('user')->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->name,
                'birth_date' => $employee->birth_date,
                'position' => $employee->position,
                'base_salary' => $employee->base_salary,
                'bpjs_allowance' => $employee->bpjs_allowance,
                'is_active' => $employee->is_active ? 'Ya' : 'Tidak',
                'user_email' => $employee->user->email ?? '',
                'created_at' => $employee->created_at,
                'updated_at' => $employee->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'NIK',
            'Nama',
            'Tanggal Lahir',
            'Posisi',
            'Gaji Pokok',
            'Potongan BPJS',
            'Status Aktif',
            'Email Login',
            'Dibuat',
            'Diupdate',
        ];
    }
}

class LogbooksSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Logbook::with('employee')->get()->map(function ($logbook) {
            return [
                'id' => $logbook->id,
                'employee_name' => $logbook->employee->name ?? '',
                'employee_nik' => $logbook->employee->nik ?? '',
                'log_date' => $logbook->log_date,
                'activity' => $logbook->activity,
                'photo_count' => is_array($logbook->photo) ? count($logbook->photo) : 0,
                'created_at' => $logbook->created_at,
                'updated_at' => $logbook->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Karyawan',
            'NIK Karyawan',
            'Tanggal Aktivitas',
            'Aktivitas',
            'Jumlah Foto',
            'Dibuat',
            'Diupdate',
        ];
    }
}

class CashbonsSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Cashbon::with('employee')->get()->map(function ($cashbon) {
            return [
                'id' => $cashbon->id,
                'employee_name' => $cashbon->employee->name ?? '',
                'employee_nik' => $cashbon->employee->nik ?? '',
                'amount' => $cashbon->amount,
                'reason' => $cashbon->reason,
                'request_date' => $cashbon->request_date,
                'status' => $cashbon->status,
                'created_at' => $cashbon->created_at,
                'updated_at' => $cashbon->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Karyawan',
            'NIK Karyawan',
            'Jumlah',
            'Alasan',
            'Tanggal Request',
            'Status',
            'Dibuat',
            'Diupdate',
        ];
    }
}

class ComponentsSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Component::all()->map(function ($component) {
            return [
                'id' => $component->id,
                'name' => $component->name,
                'sn' => $component->sn,
                'supplier' => $component->supplier ?? '',
                'purchase_date' => $component->purchase_date ?? '',
                'status' => $component->status,
                'created_at' => $component->created_at,
                'updated_at' => $component->updated_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Komponen',
            'Serial Number',
            'Supplier',
            'Tanggal Pembelian',
            'Status',
            'Dibuat',
            'Diupdate',
        ];
    }
}
