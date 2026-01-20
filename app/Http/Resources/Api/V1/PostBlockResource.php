<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'data' => $this->transformData(),
            'order' => $this->order,
        ];
    }

    /**
     * Transform block data based on type, including image placeholder handling.
     */
    private function transformData(): ?array
    {
        if (empty($this->data)) {
            return null;
        }

        $data = $this->data;

        // For image blocks, ensure we have a URL (use placeholder if needed)
        if ($this->type === 'image') {
            $data['url'] = $this->getImageUrl();
        }

        return $data;
    }
}
