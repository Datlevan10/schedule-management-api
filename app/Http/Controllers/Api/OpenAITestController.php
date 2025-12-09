<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAITestController extends Controller
{
    /**
     * Test OpenAI API connection
     * POST /api/v1/test/openai
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
            $model = config('services.openai.model') ?: env('OPENAI_MODEL', 'gpt-4o-mini');

            if (!$apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OpenAI API key not configured',
                    'debug' => [
                        'env_check' => [
                            'OPENAI_API_KEY' => env('OPENAI_API_KEY') ? 'Set' : 'Not set',
                            'OPENAI_MODEL' => env('OPENAI_MODEL', 'Not set')
                        ]
                    ]
                ], 500);
            }

            // Simple test message
            $testMessage = $request->input('message', 'Hello! This is a test message to verify the OpenAI API connection is working properly.');

            Log::info('Testing OpenAI connection', [
                'model' => $model,
                'api_key_length' => strlen($apiKey),
                'test_message' => $testMessage
            ]);

            // Make request to OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'verify' => false,
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $testMessage
                    ]
                ],
                'max_tokens' => 150,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'OpenAI API connection successful',
                    'data' => [
                        'model_used' => $model,
                        'response' => $data['choices'][0]['message']['content'] ?? 'No content returned',
                        'usage' => $data['usage'] ?? null,
                        'response_time' => $response->transferStats->getTransferTime() ?? null
                    ],
                    'debug' => [
                        'status_code' => $response->status(),
                        'headers' => [
                            'content_type' => $response->header('Content-Type'),
                            'ratelimit_remaining' => $response->header('x-ratelimit-remaining-requests'),
                        ]
                    ]
                ]);
            } else {
                $errorData = $response->json();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'OpenAI API request failed',
                    'error' => [
                        'status_code' => $response->status(),
                        'error_type' => $errorData['error']['type'] ?? 'unknown',
                        'error_message' => $errorData['error']['message'] ?? 'Unknown error',
                        'error_code' => $errorData['error']['code'] ?? null,
                    ],
                    'debug' => [
                        'model' => $model,
                        'full_response' => $response->body()
                    ]
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('OpenAI test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to test OpenAI connection',
                'error' => $e->getMessage(),
                'debug' => [
                    'exception_type' => get_class($e),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ], 500);
        }
    }

    /**
     * Test OpenAI API with schedule analysis prompt
     * POST /api/v1/test/openai/schedule
     */
    public function testScheduleAnalysis(Request $request): JsonResponse
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');

            if (!$apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OpenAI API key not configured'
                ], 500);
            }

            // Sample schedule data for testing
            $sampleSchedule = [
                [
                    'title' => 'Team Meeting',
                    'start_time' => '2024-12-05 09:00:00',
                    'end_time' => '2024-12-05 10:00:00',
                    'priority' => 3
                ],
                [
                    'title' => 'Project Review',
                    'start_time' => '2024-12-05 14:00:00',
                    'end_time' => '2024-12-05 15:30:00',
                    'priority' => 4
                ],
                [
                    'title' => 'Client Call',
                    'start_time' => '2024-12-05 16:00:00',
                    'end_time' => '2024-12-05 17:00:00',
                    'priority' => 5
                ]
            ];

            $prompt = "Analyze this schedule and provide optimization suggestions:\n\n" . json_encode($sampleSchedule, JSON_PRETTY_PRINT) . "\n\nProvide suggestions for better time management, potential conflicts, and productivity improvements.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'verify' => false,
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a schedule optimization AI assistant. Provide helpful and practical scheduling advice.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Schedule analysis test successful',
                    'data' => [
                        'sample_schedule' => $sampleSchedule,
                        'ai_analysis' => $data['choices'][0]['message']['content'] ?? 'No analysis returned',
                        'usage' => $data['usage'] ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Schedule analysis test failed',
                    'error' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to test schedule analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}