<?php

namespace App\Support;

use App\Filament\Employee\Pages\Dashboard as EmployeeDashboard;
use App\Filament\Employee\Pages\MyAssembly;
use App\Filament\Employee\Pages\MyBudgetRequest;
use App\Filament\Employee\Pages\MyCashbon;
use App\Filament\Employee\Pages\MyComponent;
use App\Filament\Employee\Pages\MyLogbook;
use App\Filament\Employee\Pages\MyReimbursement;
use App\Filament\Employee\Pages\MyTasks;
use App\Filament\Employee\Pages\MyWarrantyClaim;
use App\Filament\Pages\BackupData;
use App\Filament\Pages\Dashboard as AdminDashboard;
use App\Filament\Pages\FinancialReport;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\AssemblyResource;
use App\Filament\Resources\BudgetRequestResource;
use App\Filament\Resources\CashbonResource;
use App\Filament\Resources\ComponentResource;
use App\Filament\Resources\DeviceReturnResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\LogbookResource;
use App\Filament\Resources\ReimbursementResource;
use App\Filament\Resources\SalaryPaymentResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\WarrantyClaimResource;
use App\Models\Employee;
use Illuminate\Http\Request;

final class DonoApplicationCatalog
{
    public static function resolvePanel(Request $request): string
    {
        $panel = $request->input('panel');
        if (in_array($panel, ['admin', 'employee'], true)) {
            return $panel;
        }

        $referer = (string) $request->header('Referer', '');
        if ($referer !== '' && str_contains($referer, '/employee')) {
            return 'employee';
        }

        return 'admin';
    }

    public static function knowledgeBaseForPrompt(): string
    {
        return <<<'TXT'
DigiGate adalah ERP internal. Ada dua panel:
1) Panel Admin (URL path biasanya "/", bukan diawali /employee): akses penuh ke HR, keuangan, penjualan, inventori, dokumen, dll.
2) Panel Karyawan (/employee): self-service — task/kalender sendiri, cashbon, budget request, reimbursement, warranty claim, komponen, assembly, logbook.

Ringkas modul:
- Kalender Pekerjaan / Task: assign pekerjaan ke karyawan, status, upload bukti per karyawan (pivot), notifikasi WhatsApp. Admin: buat/mengelola semua task. Karyawan: lihat task yang di-assign ke mereka, upload bukti, ubah status sesuai alur.
- Cashbon: pinjaman/uang muka; ada jatah bulanan per karyawan (di employee: Request Cashbon Baru dari halaman Cashbon).
- Budget Request: pengajuan anggaran.
- Reimbursement: klaim penggantian biaya (ada upload bon di alur admin).
- Keuangan (admin): Pemasukan (Income), Pengeluaran (Expense), Laporan Keuangan.
- Penjualan/operasional (admin): Invoice, Dokumen.
- Inventori/produksi (admin): Komponen, Assembly; Warranty Claim untuk garansi.
- HR (admin): Data Karyawan, Pembayaran Gaji (Salary Payment).
- Device Return (admin): retur perangkat — biasanya dari daftar/tracking, bukan form "create" sederhana.
- Activity Log (admin): jejak aktivitas sistem (baca saja).

