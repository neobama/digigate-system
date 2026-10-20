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
        if (! $telegram->enabled()) {
            return response('disabled', 503);
        }

        $secret = (string) config('services.telegram.webhook_secret', '');
        if ($secret !== '') {
            $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($secret, $header)) {
                return response('unauthorized', 401);
            }
        }

        try {
            $handler->handle($request->all());
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
        }

        return response('ok', 200);
    }
}
