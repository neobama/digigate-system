<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiUrl;
    private string $apiKey;
    private string $session;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'http://10.11.10.55:3000/api/sendText');
        $this->apiKey = config('services.whatsapp.api_key', '1d7a97d985a245f699b0eb42567670aa');
        $this->session = config('services.whatsapp.session', 'default');
    }

    /**
     * Format nomor telepon ke format WhatsApp (62xxxxxxxxxx@c.us)
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', trim($phoneNumber));
        
        // Validate phone number is not empty
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Phone number cannot be empty');
        }
        
        // If starts with 0, replace with 62
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '62' . substr($phoneNumber, 1);
        }
        
        // If doesn't start with 62, add it
        if (!str_starts_with($phoneNumber, '62')) {
            $phoneNumber = '62' . $phoneNumber;
        }
        
        return $phoneNumber . '@c.us';
    }

    /**
     * Send WhatsApp message with retry logic
     */
    public function sendMessage(string $phoneNumber, string $message, int $maxRetries = 1): bool
    {
        try {
            // Validate phone number
            if (empty($phoneNumber) || trim($phoneNumber) === '') {
                Log::warning('Empty phone number provided for WhatsApp message');
                return false;
            }
            
            $chatId = $this->formatPhoneNumber($phoneNumber);
            
            $attempt = 0;
            $lastError = null;
            
            while ($attempt <= $maxRetries) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'X-Api-Key' => $this->apiKey,
                        ])->post($this->apiUrl, [
                            'session' => $this->session,
                            'chatId' => $chatId,
                            'text' => $message,
                        ]);

                    if ($response->successful()) {
                        Log::info('WhatsApp message sent successfully', [
                            'phone' => $phoneNumber,
                            'chatId' => $chatId,
                            'attempt' => $attempt + 1,
                        ]);
                        return true;
                    } else {
                        $responseBody = $response->body();
                        $statusCode = $response->status();
                        
                        // Check if it's a retryable error (5xx server errors)
                        $isRetryable = $statusCode >= 500 && $statusCode < 600;
                        
                        // Also check for specific WhatsApp API errors that might be temporary
                        $isWhatsAppError = false;
                        if ($responseBody) {
                            $responseData = json_decode($responseBody, true);
                            if (isset($responseData['statusCode']) && $responseData['statusCode'] >= 500) {
                                $isRetryable = true;
                                $isWhatsAppError = true;
                            }
                        }
                        
                        if ($isRetryable && $attempt < $maxRetries) {
                            $attempt++;
                            $waitTime = $attempt * 2; // Exponential backoff: 2s, 4s, etc.
                            Log::warning('WhatsApp API error, retrying...', [
                                'phone' => $phoneNumber,
                                'chatId' => $chatId,
                                'status' => $statusCode,
                                'attempt' => $attempt,
                                'wait_time' => $waitTime,
                                'error' => $isWhatsAppError ? ($responseData['exception']['message'] ?? 'Unknown error') : 'HTTP ' . $statusCode,
                            ]);
                            sleep($waitTime);
                            continue;
                        }
                        
                        Log::error('Failed to send WhatsApp message', [
                            'phone' => $phoneNumber,
                            'chatId' => $chatId,
                            'status' => $statusCode,
                            'response' => $responseBody,
                            'attempts' => $attempt + 1,
                        ]);
                        return false;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    // Network/connection errors are retryable
                    if ($attempt < $maxRetries) {
                        $attempt++;
                        $waitTime = $attempt * 2;
                        Log::warning('WhatsApp connection error, retrying...', [
                            'phone' => $phoneNumber,
                            'chatId' => $chatId,
                            'attempt' => $attempt,
                            'wait_time' => $waitTime,
                            'error' => $e->getMessage(),
                        ]);
                        sleep($waitTime);
                        continue;
                    }
                    throw $e;
                }
            }
            
            return false;
        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid phone number format for WhatsApp message', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send message to admin
     */
    public function sendToAdmin(string $message): bool
    {
        $adminPhone = config('services.whatsapp.admin_phone', '081511207866');
        return $this->sendMessage($adminPhone, $message);
    }

    /**
     * Send message to multiple recipients
     */
    public function sendBulk(array $phoneNumbers, string $message): array
    {
        $results = [];
        foreach ($phoneNumbers as $phoneNumber) {
            if (!empty($phoneNumber)) {
                $results[$phoneNumber] = $this->sendMessage($phoneNumber, $message);
            }
        }
        return $results;
    }
}
