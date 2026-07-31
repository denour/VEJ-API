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
        $slides = $this->generateSocialSlides($post, $copy['social_hook'], $copy['carousel_tips']);

        if ($slides === []) {
            Log::error('No image available for social publishing', ['post_id' => $post->id]);

            return $results;
        }

        $post->update([
            'social_image' => $slides[0],
            'social_images' => $slides,
        ]);

        if (config('social.facebook.enabled')) {
            $results['facebook'] = $this->publishToFacebook($post, $slides[0], $this->buildFacebookCaption($post, $copy['fb_body']));
        }

        if (config('social.instagram.enabled')) {
            $results['instagram'] = $this->publishToInstagram($post, $slides, $this->buildInstagramCaption($post, $copy['ig_body']));
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
     * @return array{social_hook: string, fb_body: string, ig_body: string, carousel_tips: list<string>}
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
  "social_hook": "Gancho cortísimo de 5 a 8 palabras para sobreponer en la imagen de portada. Emocional o que despierte curiosidad, NADA de tono SEO. Sin hashtags, sin emoji, sin comillas.",
  "fb_body": "Texto para Facebook: 2-3 frases cálidas e informativas que enganchen al lector. En español mexicano. Sin enlaces y sin hashtags (se agregan aparte). Máximo 1 emoji.",
  "ig_body": "Texto para Instagram: cercano y visual, 1-2 frases con 2-4 emoji bien colocados. En español mexicano. Sin enlaces y sin hashtags (se agregan aparte).",
  "carousel_tips": "Lista de EXACTAMENTE 3 ganchos cortísimos (5 a 8 palabras cada uno), mismo tono que social_hook, cada uno un consejo o dato distinto y concreto del artículo — para las siguientes tarjetas de un carrusel. Sin numerar, sin hashtags, sin emoji, sin comillas."
}
PROMPT;

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'Eres un community manager experto en jardinería. Devuelve solo JSON válido.',
            'max_tokens' => 500,
        ]);

        $data = $this->parseJsonObject($raw);
        $tips = is_array($data['carousel_tips'] ?? null) ? $data['carousel_tips'] : [];

        return [
            'social_hook' => trim($data['social_hook'] ?? Str::limit($post->title, 50, '')),
            'fb_body' => trim($data['fb_body'] ?? ($excerpt !== '' ? $excerpt : $title)),
            'ig_body' => trim($data['ig_body'] ?? ($excerpt !== '' ? $excerpt : $title)),
            'carousel_tips' => collect($tips)->filter(fn ($tip) => is_string($tip) && trim($tip) !== '')->map(fn ($tip) => trim($tip))->take(3)->values()->all(),
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
     * Build the carousel: a hero slide (cover photo + hook) followed by up to
     * 3 tip slides, each using one of the post's own inline images as the
     * background. Every slide is composited deterministically (GD), so the
     * copy is never missing or garbled — what happened when we asked the
     * image model to render the text itself.
     *
     * Returns the list of stored slide URLs, hero first. A tip slide that
     * fails to compose is skipped rather than failing the whole post; if the
     * hero itself fails, falls back to the plain cover/background so the post
     * still has something to publish.
     *
     * @return list<string>
     */
    private function generateSocialSlides(Post $post, string $hook, array $tips): array
    {
        $category = $post->category ?? 'Jardinería';
        $slides = [];
        $heroBackground = null;

        try {
            $heroBackground = $post->cover_image ?: $this->generateBackground($post);
            $slides[] = $this->composeSlide($heroBackground, $hook, $category);
        } catch (\Throwable $e) {
            Log::error('Failed to build hero social card, falling back to plain background', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            // The overlay may have failed after we got a background, or the
            // background itself may never have been generated — only use it
            // if we actually have one.
            if ($heroBackground) {
                $slides[] = $heroBackground;
            }
        }

        if ($slides === []) {
            return [];
        }

        $tipBackgrounds = $this->tipBackgroundUrls($post, count($tips));

        foreach ($tips as $i => $tip) {
            if (! isset($tipBackgrounds[$i])) {
                break;
            }

            try {
                $slides[] = $this->composeSlide($tipBackgrounds[$i], $tip, $category);
            } catch (\Throwable $e) {
                Log::error('Failed to overlay carousel tip slide, skipping it', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $slides;
    }

    /**
     * Download a background, overlay the card text on it, and store the result.
     */
    private function composeSlide(string $backgroundUrl, string $text, string $category): string
    {
        $backgroundBytes = Http::timeout(120)->get($backgroundUrl)->body();
        $card = $this->imageComposer->overlay($backgroundBytes, $text, $category);

        $path = 'social/'.uniqid('card-', true).'.png';
        Storage::disk('s3')->put($path, $card, ['visibility' => 'public']);

        return Storage::disk('s3')->url($path);
    }

    /**
     * The post's own inline photos (from its content blocks), in article
     * order, to use as backgrounds for carousel tip slides. Reads the
     * `blocks` relation via property access (not a fresh `blocks()` query) so
     * tests can inject blocks with `setRelation()` without touching the DB.
     *
     * @return list<string>
     */
    private function tipBackgroundUrls(Post $post, int $limit): array
    {
        if ($limit === 0) {
            return [];
        }

        return $post->blocks
            ->where('type', 'image')
            ->sortBy('order')
            ->map(fn ($block) => $block->data['url'] ?? null)
            ->filter()
            ->take($limit)
            ->values()
            ->all();
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
- Portrait format (4:5 aspect ratio) for Instagram and Facebook
- Vibrant plant photography with cinematic, natural lighting
- Rich composition that leaves the upper-left area visually calm for a text overlay
- Modern editorial aesthetic, shallow depth of field
- IMPORTANT: absolutely NO text, NO letters, NO words, NO logos, NO watermarks anywhere in the image
PROMPT;

        return $this->imageGenerator->generate($prompt, [
            'aspectRatio' => '4:5',
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
     * Publish to Instagram Business Account: a carousel when there's more than
     * one slide, a single photo post otherwise. Either way it's a 2-step
     * process — create the container(s), then publish.
     *
     * @param  list<string>  $imageUrls
     */
    private function publishToInstagram(Post $post, array $imageUrls, string $caption): ?string
    {
        $accountId = config('social.instagram.account_id');
        $accessToken = config('social.facebook.access_token'); // Uses same FB token

        if (! $accountId || ! $accessToken) {
            Log::warning('Instagram credentials not configured');

            return null;
        }

        try {
            $creationId = count($imageUrls) > 1
                ? $this->createInstagramCarouselContainer($post, $accountId, $accessToken, $imageUrls, $caption)
                : $this->createInstagramSingleContainer($post, $accountId, $accessToken, $imageUrls[0], $caption);

            if (! $creationId) {
                return null;
            }

            // Brief pause for Instagram to process the container(s)
            sleep(5);

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

    /**
     * Create a single-photo media container with its caption.
     */
    private function createInstagramSingleContainer(Post $post, string $accountId, string $accessToken, string $imageUrl, string $caption): ?string
    {
        $response = Http::post("https://graph.facebook.com/v21.0/{$accountId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            Log::error('Instagram container creation failed', [
                'post_id' => $post->id,
                'error' => $response->body(),
            ]);

            return null;
        }

        $creationId = $response->json('id');

        if (! $creationId) {
            Log::error('Instagram container returned no ID', ['post_id' => $post->id]);

            return null;
        }

        return $creationId;
    }

    /**
     * Create a carousel: one child container per slide (no caption on
     * children — captions only go on the parent), then a parent container
     * referencing all of them.
     *
     * @param  list<string>  $imageUrls
     */
    private function createInstagramCarouselContainer(Post $post, string $accountId, string $accessToken, array $imageUrls, string $caption): ?string
    {
        $childIds = [];

        foreach ($imageUrls as $imageUrl) {
            $childResponse = Http::post("https://graph.facebook.com/v21.0/{$accountId}/media", [
                'image_url' => $imageUrl,
                'is_carousel_item' => true,
                'access_token' => $accessToken,
            ]);

            if (! $childResponse->successful()) {
                Log::error('Instagram carousel item creation failed', [
                    'post_id' => $post->id,
                    'error' => $childResponse->body(),
                ]);

                return null;
            }

            $childId = $childResponse->json('id');

            if (! $childId) {
                Log::error('Instagram carousel item returned no ID', ['post_id' => $post->id]);

                return null;
            }

            $childIds[] = $childId;
        }

        $parentResponse = Http::post("https://graph.facebook.com/v21.0/{$accountId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if (! $parentResponse->successful()) {
            Log::error('Instagram carousel container creation failed', [
                'post_id' => $post->id,
                'error' => $parentResponse->body(),
            ]);

            return null;
        }

        $creationId = $parentResponse->json('id');

        if (! $creationId) {
            Log::error('Instagram carousel container returned no ID', ['post_id' => $post->id]);

            return null;
        }

        return $creationId;
    }
}
