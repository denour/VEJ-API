<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::create($data);

        return response()->json([
            'message' => 'Message received',
            'data' => [
                'id' => $message->getKey(),
            ],
        ], 201);
    }
}
