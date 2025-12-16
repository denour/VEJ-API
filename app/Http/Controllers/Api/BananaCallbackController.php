<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BananaCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Try to support multiple payload shapes from Nano Banana
        $taskId = data_get($payload, 'taskId')
            ?? data_get($payload, 'data.taskId')
            ?? data_get($payload, 'data.id');

        $imageUrl = data_get($payload, 'imageUrl')
            ?? data_get($payload, 'resultImageUrl')
            ?? data_get($payload, 'data.response.resultImageUrl');

        if (empty($taskId)) {
            return response()->json([
                'message' => 'Missing taskId in payload',
            ], 422);
        }

        $requestRecord = ImageGenerationRequest::query()
            ->where('external_id', (string) $taskId)
            ->first();

        if (! $requestRecord) {
            // Accept but nothing to update yet
            return response()->json([
                'message' => 'No matching request found for taskId, ignoring',
            ], 202);
        }

        // Update status early
        $requestRecord->update([
            'status' => 'processing',
            'metadata' => array_merge((array) $requestRecord->metadata, [
                'webhook' => $payload,
            ]),
        ]);

        if (empty($imageUrl)) {
            // Provider might send intermediate webhook without the URL
            return response()->json([
                'message' => 'Accepted - awaiting image URL',
            ], 202);
        }

        try {
            $imageResponse = Http::timeout(120)->get((string) $imageUrl);
            if (! $imageResponse->successful()) {
                $requestRecord->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to download image from provider',
                ]);

                return response()->json([
                    'message' => 'Failed to download image',
                ], 422);
            }

            $filename = 'posts/'.uniqid('', true).'.png';
            Storage::disk('s3')->put($filename, $imageResponse->body(), ['visibility' => 'public']);

            $publicUrl = Storage::disk('s3')->url($filename);

            // Update related post cover image when available
            if ($requestRecord->post_id) {
                $post = Post::query()->find($requestRecord->post_id);
                if ($post) {
                    $post->update(['cover_image' => $publicUrl]);
                }
            }

            $requestRecord->update([
                'status' => 'completed',
                'image_path' => $filename,
                'image_url' => $publicUrl,
            ]);

            return response()->json([
                'message' => 'Image stored successfully',
                'url' => $publicUrl,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Banana webhook error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            $requestRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal error handling webhook',
            ], 500);
        }
    }
}
