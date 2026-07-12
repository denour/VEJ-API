<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 12);
        if ($perPage < 1) {
            $perPage = 12;
        }
        $perPage = min($perPage, 50);

        // Cache key from an explicit allowlist of supported params so junk query
        // strings can't blow up the cache with unique, never-hit entries.
        $keyParts = $request->only([
            'q', 'category', 'status', 'featured', 'published_before', 'published_after',
        ]);
        $keyParts['per_page'] = $perPage;
        $keyParts['page'] = (int) $request->integer('page', 1);
        $cacheKey = 'posts:index:'.md5(json_encode($keyParts));

        $posts = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($request, $perPage) {
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
                ->latest('created_at')
                ->with(['author', 'blocks']);

            return $query->paginate($perPage);
        });

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        $cacheKey = 'posts:show:'.$post->id;

        $post = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($post) {
            $post->load(['author', 'blocks']);

            $related = Post::query()
                ->whereKeyNot($post->getKey())
                ->where('status', 'published')
                ->latest('created_at')
                ->with(['author', 'blocks'])
                ->limit(3)
                ->get();

            $post->setRelation('relatedPosts', $related);

            return $post;
        });

        return new PostResource($post);
    }
}
