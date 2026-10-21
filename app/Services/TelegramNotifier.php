<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BudgetRequest;
use App\Models\Cashbon;
use App\Models\Employee;
use App\Models\Reimbursement;
use App\Models\SalaryPayment;
use Illuminate\Database\Eloquent\Model;

class TelegramNotifier
{
    public function __construct(
        private TelegramService $telegram,
        private SalarySlipService $salarySlips,
    ) {}

    public function paymentRequestCreated(Model $request): void
    {
        @file_put_contents(
            storage_path('logs/telegram-debug.log'),
            date('c').' paymentRequestCreated '.class_basename($request).' '.$request->getKey().PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if (! $this->telegram->enabled()) {
            return;
        }

        $type = $this->typeKey($request);
        if (! $type) {
            return;
        }

        if ($request instanceof Cashbon && $request->is_term_loan) {
            $request->loadMissing('employee');
            if ($request->employee) {
                $this->telegram->sendToEmployee($request->employee, $this->termLoanMessage($request));
            }

            return;
        }

        $request->loadMissing('employee');
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        $text = $this->adminCreatedMessage($request, $employee);
        $this->telegram->sendToAdmins($text, $this->approvalKeyboard($type, (string) $request->id, 'pending'));
    }

    public function paymentRequestStatusChanged(Model $request, string $newStatus): void
    {
        if (! $this->telegram->enabled()) {
            return;
        }

        if (! in_array($newStatus, ['approved', 'rejected', 'paid'], true)) {
            return;
        }

        $request->loadMissing('employee');
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        if ($request instanceof Cashbon && $request->is_term_loan) {
            return;
        }

        $message = $this->employeeStatusMessage($request, $newStatus);
        if ($message) {
            $this->telegram->sendToEmployee($employee, $message);
        }
    }

    public function sendDailyAttendanceSummary(\Carbon\Carbon $date): void
    {
        if (! $this->telegram->enabled()) {
            return;
        }

        $service = app(AttendanceWorkingHoursService::class);
        $summaries = $service->summariesForDate($date);
        $dateLabel = $date->copy()->timezone($service->timezone())->translatedFormat('l, d M Y');

        $complete = [];
        $incomplete = [];
        $absent = [];

        foreach ($summaries as $row) {
            $name = htmlspecialchars((string) $row['employee']->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $tapIn = $row['tap_in'];
            $tapOut = $row['tap_out'];

            if (! $tapIn && ! $tapOut) {
                $absent[] = "• {$name}";

                continue;
            }

            $in = $tapIn?->recorded_at?->timezone($service->timezone())->format('H:i') ?? '—';
            $out = $tapOut?->recorded_at?->timezone($service->timezone())->format('H:i') ?? '—';
            $flags = [];
            foreach ([$tapIn, $tapOut] as $tap) {
                if ($tap && ! $tap->is_within_radius) {
                    $flags[] = 'luar radius';
                    break;
                }
            }
            $flag = $flags !== [] ? ' (luar radius)' : '';
            $line = "• {$name} — {$in} → {$out}{$flag}";

            if ($tapIn && $tapOut) {
                $complete[] = $line;
            } else {
                $incomplete[] = $line;
            }
        }

        $text = "📋 <b>Ringkasan Absensi</b>\n{$dateLabel}\n\n"
            .'<b>Lengkap ('.count($complete).")</b>\n"
            .($complete !== [] ? implode("\n", $complete) : '—')
            ."\n\n<b>Belum lengkap (".count($incomplete).")</b>\n"
            .($incomplete !== [] ? implode("\n", $incomplete) : '—')
            ."\n\n<b>Tidak absen (".count($absent).")</b>\n"
            .($absent !== [] ? implode("\n", $absent) : '—');

        $chunk = '';
        foreach (preg_split("/\n/", $text) ?: [] as $line) {
            $next = $chunk === '' ? $line : $chunk."\n".$line;
            if (mb_strlen($next) > 3800) {
                $this->telegram->sendToAdmins($chunk);
                $chunk = $line;
            } else {
                $chunk = $next;
            }
        }

        if ($chunk !== '') {
            $this->telegram->sendToAdmins($chunk);
        }
    }

    public function attendanceStatusChanged(Attendance $attendance): void
    {
        if (! $this->telegram->enabled()) {
            return;
        }

        if (! in_array($attendance->status, ['approved', 'rejected'], true)) {
            return;
        }

        $attendance->loadMissing('employee');
        $employee = $attendance->employee;
        if (! $employee) {
            return;
        }

        $recordedAt = $attendance->recorded_at?->format('d/m/Y H:i') ?? '-';
        $typeLabel = $attendance->type?->label() ?? 'Absensi';

        $text = "📸 <b>Update Absensi Anda</b>\n\n"
            ."Jenis: {$typeLabel}\n"
            ."Waktu absen: {$recordedAt}\n";

        if ($attendance->status === 'approved') {
            $text .= "✅ Status: <b>Diterima</b>\n\nAbsensi Anda telah diverifikasi admin.";
        } else {
            $text .= "❌ Status: <b>Ditolak</b>\n\n";
            if ($attendance->admin_notes) {
                $text .= "Alasan: {$attendance->admin_notes}";
            }
        }

        $this->telegram->sendToEmployee($employee, $text);
    }

    public function salaryPaid(SalaryPayment $salaryPayment): void
    {
        if (! $this->telegram->enabled()) {
            return;
        }

        $salaryPayment->loadMissing('employee', 'adjustments');
        $employee = $salaryPayment->employee;
        if (! $employee) {
            return;
        }

        $period = str_pad((string) $salaryPayment->month, 2, '0', STR_PAD_LEFT).'/'.$salaryPayment->year;
        $net = number_format((float) $salaryPayment->net_salary, 0, ',', '.');
        $caption = "💰 <b>Gaji Sudah Dibayar</b>\n\n"
            .'Karyawan: '.e($employee->name)."\n"
            ."Periode: {$period}\n"
            ."Jumlah: Rp {$net}\n\n"
            .'Slip gaji terlampir.';

        try {
            $pdf = $this->salarySlips->generatePdfBinary($salaryPayment);
            $filename = $this->salarySlips->filename($salaryPayment);

            if (! $this->telegram->sendDocumentToEmployee($employee, $pdf, $filename, $caption)) {
                // Fallback teks jika kirim PDF gagal / belum link
                $this->telegram->sendToEmployee($employee, $caption."\n\n(Slip PDF gagal dikirim; cek di panel karyawan.)");
            }
        } catch (\Throwable $e) {
            \Log::error('Gagal generate/kirim slip gaji Telegram', [
                'salary_payment_id' => $salaryPayment->id,
                'error' => $e->getMessage(),
            ]);
            $this->telegram->sendToEmployee($employee, $caption."\n\n(Slip PDF gagal dibuat; cek di panel karyawan.)");
        }
    }

    public function approvalKeyboard(string $type, string $id, string $status): array
    {
        $rows = [];

        if ($status === 'pending') {
            $rows[] = [
                ['text' => '✅ Approve', 'callback_data' => "apr:{$type}:{$id}"],
                ['text' => '❌ Reject', 'callback_data' => "rej:{$type}:{$id}"],
            ];
        }

        if ($status === 'approved') {
            $rows[] = [
                ['text' => '💵 Set Paid', 'callback_data' => "pay:{$type}:{$id}"],
            ];
        }

        return $rows === [] ? [] : ['inline_keyboard' => $rows];
    }

    public function typeKey(Model $request): ?string
    {
        return match (true) {
            $request instanceof Reimbursement => 'rb',
            $request instanceof Cashbon => 'cb',
            $request instanceof BudgetRequest => 'br',
            default => null,
        };
    }

    public function resolveRequest(string $type, string $id): ?Model
    {
        return match ($type) {
            'rb' => Reimbursement::query()->find($id),
            'cb' => Cashbon::query()->find($id),
            'br' => BudgetRequest::query()->find($id),
            default => null,
        };
    }

    private function adminCreatedMessage(Model $request, Employee $employee): string
    {
        $name = e($employee->name);

        if ($request instanceof Reimbursement) {
            $date = $this->formatDate($request->expense_date);

            return "📋 <b>Reimbursement Baru</b>\n\n"
                ."Karyawan: {$name}\n"
                .'Keperluan: '.e((string) $request->purpose)."\n"
                ."Tanggal: {$date}\n"
                .'Jumlah: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n"
                .'Status: Pending'
                .($request->description ? "\nKeterangan: ".e((string) $request->description) : '')
                ."\n\nBalas lewat tombol di bawah.";
        }

        if ($request instanceof Cashbon) {
            return "💰 <b>Cashbon Baru</b>\n\n"
                ."Karyawan: {$name}\n"
                .'Alasan: '.e((string) $request->reason)."\n"
                .'Tanggal Request: '.$this->formatDate($request->request_date)."\n"
                .'Jumlah: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n"
                .($request->installment_months ? "Cicilan: {$request->installment_months} bulan\n" : '')
                ."Status: Pending\n\nBalas lewat tombol di bawah.";
        }

        if ($request instanceof BudgetRequest) {
            return "📋 <b>Request Anggaran Baru</b>\n\n"
                ."Karyawan: {$name}\n"
                .'Nama Anggaran: '.e((string) $request->budget_name)."\n"
                .'Detail: '.e((string) $request->budget_detail)."\n"
                .'Rekening: '.e((string) $request->recipient_account)."\n"
                .'Nominal: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n"
                .'Tanggal Request: '.$this->formatDate($request->request_date)."\n"
                ."Status: Pending\n\nBalas lewat tombol di bawah.";
        }

        return 'Request baru menunggu approval.';
    }

    private function employeeStatusMessage(Model $request, string $status): ?string
    {
        if ($request instanceof Reimbursement) {
            $text = "📋 <b>Update Reimbursement Anda</b>\n\n"
                ."Keperluan: {$request->purpose}\n"
                .'Tanggal: '.$this->formatDate($request->expense_date)."\n"
                .'Jumlah: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n";

            return $text.$this->statusLine($status, [
                'approved' => "Reimbursement Anda telah disetujui dan akan segera diproses.",
                'rejected' => 'Reimbursement Anda ditolak.',
                'paid' => 'Reimbursement Anda sudah dibayar.',
            ]);
        }

        if ($request instanceof Cashbon) {
            $text = "💰 <b>Update Cashbon Anda</b>\n\n"
                ."Alasan: {$request->reason}\n"
                .'Jumlah: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n";

            $approvedExtra = $request->installment_months
                ? "Cashbon akan dicicil selama {$request->installment_months} bulan."
                : 'Cashbon akan langsung dipotong di bulan pertama.';

            return $text.$this->statusLine($status, [
                'approved' => $approvedExtra,
                'rejected' => 'Cashbon Anda ditolak.',
                'paid' => 'Cashbon Anda sudah dibayar.',
            ]);
        }

        if ($request instanceof BudgetRequest) {
            $text = "📋 <b>Update Request Anggaran Anda</b>\n\n"
                ."Nama Anggaran: {$request->budget_name}\n"
                .'Nominal: Rp '.number_format((float) $request->amount, 0, ',', '.')."\n";

            return $text.$this->statusLine($status, [
                'approved' => 'Request anggaran Anda telah disetujui.',
                'rejected' => 'Request anggaran Anda telah ditolak.',
                'paid' => "Anggaran sudah ditransfer ke: {$request->recipient_account}\n\nSetelah memakai dana, wajib input realisasi di menu Request Anggaran.",
            ]);
        }

        return null;
    }

    private function termLoanMessage(Cashbon $cashbon): string
    {
        $text = "💰 <b>Pinjaman Term</b>\n\n"
            ."Keterangan: {$cashbon->reason}\n"
            .'Total: Rp '.number_format((float) $cashbon->amount, 0, ',', '.')."\n"
            .'Mulai potong gaji: '.$this->formatDate($cashbon->request_date)."\n";

        if ($cashbon->installment_months) {
            $perMonth = (float) $cashbon->amount / (int) $cashbon->installment_months;
            $text .= "Cicilan: {$cashbon->installment_months} bulan (Rp ".number_format($perMonth, 0, ',', '.')."/bulan)\n";
        } else {
            $text .= "Potongan: sekali di bulan pertama.\n";
        }

        return $text."\nRincian potongan akan muncul di slip gaji.";
    }

    /** @param  array<string, string>  $messages */
    private function statusLine(string $status, array $messages): string
    {
        return match ($status) {
            'approved' => "✅ Status: <b>Disetujui</b>\n\n".($messages['approved'] ?? ''),
            'rejected' => "❌ Status: <b>Ditolak</b>\n\n".($messages['rejected'] ?? ''),
            'paid' => "✅ Status: <b>Sudah Dibayar</b>\n\n".($messages['paid'] ?? ''),
            default => '',
        };
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->format('d/m/Y');
        }

        if (is_string($date) && $date !== '') {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        }

        return '-';
    }
}