Jika pengguna karyawan menanyakan fitur yang hanya di admin, jelaskan singkat dan arahkan minta bantu admin atau tim terkait — jangan mengarang URL yang tidak ada.
TXT;
    }

    public static function jsonSchemaInstruction(): string
    {
        return <<<'TXT'
Output HANYA JSON valid (tanpa markdown), schema:
{
  "reply": "balasan singkat bahasa Indonesia, ramah dan jelas",
  "intent": "create_task|create_cashbon|create_budget_request|create_reimbursement|create_expense|create_income|create_warranty_claim|create_component|create_assembly|create_invoice|create_document|create_logbook|create_salary_payment|create_employee|open_feature|app_help|unknown",
  "fields": {
    "title": "string atau kosong",
    "description": "string atau kosong",
    "start_date": "YYYY-MM-DD atau null",
    "end_date": "YYYY-MM-DD atau null",
    "start_time": "HH:mm atau null (jika task satu hari)",
    "end_time": "HH:mm atau null",
    "employee_names": ["nama karyawan yang disebut user"],
    "amount": number atau null,
    "feature_key": "untuk intent open_feature: dashboard|tasks|task_calendar|cashbon|budget_requests|reimbursement|financial_report|invoices|expenses|income|employees|warranty|components|assembly|logbook|device_returns|documents|salary_payments|activity_logs|backup"
  }
}

Aturan intent:
- create_* : user ingin membuat entri / membuka form terkait (isi fields yang bisa diinfer).
- open_feature : user ingin dibawa ke halaman/menu tertentu; set feature_key.
- app_help : user bertanya cara pakai, arti menu, atau "apa itu" — jawab di "reply" memakai basis pengetahuan; fields boleh kosong.
- unknown : tidak yakin — reply minta perjelas.

Jika pertanyaan murni informasi / bantuan (bukan buka form), gunakan app_help.
Jika panel employee tetapi user meminta fitur khusus admin (invoice, gaji, dll.), gunakan app_help dan jelaskan di reply; jangan pakai intent create_* yang tidak tersedia untuk karyawan.
TXT;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    public static function buildAction(string $intent, array $fields, string $panel): ?array
    {
        return match ($intent) {
            'create_task' => self::taskPrefillAction($fields, $panel),
            'create_cashbon' => self::labeledUrl(self::cashbonUrl($panel), 'Buka Cashbon'),
            'create_budget_request' => self::labeledUrl(self::budgetRequestUrl($panel), 'Buka Budget Request'),
            'create_reimbursement' => self::labeledUrl(self::reimbursementUrl($panel), 'Buka Reimbursement'),
            'create_expense' => $panel === 'admin'
                ? self::expenseCreateAction($fields)
                : null,
            'create_income' => $panel === 'admin'
                ? self::incomeCreateAction($fields)
                : null,
            'create_warranty_claim' => self::labeledUrl(self::warrantyClaimUrl($panel), 'Buka Warranty Claim'),
            'create_component' => self::labeledUrl(self::componentUrl($panel), 'Buka Komponen'),
            'create_assembly' => self::labeledUrl(self::assemblyUrl($panel), 'Buka Assembly'),
            'create_invoice' => $panel === 'admin'
                ? self::labeledUrl(InvoiceResource::getUrl('create', [], true, 'admin'), 'Buka Form Invoice')
                : null,
            'create_document' => $panel === 'admin'
                ? self::labeledUrl(DocumentResource::getUrl('create', [], true, 'admin'), 'Buka Form Dokumen')
                : null,
            'create_logbook' => self::labeledUrl(self::logbookUrl($panel), 'Buka Logbook'),
            'create_salary_payment' => $panel === 'admin'
                ? self::labeledUrl(SalaryPaymentResource::getUrl('create', [], true, 'admin'), 'Buka Pembayaran Gaji')
                : null,
            'create_employee' => $panel === 'admin'
                ? self::labeledUrl(EmployeeResource::getUrl('create', [], true, 'admin'), 'Buka Form Karyawan')
                : null,
            'open_feature' => self::openFeatureAction($fields['feature_key'] ?? '', $panel),
            default => null,
        };
    }

    /**
     * @return array{label: string, url: string}|null
     */
    private static function labeledUrl(?string $url, string $label): ?array
    {
        if ($url === null || $url === '') {
            return null;
        }

        return ['label' => $label, 'url' => $url];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{label: string, url: string}|null
     */
    private static function expenseCreateAction(array $fields): ?array
    {
        $prefill = array_filter([
            'description' => isset($fields['description']) ? (string) $fields['description'] : null,
            'amount' => isset($fields['amount']) ? (float) $fields['amount'] : null,
            'expense_date' => $fields['expense_date'] ?? null,
            'fund_source' => $fields['fund_source'] ?? null,
            'account_code' => $fields['account_code'] ?? null,
            'vendor_invoice_number' => $fields['vendor_invoice_number'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $query = [];
        if ($prefill !== []) {
            $query['prefill'] = base64_encode(json_encode($prefill));
        }

        return [
            'label' => 'Buka Form Pengeluaran',
            'url' => ExpenseResource::getUrl('create', $query, true, 'admin'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{label: string, url: string}|null
     */
    private static function incomeCreateAction(array $fields): ?array
    {
        $prefill = array_filter([
            'description' => isset($fields['description']) ? (string) $fields['description'] : null,
            'amount' => isset($fields['amount']) ? (float) $fields['amount'] : null,
            'income_date' => $fields['income_date'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $query = [];
        if ($prefill !== []) {
            $query['prefill'] = base64_encode(json_encode($prefill));
        }

        return [
            'label' => 'Buka Form Pemasukan',
            'url' => IncomeResource::getUrl('create', $query, true, 'admin'),
        ];
    }

    private static function cashbonUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyCashbon::getUrl([], true, 'employee')
            : CashbonResource::getUrl('index', [], true, 'admin');
    }

    private static function budgetRequestUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyBudgetRequest::getUrl([], true, 'employee')
            : BudgetRequestResource::getUrl('index', [], true, 'admin');
    }

    private static function reimbursementUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyReimbursement::getUrl([], true, 'employee')
            : ReimbursementResource::getUrl('index', [], true, 'admin');
    }

    private static function warrantyClaimUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyWarrantyClaim::getUrl([], true, 'employee')
            : WarrantyClaimResource::getUrl('create', [], true, 'admin');
    }

    private static function componentUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyComponent::getUrl([], true, 'employee')
            : ComponentResource::getUrl('create', [], true, 'admin');
    }

    private static function assemblyUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyAssembly::getUrl([], true, 'employee')
            : AssemblyResource::getUrl('create', [], true, 'admin');
    }

    private static function logbookUrl(string $panel): ?string
    {
        return $panel === 'employee'
            ? MyLogbook::getUrl([], true, 'employee')
            : LogbookResource::getUrl('create', [], true, 'admin');
    }

    /**
     * @return array{label: string, url: string}|null
     */
    private static function taskPrefillAction(array $fields, string $panel): ?array
    {
        $names = collect($fields['employee_names'] ?? [])->filter()->values()->all();
        $employeeIds = Employee::query()->whereIn('name', $names)->pluck('id')->all();

        $prefill = array_filter([
            'title' => $fields['title'] ?? null,
            'description' => $fields['description'] ?? null,
            'start_date' => $fields['start_date'] ?? null,
            'end_date' => $fields['end_date'] ?? null,
            'employees' => $employeeIds,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($panel === 'employee') {
            $payload = array_filter([
                'title' => $fields['title'] ?? '',
                'description' => $fields['description'] ?? '',
                'start_date' => $fields['start_date'] ?? null,
                'end_date' => $fields['end_date'] ?? null,
                'employees' => $employeeIds,
                'start_time' => $fields['start_time'] ?? null,
                'end_time' => $fields['end_time'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $encoded = base64_encode(json_encode($payload));

            return [
                'label' => 'Buka form buat pekerjaan (isi awal)',
                'url' => MyTasks::getUrl(['dono_prefill' => $encoded], true, 'employee'),
            ];
        }

        return [
            'label' => 'Buka Form Task (prefill)',
            'url' => TaskResource::getUrl('create', ['prefill' => base64_encode(json_encode($prefill))], true, 'admin'),
        ];
    }

    /**
     * @return array{label: string, url: string}|null
     */
    private static function openFeatureAction(string $featureKey, string $panel): ?array
    {
        $key = strtolower(trim($featureKey));

        if ($panel === 'employee') {
            return match ($key) {
                'dashboard', 'beranda' => [
                    'label' => 'Dashboard',
                    'url' => EmployeeDashboard::getUrl([], true, 'employee'),
                ],
                'tasks', 'task', 'kalender', 'pekerjaan' => [
                    'label' => 'Kalender Pekerjaan',
                    'url' => MyTasks::getUrl([], true, 'employee'),
                ],
                'cashbon' => ['label' => 'Cashbon', 'url' => MyCashbon::getUrl([], true, 'employee')],
                'budget_requests', 'budget' => ['label' => 'Budget Request', 'url' => MyBudgetRequest::getUrl([], true, 'employee')],
                'reimbursement' => ['label' => 'Reimbursement', 'url' => MyReimbursement::getUrl([], true, 'employee')],
                'warranty' => ['label' => 'Warranty Claim', 'url' => MyWarrantyClaim::getUrl([], true, 'employee')],
                'components' => ['label' => 'Komponen', 'url' => MyComponent::getUrl([], true, 'employee')],
                'assembly' => ['label' => 'Assembly', 'url' => MyAssembly::getUrl([], true, 'employee')],
                'logbook' => ['label' => 'Logbook', 'url' => MyLogbook::getUrl([], true, 'employee')],
                default => null,
            };
        }

        return match ($key) {
            'dashboard', 'beranda' => [
                'label' => 'Dashboard Admin',
                'url' => AdminDashboard::getUrl([], true, 'admin'),
            ],
            'tasks' => [
                'label' => 'Daftar Task',
                'url' => TaskResource::getUrl('index', [], true, 'admin'),
            ],
            'task_calendar' => [
                'label' => 'Kalender Pekerjaan',
                'url' => TaskResource::getUrl('calendar', [], true, 'admin'),
            ],
            'cashbon' => ['label' => 'Cashbon', 'url' => CashbonResource::getUrl('index', [], true, 'admin')],
            'budget_requests' => ['label' => 'Budget Request', 'url' => BudgetRequestResource::getUrl('index', [], true, 'admin')],
            'reimbursement' => ['label' => 'Reimbursement', 'url' => ReimbursementResource::getUrl('index', [], true, 'admin')],
            'financial_report' => ['label' => 'Laporan Keuangan', 'url' => FinancialReport::getUrl([], true, 'admin')],
            'invoices' => ['label' => 'Invoice', 'url' => InvoiceResource::getUrl('index', [], true, 'admin')],
            'expenses' => ['label' => 'Pengeluaran', 'url' => ExpenseResource::getUrl('index', [], true, 'admin')],
            'income' => ['label' => 'Pemasukan', 'url' => IncomeResource::getUrl('index', [], true, 'admin')],
            'employees' => ['label' => 'Karyawan', 'url' => EmployeeResource::getUrl('index', [], true, 'admin')],
            'warranty' => ['label' => 'Warranty Claim', 'url' => WarrantyClaimResource::getUrl('index', [], true, 'admin')],
            'components' => ['label' => 'Komponen', 'url' => ComponentResource::getUrl('index', [], true, 'admin')],
            'assembly' => ['label' => 'Assembly', 'url' => AssemblyResource::getUrl('index', [], true, 'admin')],
            'logbook' => ['label' => 'Logbook', 'url' => LogbookResource::getUrl('index', [], true, 'admin')],
            'device_returns' => ['label' => 'Device Return', 'url' => DeviceReturnResource::getUrl('index', [], true, 'admin')],
            'documents' => ['label' => 'Dokumen', 'url' => DocumentResource::getUrl('index', [], true, 'admin')],
            'salary_payments' => ['label' => 'Pembayaran Gaji', 'url' => SalaryPaymentResource::getUrl('index', [], true, 'admin')],
            'activity_logs' => ['label' => 'Activity Log', 'url' => ActivityLogResource::getUrl('index', [], true, 'admin')],
            'backup' => ['label' => 'Backup Data', 'url' => BackupData::getUrl([], true, 'admin')],
            default => null,
        };
    }
}
