<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotHandler;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramService $telegram, TelegramBotHandler $handler): Response
    {
        $this->debug('webhook_hit', [
            'ip' => $request->ip(),
            'has_secret_header' => $request->hasHeader('X-Telegram-Bot-Api-Secret-Token'),
            'keys' => array_keys($request->all()),
        ]);

        if (! $telegram->enabled()) {
            $this->debug('disabled');

            return response('disabled', 503);
        }

        $secret = (string) config('services.telegram.webhook_secret', '');
        if ($secret !== '') {
            $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($secret, $header)) {
                $this->debug('unauthorized');

                return response('unauthorized', 401);
            }
        }

        $update = $request->all();

        // Jawab Telegram dulu, baru proses — hindari deadlock webhook ↔ sendMessage.
        app()->terminating(function () use ($handler, $update) {
            try {
                $handler->handle($update);
                $this->debug('handled_ok', [
                    'text' => $update['message']['text'] ?? ($update['callback_query']['data'] ?? null),
                ]);
            } catch (\Throwable $e) {
                $this->debug('handled_error', ['error' => $e->getMessage()]);
                Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
            }
        });

        return response('ok', 200);
    }

    private function debug(string $event, array $context = []): void
    {
        $line = date('c').' '.$event.' '.json_encode($context, JSON_UNESCAPED_UNICODE).PHP_EOL;
        @file_put_contents(storage_path('logs/telegram-debug.log'), $line, FILE_APPEND | LOCK_EX);
    }
}
