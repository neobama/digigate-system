<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {url?}';

    protected $description = 'Register Telegram bot webhook URL';

    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN belum diisi.');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/').'/telegram/webhook';
        $secret = config('services.telegram.webhook_secret');

        $result = $telegram->setWebhook($url, $secret ?: null);

        if (($result['ok'] ?? false) === true) {
            $this->info('Webhook OK: '.$url);

            return self::SUCCESS;
        }

        $this->error('Gagal set webhook: '.json_encode($result));

        return self::FAILURE;
    }
}
