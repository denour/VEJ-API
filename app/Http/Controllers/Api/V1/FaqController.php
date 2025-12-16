<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class FaqController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }

        $query = Faq::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        $query->latest('updated_at');

        return FaqResource::collection($query->paginate($perPage));
    }

    public function show(Faq $faq): FaqResource
    {
        return new FaqResource($faq);
    }
}
