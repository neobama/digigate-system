<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TelegramChat;
use App\Support\Defer;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function enabled(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    public function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
    }

    public function sendMessage(string $chatId, string $text, array $replyMarkup = [], bool $defer = true): bool
    {
        if (! $this->enabled() || blank($chatId)) {
            return false;
        }

        if ($defer && ! app()->runningInConsole()) {
            Defer::afterResponse(fn () => $this->sendMessage($chatId, $text, $replyMarkup, false));

            return true;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($replyMarkup !== []) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::timeout(12)->asJson()->post($this->apiUrl('sendMessage'), $payload);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                Log::info('Telegram sendMessage ok', [
                    'chat_id' => $chatId,
                    'message_id' => $response->json('result.message_id'),
                ]);
                @file_put_contents(
                    storage_path('logs/telegram-debug.log'),
                    date('c')." send_ok chat={$chatId} mid=".$response->json('result.message_id').PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );

                return true;
            }

            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage exception', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            Http::timeout(8)->post($this->apiUrl('answerCallbackQuery'), [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery failed', ['error' => $e->getMessage()]);
        }
    }

    public function editMessageText(string $chatId, int $messageId, string $text, array $replyMarkup = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($replyMarkup !== []) {
                $payload['reply_markup'] = $replyMarkup;
            }

            Http::timeout(12)->post($this->apiUrl('editMessageText'), $payload);
        } catch (\Throwable $e) {
            Log::warning('Telegram editMessageText failed', ['error' => $e->getMessage()]);
        }
    }

    public function resolveEmployeeChatId(Employee $employee): ?string
    {
        $chatId = $employee->telegram_chat_id;
        if (blank($chatId) && filled($employee->phone_number)) {
            $chatId = TelegramChat::query()
                ->whereIn('phone_number', PhoneNumber::variants($employee->phone_number))
                ->value('chat_id');
        }

        return filled($chatId) ? (string) $chatId : null;
    }

    public function sendToEmployee(Employee $employee, string $text, array $replyMarkup = [], bool $defer = true): bool
    {
        $chatId = $this->resolveEmployeeChatId($employee);

        if (! $chatId) {
            Log::info('Telegram: employee belum link chat', [
                'employee_id' => $employee->id,
                'phone' => $employee->phone_number,
            ]);

            return false;
        }

        return $this->sendMessage($chatId, $text, $replyMarkup, $defer);
    }

    public function sendDocument(
        string $chatId,
        string $fileContents,
        string $filename,
        ?string $caption = null,
        string $mime = 'application/pdf',
        bool $defer = true,
    ): bool {
        if (! $this->enabled() || blank($chatId) || $fileContents === '') {
            return false;
        }

        if ($defer && ! app()->runningInConsole()) {
            Defer::afterResponse(fn () => $this->sendDocument($chatId, $fileContents, $filename, $caption, $mime, false));

            return true;
        }

        try {
            $request = Http::timeout(60)
                ->attach('document', $fileContents, $filename, ['Content-Type' => $mime]);

            $payload = ['chat_id' => $chatId];
            if (filled($caption)) {
                $payload['caption'] = $caption;
                $payload['parse_mode'] = 'HTML';
            }

            $response = $request->post($this->apiUrl('sendDocument'), $payload);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return true;
            }

            Log::warning('Telegram sendDocument failed', [
                'chat_id' => $chatId,
                'filename' => $filename,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendDocument exception', [
                'chat_id' => $chatId,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function sendDocumentToEmployee(
        Employee $employee,
        string $fileContents,
        string $filename,
        ?string $caption = null,
        string $mime = 'application/pdf',
        bool $defer = true,
    ): bool {
        $chatId = $this->resolveEmployeeChatId($employee);
        if (! $chatId) {
            Log::info('Telegram: employee belum link chat (document)', [
                'employee_id' => $employee->id,
                'phone' => $employee->phone_number,
            ]);

            return false;
        }

        return $this->sendDocument($chatId, $fileContents, $filename, $caption, $mime, $defer);
    }

    /** @return list<string> */
    public function adminChatIds(): array
    {
        $fromDb = TelegramChat::query()
            ->where('is_admin', true)
            ->pluck('chat_id')
            ->all();

        $fromConfig = array_filter(array_map('trim', explode(',', (string) config('services.telegram.admin_chat_ids', ''))));

        return array_values(array_unique([...$fromDb, ...$fromConfig]));
    }

    public function sendToAdmins(string $text, array $replyMarkup = [], bool $defer = false): void
    {
        $ids = $this->adminChatIds();
        if ($ids === []) {
            Log::warning('Telegram: tidak ada admin chat terhubung');

            return;
        }

        foreach ($ids as $chatId) {
            // Default sync: notifikasi approval harus sampai, jangan andalkan afterResponse Livewire.
            $this->sendMessage((string) $chatId, $text, $replyMarkup, $defer);
        }
    }

    public function isAdminPhone(string $phone): bool
    {
        $adminPhones = array_filter(array_map('trim', explode(',', (string) config('services.telegram.admin_phones', ''))));
        $variants = PhoneNumber::variants($phone);

        foreach ($adminPhones as $adminPhone) {
            if (array_intersect(PhoneNumber::variants($adminPhone), $variants)) {
                return true;
            }
        }

        return false;
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ];

        if (filled($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }

        $response = Http::timeout(20)->post($this->apiUrl('setWebhook'), $payload);

        return $response->json() ?? ['ok' => false, 'description' => $response->body()];
    }
}
