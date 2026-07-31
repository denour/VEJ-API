<?php

namespace App\Services\Social;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocialMediaPublisher
{
    public function __construct(
        private readonly ImageGeneratorInterface $imageGenerator,
        private readonly TextGeneratorInterface $textGenerator,
        private readonly SocialImageComposer $imageComposer = new SocialImageComposer,
    ) {}

    /**
     * Publish a post to all configured social media platforms.
     *
     * @return array{facebook: ?string, instagram: ?string}
     */
    public function publishPost(Post $post): array
    {
        $results = ['facebook' => null, 'instagram' => null];

        $copy = $this->generateSocialCopy($post);

        try {
            $socialImageUrl = $this->generateSocialImage($post, $copy['social_hook']);
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

        if (config('social.facebook.enabled')) {
            $results['facebook'] = $this->publishToFacebook($post, $socialImageUrl, $this->buildFacebookCaption($post, $copy['fb_body']));
        }

        if (config('social.instagram.enabled')) {
            $results['instagram'] = $this->publishToInstagram($post, $socialImageUrl, $this->buildInstagramCaption($post, $copy['ig_body']));
        }

        $post->update([
            'facebook_post_id' => $results['facebook'],
            'instagram_post_id' => $results['instagram'],
            'social_published_at' => now(),
        ]);

        return $results;
    }

    /**
     * Generate platform-tailored social copy in a single AI call.
     *
     * @return array{social_hook: string, fb_body: string, ig_body: string}
     */
    private function generateSocialCopy(Post $post): array
    {
        $title = $post->title;
        $excerpt = $post->excerpt ?? '';
        $category = $post->category ?? 'Jardinería';

        $prompt = <<<PROMPT
Eres el community manager del blog mexicano de jardinería "Vida en el Jardín".
A partir de este artículo, crea copy para redes sociales que invite a leerlo.

TÍTULO DEL ARTÍCULO: {$title}
CATEGORÍA: {$category}
RESUMEN: {$excerpt}

Devuelve EXCLUSIVAMENTE JSON válido, sin texto adicional, con esta estructura:
{
  "social_hook": "Gancho cortísimo de 5 a 8 palabras para sobreponer en la imagen. Emocional o que despierte curiosidad, NADA de tono SEO. Sin hashtags, sin emoji, sin comillas.",
  "fb_body": "Texto para Facebook: 2-3 frases cálidas e informativas que enganchen al lector. En español mexicano. Sin enlaces y sin hashtags (se agregan aparte). Máximo 1 emoji.",
  "ig_body": "Texto para Instagram: cercano y visual, 1-2 frases con 2-4 emoji bien colocados. En español mexicano. Sin enlaces y sin hashtags (se agregan aparte)."
}
PROMPT;

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'Eres un community manager experto en jardinería. Devuelve solo JSON válido.',
            'max_tokens' => 400,
        ]);

        $data = $this->parseJsonObject($raw);

        return [
            'social_hook' => trim($data['social_hook'] ?? Str::limit($post->title, 50, '')),
            'fb_body' => trim($data['fb_body'] ?? ($excerpt !== '' ? $excerpt : $title)),
            'ig_body' => trim($data['ig_body'] ?? ($excerpt !== '' ? $excerpt : $title)),
        ];
    }

    /**
     * Decode a JSON object from a raw AI response, tolerating markdown fences.
     *
     * @return array<string, mixed>
     */
    private function parseJsonObject(string $raw): array
    {
        $candidate = trim($raw);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $candidate, $matches)) {
            $candidate = $matches[1];
        }

        $data = json_decode($candidate, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Generate the social card: the article's own cover photo with the hook,
     * category and brand drawn on top deterministically (GD), so the copy is
     * never missing or garbled — which is what happened when we asked the image
     * model to render the text itself.
     *
     * The cover is the background; we only ask the image model for a fresh
     * background when the post has no cover.
     */
    private function generateSocialImage(Post $post, string $hook): string
    {
        $category = $post->category ?? 'Jardinería';

        $backgroundUrl = $post->cover_image ?: $this->generateBackground($post);

        try {
            $backgroundBytes = Http::timeout(120)->get($backgroundUrl)->body();
            $card = $this->imageComposer->overlay($backgroundBytes, $hook, $category);

            $path = 'social/'.uniqid('card-', true).'.png';
            Storage::disk('s3')->put($path, $card, ['visibility' => 'public']);

            return Storage::disk('s3')->url($path);
        } catch (\Throwable $e) {
            // Never make the card worse than the plain background: fall back to it.
            Log::error('Failed to overlay social card text, using plain background', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return $backgroundUrl;
        }
    }

    /**
     * Ask the image model for a text-free background photo. Only used when the
     * post has no cover image of its own.
     */
    private function generateBackground(Post $post): string
    {
        $category = $post->category ?? 'Jardinería';
        $title = $post->title;

        // Background ONLY — the model must not render any text; we overlay it.
        $prompt = <<<PROMPT
Create a stunning background photograph for a gardening social media card.

Theme of the article: "{$title}" (category: {$category}).

Style requirements:
- Square format (1:1 aspect ratio) for Instagram and Facebook
- Vibrant plant photography with cinematic, natural lighting
- Rich composition that leaves the lower third visually calm for a text overlay
- Modern editorial aesthetic, shallow depth of field
- IMPORTANT: absolutely NO text, NO letters, NO words, NO logos, NO watermarks anywhere in the image
PROMPT;

        return $this->imageGenerator->generate($prompt, [
            'aspectRatio' => '1:1',
            'quality' => 'high',
            'directory' => 'social',
        ]);
    }

    /**
     * Build a Facebook caption: creative body + clickable link + few hashtags.
     */
    private function buildFacebookCaption(Post $post, string $body): string
    {
        $blogUrl = config('social.blog_url', 'https://vidaeneljardin.com');
        $postUrl = "{$blogUrl}/blog/{$post->slug}";
        $hashtags = $this->hashtags($post, 2, ['#VidaEnElJardin']);

        return "{$body}\n\nLee el artículo completo:\n{$postUrl}\n\n{$hashtags}";
    }

    /**
     * Build an Instagram caption: creative body + "link in bio" + many hashtags.
     * Instagram captions do not render clickable links, so we point to the bio.
     */
    private function buildInstagramCaption(Post $post, string $body): string
    {
        $hashtags = $this->hashtags($post, 12, ['#VidaEnElJardin', '#Plantas', '#Jardineria']);

        return "{$body}\n\n📍 Encuentra el link en nuestra bio para leer el artículo completo.\n\n{$hashtags}";
    }

    /**
     * Build a hashtag string from the post tags plus brand hashtags.
     *
     * @param  list<string>  $brand
     */
    private function hashtags(Post $post, int $maxPostTags, array $brand): string
    {
        return collect($post->tags ?? [])
            ->filter()
            ->take($maxPostTags)
            ->map(fn (string $tag) => '#'.str_replace(' ', '', trim($tag)))
            ->merge($brand)
            ->unique()
            ->implode(' ');
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
