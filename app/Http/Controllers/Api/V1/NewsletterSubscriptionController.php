<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\NewsletterSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // firstOrCreate + an identical response whether or not the email already
        // existed, so the endpoint can't be used to enumerate who is subscribed
        // (a unique-rule 422 vs 201 divergence would leak membership).
        NewsletterSubscription::firstOrCreate(['email' => $data['email']]);

        return response()->json([
            'message' => 'Subscribed successfully',
            'data' => [
                'email' => $data['email'],
            ],
        ], 201);
    }
}
