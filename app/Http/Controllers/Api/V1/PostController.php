<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        }

        $query = Post::query();

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->filled('featured')) {
            $query->where('featured', filter_var($request->query('featured'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('published_before')) {
            $date = Carbon::parse((string) $request->query('published_before'));
            $query->whereNotNull('published_at')->where('published_at', '<=', $date);
        }

        if ($request->filled('published_after')) {
            $date = Carbon::parse((string) $request->query('published_after'));
            $query->whereNotNull('published_at')->where('published_at', '>=', $date);
        }

        $query->latest('published_at')
            ->where('status', 'published')
            ->latest('created_at')->with('author');

        return PostResource::collection($query->paginate($perPage));
    }

    public function show(Post $post): PostResource
    {
        $post->load('author');

        $related = Post::query()
            ->whereKeyNot($post->getKey())
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->latest('created_at')
            ->with('author')
            ->limit(3)
            ->get();

        $post->setRelation('relatedPosts', $related);

        return new PostResource($post);
    }
}
