<?php

namespace App\Jobs;

use App\Services\DonoChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessDonoChatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 45;

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    public function __construct(
        public string $jobId,
        public string $message,
        public string $panel,
        public array $history,
        public ?string $userId,
    ) {}

    public function handle(DonoChatService $donoChat): void
    {
        if ($this->userId) {
            Auth::onceUsingId($this->userId);
        }

        try {
            $payload = $donoChat->parseIntent($this->message, $this->history, $this->panel);
            Cache::put($this->cacheKey(), [
                'status' => 'done',
                'payload' => $payload,
            ], now()->addMinutes(10));
        } catch (\Throwable $e) {
            Log::error('ProcessDonoChatJob failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            Cache::put($this->cacheKey(), [
                'status' => 'failed',
                'payload' => [
                    'reply' => 'Maaf, Dono sedang error. Coba lagi sebentar.',
                    'intent' => 'unknown',
                    'action' => null,
                ],
            ], now()->addMinutes(10));

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put($this->cacheKey(), [
            'status' => 'failed',
            'payload' => [
                'reply' => 'Maaf, Dono sedang sibuk. Coba lagi.',
                'intent' => 'unknown',
                'action' => null,
            ],
        ], now()->addMinutes(10));
    }

    public static function cacheKeyFor(string $jobId): string
    {
        return 'dono_chat_result:'.$jobId;
    }

    private function cacheKey(): string
    {
        return self::cacheKeyFor($this->jobId);
    }
}
