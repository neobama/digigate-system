<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key') ?: null;
        // Force use gemini-1.5-pro as it's the most stable
        $model = config('gemini.model', 'gemini-1.5-pro');
        $this->baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        
        // Log for debugging
        Log::debug('GeminiService initialized', [
            'model' => $model,
            'baseUrl' => $this->baseUrl,
            'hasApiKey' => !empty($this->apiKey),
        ]);
    }
    
    /**
     * Check if API key is configured
     */
    protected function hasApiKey(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Parse reimbursement invoice/bon untuk extract informasi
     * 
     * @param string $imageBase64 Base64 encoded image
     * @return array|null
     */
    public function parseReimbursementInvoice(string $imageBase64): ?array
    {
        if (!$this->hasApiKey()) {
            Log::error('Gemini API Key not configured');
            return null;
        }
        
        $prompt = "Analyze this invoice/receipt image and extract the following information in JSON format:
{
  \"purpose\": \"Short description of what the expense is for (e.g., 'Transport ke client', 'Makan siang meeting')\",
  \"expense_date\": \"Date in YYYY-MM-DD format (extract from invoice date)\",
  \"amount\": \"Total amount as number (without currency symbol)\",
  \"description\": \"Additional details or notes from the invoice\"
}

Only return valid JSON, no other text. If any field cannot be determined, use null.";

        try {
            $response = Http::timeout(30)->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $this->detectMimeType($imageBase64),
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json();
                $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($text) {
                    // Extract JSON from response (might have markdown code blocks)
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $parsed = json_decode($text, true);
                    
                    if ($parsed && is_array($parsed)) {
                        return [
                            'purpose' => $parsed['purpose'] ?? null,
                            'expense_date' => $parsed['expense_date'] ?? null,
                            'amount' => $parsed['amount'] ? (float) $parsed['amount'] : null,
                            'description' => $parsed['description'] ?? null,
                        ];
                    }
                }
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse component invoice untuk extract multiple items
     * 
     * @param string $imageBase64 Base64 encoded image
     * @return array|null Array of items
     */
    public function parseComponentInvoice(string $imageBase64): ?array
    {
        if (!$this->hasApiKey()) {
            Log::error('Gemini API Key not configured');
            return null;
        }
        
        $prompt = "Analyze this invoice/receipt image and extract ALL items/products listed. Return as JSON array:
[
  {
    \"name\": \"Product/Item name - MUST match one of these exact options: 'Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K', 'RAM DDR4', 'RAM DDR5', 'SSD', 'Chassis Macan', 'Chassis Maleo', or 'Chassis Komodo'. Mapping rules: If you see RAM/DDR4/DDR4 memory -> use 'RAM DDR4'. If you see RAM/DDR5/DDR5 memory -> use 'RAM DDR5'. If you see Processor i7 11700/11700K/11700F -> use 'Processor i7 11700K'. If you see Processor i7 8700/8700K/8700F -> use 'Processor i7 8700K'. If you see Processor i7 14700/14700K/14700F -> use 'Processor i7 14700K'. If you see any SSD (Samsung, Kingston, etc.) -> use 'SSD'. If you see Chassis Macan/Case Macan -> use 'Chassis Macan'. If you see Chassis Maleo/Case Maleo -> use 'Chassis Maleo'. If you see Chassis Komodo/Case Komodo -> use 'Chassis Komodo'.\",
    \"supplier\": \"Supplier/vendor name from invoice\",
    \"purchase_date\": \"Date in YYYY-MM-DD format (extract from invoice date)\",
    \"quantity\": \"Quantity as number (if available)\"
  },
  ...
]

IMPORTANT: The 'name' field MUST be exactly one of: 'Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K', 'RAM DDR4', 'RAM DDR5', 'SSD', 'Chassis Macan', 'Chassis Maleo', or 'Chassis Komodo'. Map similar items to the closest match.

Only return valid JSON array, no other text. If invoice date is not found, use today's date. Extract all items from the invoice.";

        try {
            $response = Http::timeout(30)->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $this->detectMimeType($imageBase64),
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json();
                $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($text) {
                    // Extract JSON from response (might have markdown code blocks)
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $parsed = json_decode($text, true);
                    
                    if ($parsed && is_array($parsed)) {
                        // Normalize items and map to dropdown options
                        $items = [];
                        foreach ($parsed as $item) {
                            if (isset($item['name'])) {
                                $items[] = [
                                    'name' => $this->mapComponentName($item['name']),
                                    'supplier' => $item['supplier'] ?? null,
                                    'purchase_date' => $item['purchase_date'] ?? now()->format('Y-m-d'),
                                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                                ];
                            }
                        }
                        
                        return $items;
                    }
                }
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Map component name to dropdown options
     * 
     * @param string $name
     * @return string
     */
    protected function mapComponentName(string $name): string
    {
        $name = strtolower(trim($name));
        
        // Available options
        $options = [
            'Processor i7 11700K',
            'Processor i7 8700K',
            'Processor i7 14700K',
            'RAM DDR4',
            'RAM DDR5',
            'SSD',
            'Chassis Macan',
            'Chassis Maleo',
            'Chassis Komodo',
        ];
        
        // Mapping rules (order matters - check more specific first)
        // Chassis Komodo -> Chassis Komodo
        if (preg_match('/\b(chassis\s*komodo|komodo\s*chassis|case\s*komodo)\b/i', $name)) {
            return 'Chassis Komodo';
        }
        
        // Chassis Macan -> Chassis Macan
        if (preg_match('/\b(chassis\s*macan|macan\s*chassis|case\s*macan)\b/i', $name)) {
            return 'Chassis Macan';
        }
        
        // Chassis Maleo -> Chassis Maleo
        if (preg_match('/\b(chassis\s*maleo|maleo\s*chassis|case\s*maleo)\b/i', $name)) {
            return 'Chassis Maleo';
        }
        
        // RAM/DDR5 -> RAM DDR5 (check DDR5 before DDR4)
        if (preg_match('/\b(ddr5|ddr\s*5)\b/i', $name)) {
            return 'RAM DDR5';
        }
        
        // RAM/DDR4 -> RAM DDR4
        if (preg_match('/\b(ram|ddr4|ddr\s*4|memory)\b/i', $name)) {
            return 'RAM DDR4';
        }
        
        // Processor i7 14700 variants -> Processor i7 14700K
        if (preg_match('/\b(i7\s*14700|14700k|14700f|14700)\b/i', $name)) {
            return 'Processor i7 14700K';
        }
        
        // Processor i7 11700 variants -> Processor i7 11700K
        if (preg_match('/\b(i7\s*11700|11700k|11700f|11700)\b/i', $name)) {
            return 'Processor i7 11700K';
        }
        
        // Processor i7 8700 variants -> Processor i7 8700K
        if (preg_match('/\b(i7\s*8700|8700k|8700f|8700)\b/i', $name)) {
            return 'Processor i7 8700K';
        }
        
        // SSD (any brand/model) -> SSD
        if (preg_match('/\b(ssd|solid\s*state|samsung|kingston|wd|western\s*digital|crucial|adata|sandisk)\b/i', $name)) {
            return 'SSD';
        }
        
        // Default: return first option if no match
        return $options[0];
    }

    /**
     * Detect MIME type from base64 image
     * 
     * @param string $base64
     * @return string
     */
    protected function detectMimeType(string $base64): string
    {
        // Remove data URI prefix if present
        $base64 = preg_replace('/^data:image\/[^;]+;base64,/', '', $base64);
        
        // Decode to get image info
        $imageData = base64_decode($base64, true);
        
        if ($imageData === false) {
            return 'image/jpeg'; // Default
        }
        
        // Use finfo to detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        
        // Validate and return
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            return $mimeType;
        }
        
        return 'image/jpeg'; // Default fallback
    }
}

