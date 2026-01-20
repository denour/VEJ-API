<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\PersonaFieldAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorAiAssistController extends Controller
{
    public function __construct(
        private readonly PersonaFieldAssistantService $service
    ) {}

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'prompt' => 'required|string|min:3',
            'context' => 'nullable|array',
        ]);

        try {
            \Log::info('API AI Assist Called', [
                'field_type' => $validated['field_type'],
                'prompt' => $validated['prompt'],
            ]);

            $result = $this->service->generateFieldSuggestion(
                $validated['field_type'],
                $validated['prompt'],
                $validated['context'] ?? []
            );

            \Log::info('API AI Assist Result', ['result' => $result]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'value' => $result['value'],
                    'type' => $result['type'],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Generation failed',
            ], 422);
        } catch (\Exception $e) {
            \Log::error('API AI Assist Error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
