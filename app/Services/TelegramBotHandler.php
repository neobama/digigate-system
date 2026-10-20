<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TelegramChat;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

class TelegramBotHandler
{
    public function __construct(
        private TelegramService $telegram,
        private TelegramNotifier $notifier,
    ) {}

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        if (isset($message['contact'])) {
            $this->linkContact($chatId, $message);

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $command = strtolower(strtok($text, ' ') ?: '');

        if (in_array($command, ['/start', '/start@diggatebot', '/link', '/link@diggatebot'], true)) {
            $this->telegram->sendMessage($chatId, "Halo! Untuk menerima notifikasi DigiGate, bagikan nomor HP yang terdaftar di sistem.\n\nTekan tombol di bawah, lalu pilih <b>Share Contact</b>.", [
                'keyboard' => [[
                    ['text' => '📱 Share Contact', 'request_contact' => true],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ], false);

            return;
        }

        if (in_array($command, ['/status', '/status@diggatebot'], true)) {
            $link = TelegramChat::query()->where('chat_id', $chatId)->first();
            if (! $link) {
                $this->telegram->sendMessage($chatId, 'Belum terhubung. Ketik /start lalu share contact.', [], false);

                return;
            }

            $role = $link->is_admin ? 'Admin' : 'Karyawan';
            $name = $link->employee?->name ?? ($link->first_name ?: '-');
            $this->telegram->sendMessage(
                $chatId,
                "Status link Telegram:\n• Nama: {$name}\n• HP: {$link->phone_number}\n• Peran: {$role}",
                [],
                false
            );

            return;
        }

        if (in_array($command, ['/unlink', '/unlink@diggatebot'], true)) {
            $link = TelegramChat::query()->where('chat_id', $chatId)->first();
            if ($link?->employee_id) {
                Employee::query()->where('id', $link->employee_id)->update(['telegram_chat_id' => null]);
            }
            $link?->delete();
            $this->telegram->sendMessage($chatId, 'Link Telegram dilepas. Ketik /start untuk menghubungkan lagi.', [
                'remove_keyboard' => true,
            ], false);
        }
    }

    private function linkContact(string $chatId, array $message): void
    {
        $contact = $message['contact'];
        $fromId = (string) ($message['from']['id'] ?? '');

        // Hanya terima contact milik pengirim sendiri.
        if (isset($contact['user_id']) && (string) $contact['user_id'] !== $fromId) {
            $this->telegram->sendMessage($chatId, 'Mohon share contact milik Anda sendiri.', [], false);

            return;
        }

        $phone = PhoneNumber::normalize((string) ($contact['phone_number'] ?? ''));
        if ($phone === '') {
            $this->telegram->sendMessage($chatId, 'Nomor HP tidak valid.', [], false);

            return;
        }

        $employee = Employee::query()
            ->whereNotNull('phone_number')
            ->get()
            ->first(fn (Employee $e) => PhoneNumber::normalize((string) $e->phone_number) === $phone);

        $isAdmin = $this->telegram->isAdminPhone($phone);
        $from = $message['from'] ?? [];

        if (! $employee && ! $isAdmin) {
            $this->telegram->sendMessage(
                $chatId,
                "Nomor <b>{$phone}</b> belum terdaftar di DigiGate.\nMinta admin mengisi Nomor HP karyawan di ERP, lalu /start lagi.",
                ['remove_keyboard' => true],
                false
            );

            return;
        }

        TelegramChat::query()->updateOrCreate(
            ['chat_id' => $chatId],
            [
                'phone_number' => $phone,
                'employee_id' => $employee?->id,
                'is_admin' => $isAdmin,
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? ($contact['first_name'] ?? null),
            ]
        );

        if ($employee) {
            // Pastikan 1 chat_id per employee
            Employee::query()
                ->where('telegram_chat_id', $chatId)
                ->where('id', '!=', $employee->id)
                ->update(['telegram_chat_id' => null]);

            $employee->update(['telegram_chat_id' => $chatId]);
        }

        $parts = ['✅ Terhubung ke DigiGate.'];
        if ($employee) {
            $parts[] = "Karyawan: <b>{$employee->name}</b>";
        }
        if ($isAdmin) {
            $parts[] = 'Peran: <b>Admin</b> (bisa Approve / Reject / Set Paid dari bot).';
        }
        $parts[] = 'HP: '.$phone;

        $this->telegram->sendMessage($chatId, implode("\n", $parts), [
            'remove_keyboard' => true,
        ], false);
    }

    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        $originalText = (string) ($callback['message']['text'] ?? '');

        if ($callbackId === '' || $data === '' || $chatId === '') {
            return;
        }

        $admin = TelegramChat::query()->where('chat_id', $chatId)->where('is_admin', true)->first();
        $configAdmins = array_filter(array_map('trim', explode(',', (string) config('services.telegram.admin_chat_ids', ''))));
        $isAdmin = $admin !== null || in_array($chatId, $configAdmins, true);

        if (! $isAdmin) {
            $this->telegram->answerCallbackQuery($callbackId, 'Hanya admin yang boleh aksi ini.', true);

            return;
        }

        if (! preg_match('/^(apr|rej|pay):(rb|cb|br):([0-9a-fA-F-]{36})$/', $data, $m)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Data tombol tidak valid.', true);

            return;
        }

        [, $action, $type, $id] = $m;
        $request = $this->notifier->resolveRequest($type, $id);

        if (! $request) {
            $this->telegram->answerCallbackQuery($callbackId, 'Request tidak ditemukan.', true);

            return;
        }

        try {
            if ($action === 'apr') {
                if ($request->status !== 'pending') {
                    $this->telegram->answerCallbackQuery($callbackId, 'Status sudah '.$request->status, true);

                    return;
                }
                $request->update(['status' => 'approved']);
                $label = 'Approved';
                $nextStatus = 'approved';
            } elseif ($action === 'rej') {
                if ($request->status !== 'pending') {
                    $this->telegram->answerCallbackQuery($callbackId, 'Status sudah '.$request->status, true);

                    return;
                }
                $request->update(['status' => 'rejected']);
                $label = 'Rejected';
                $nextStatus = 'rejected';
            } else { // pay
                if ($request->status !== 'approved') {
                    $this->telegram->answerCallbackQuery($callbackId, 'Set Paid hanya untuk yang sudah approved.', true);

                    return;
                }
                $request->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $label = 'Paid';
                $nextStatus = 'paid';
            }

            $this->telegram->answerCallbackQuery($callbackId, "Berhasil: {$label}");

            $suffix = "\n\n———\n✅ Diproses via Telegram: <b>{$label}</b> · ".now()->format('d/m/Y H:i');
            $keyboard = $this->notifier->approvalKeyboard($type, $id, $nextStatus);

            if ($messageId > 0) {
                $this->telegram->editMessageText($chatId, $messageId, $originalText.$suffix, $keyboard);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram callback action failed', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            $this->telegram->answerCallbackQuery($callbackId, 'Gagal memproses. Coba lagi.', true);
        }
    }
}
