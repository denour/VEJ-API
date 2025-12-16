<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'coverImage' => $this->cover_image ?? null,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'author' => $this->whenLoaded('author', fn () => new AuthorResource($this->author)),
            'list' => $this->list ?? [],

            'related' => PostResource::collection($this->whenLoaded('relatedPosts')),

            'publishedAt' => optional($this->published_at)?->format('Y-m-d H:i:s'),
            'readingTime' => $this->reading_time,
            'featured' => (bool) $this->featured,
            'status' => $this->status,
        ];
    }
}
