<?php

namespace App\Services\Social;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaPublisher
{
    public function __construct(
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    /**
     * Publish a post to all configured social media platforms.
     *
     * @return array{facebook: ?string, instagram: ?string}
     */
    public function publishPost(Post $post): array
    {
        $results = ['facebook' => null, 'instagram' => null];

        try {
            $socialImageUrl = $this->generateSocialImage($post);
            $post->update(['social_image' => $socialImageUrl]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate social image', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            // Fall back to cover image
            $socialImageUrl = $post->cover_image;

            if (! $socialImageUrl) {
                Log::error('No image available for social publishing', ['post_id' => $post->id]);

                return $results;
            }
        }

        $caption = $this->buildCaption($post);

        if (config('social.facebook.enabled')) {
            $results['facebook'] = $this->publishToFacebook($post, $socialImageUrl, $caption);
        }

        if (config('social.instagram.enabled')) {
            $results['instagram'] = $this->publishToInstagram($post, $socialImageUrl, $caption);
        }

        $post->update([
            'facebook_post_id' => $results['facebook'],
            'instagram_post_id' => $results['instagram'],
            'social_published_at' => now(),
        ]);

        return $results;
    }

    /**
     * Generate a social-media-optimized image with title overlay.
     */
    private function generateSocialImage(Post $post): string
    {
        $title = $post->title;
        $category = $post->category ?? 'Jardinería';

        $prompt = <<<PROMPT
Create a stunning social media image for a gardening blog post.

The image must include the following title text elegantly overlaid: "{$title}"

Style requirements:
- Square format (1:1 aspect ratio) for Instagram and Facebook
- Vibrant plant photography as background with cinematic lighting
- Title text should be large, readable, and beautifully integrated
- Use a semi-transparent dark overlay behind the text for readability
- Modern editorial aesthetic, clean typography
- Category badge: "{$category}"
- Brand: "Vida en el Jardín" as small watermark in corner
- No other text besides the title, category, and brand
PROMPT;

        return $this->imageGenerator->generate($prompt, [
            'aspectRatio' => '1:1',
            'quality' => 'high',
            'directory' => 'social',
        ]);
    }

    /**
     * Build a caption for social media posts.
     */
    private function buildCaption(Post $post): string
    {
        $blogUrl = config('social.blog_url', 'https://vidaeneljardin.com');
        $postUrl = "{$blogUrl}/blog/{$post->slug}";

        $hashtags = collect($post->tags ?? [])
            ->map(fn (string $tag) => '#'.str_replace(' ', '', $tag))
            ->implode(' ');

        $caption = "{$post->excerpt}\n\n";
        $caption .= "Lee el articulo completo:\n{$postUrl}\n\n";
        $caption .= "{$hashtags} #VidaEnElJardin #Plantas #Jardineria";

        return $caption;
    }

    /**
     * Publish a photo post to Facebook Page.
     */
    private function publishToFacebook(Post $post, string $imageUrl, string $caption): ?string
    {
        $pageId = config('social.facebook.page_id');
        $accessToken = config('social.facebook.access_token');

        if (! $pageId || ! $accessToken) {
            Log::warning('Facebook credentials not configured');

            return null;
        }

        try {
            $response = Http::post("https://graph.facebook.com/v21.0/{$pageId}/photos", [
                'url' => $imageUrl,
                'message' => $caption,
                'access_token' => $accessToken,
            ]);

            if (! $response->successful()) {
                Log::error('Facebook publish failed', [
                    'post_id' => $post->id,
                    'error' => $response->body(),
                ]);

                return null;
            }

            $fbPostId = $response->json('post_id') ?? $response->json('id');

            Log::info('Published to Facebook', [
                'post_id' => $post->id,
                'fb_post_id' => $fbPostId,
            ]);

            return $fbPostId;
        } catch (\Throwable $e) {
            Log::error('Facebook publish error', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Publish a photo post to Instagram Business Account.
     * Instagram requires a 2-step process: create media container, then publish.
     */
    private function publishToInstagram(Post $post, string $imageUrl, string $caption): ?string
    {
        $accountId = config('social.instagram.account_id');
        $accessToken = config('social.facebook.access_token'); // Uses same FB token

        if (! $accountId || ! $accessToken) {
            Log::warning('Instagram credentials not configured');

            return null;
        }

        try {
            // Step 1: Create media container
            $containerResponse = Http::post("https://graph.facebook.com/v21.0/{$accountId}/media", [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ]);

            if (! $containerResponse->successful()) {
                Log::error('Instagram container creation failed', [
                    'post_id' => $post->id,
                    'error' => $containerResponse->body(),
                ]);

                return null;
            }

            $creationId = $containerResponse->json('id');

            if (! $creationId) {
                Log::error('Instagram container returned no ID', ['post_id' => $post->id]);

                return null;
            }

            // Brief pause for Instagram to process the container
            sleep(5);

            // Step 2: Publish the container
            $publishResponse = Http::post("https://graph.facebook.com/v21.0/{$accountId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

            if (! $publishResponse->successful()) {
                Log::error('Instagram publish failed', [
                    'post_id' => $post->id,
                    'error' => $publishResponse->body(),
                ]);

                return null;
            }

            $igMediaId = $publishResponse->json('id');

            Log::info('Published to Instagram', [
                'post_id' => $post->id,
                'ig_media_id' => $igMediaId,
            ]);

            return $igMediaId;
        } catch (\Throwable $e) {
            Log::error('Instagram publish error', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
