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
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscriptions,email'],
        ]);

        $subscription = NewsletterSubscription::create([
            'email' => $data['email'],
        ]);

        return response()->json([
            'message' => 'Subscribed successfully',
            'data' => [
                'id' => $subscription->getKey(),
                'email' => $subscription->email,
            ],
        ], 201);
    }
}
