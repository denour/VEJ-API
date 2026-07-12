<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Jobs\PollImageGenerationStatus;
use App\Models\Author;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
        private readonly AuthorDescriptionGeneratorService $authorDescriptionService,
        private readonly PersonaPromptBuilder $personaPromptBuilder,
    ) {}

    /**
     * Generate a complete post with AI-generated content.
     */
    public function generatePost(Author $author, ?string $topic = null, array $options = []): Post
    {
        $data = $this->generatePostData($author, $topic, $options);

        $slug = Str::slug($data['title']);

        return DB::transaction(function () use ($slug, $data, $author, $options) {
            $post = Post::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'], // Keep for backward compatibility
                    'list' => [], // Will be auto-generated from heading blocks
                    'category' => $data['category'],
                    'tags' => $data['tags'],
                    'author_id' => $author->id,
                    'status' => $options['status'] ?? 'draft',
                    'featured' => false,
                    'reading_time' => $data['reading_time'],
                    'published_at' => $options['published_at'] ?? null,
                ]
            );

            // Delete existing blocks and create new ones
            $post->blocks()->delete();

            // Create PostBlock records from content
            $order = 0;
            foreach ($data['content'] as $blockData) {
                $this->createPostBlock($post, $blockData, $order);
                $order++;
            }

            // Auto-generate table of contents from heading blocks
            $toc = $post->generateTableOfContents();
            $post->update(['list' => $toc]);

            // Generate images for the post (cover + block images)
            $this->generateImagesForPost($post, $data['structure']);

            return $post;
        });
    }

    /**
     * Generate a post using an Author instead of Author.
     */
    public function generatePostWithPersona(Author $persona, ?string $topic = null, array $options = []): Post
    {
        $data = $this->generatePostDataWithPersona($persona, $topic, $options);

        $slug = Str::slug($data['title']);

        return DB::transaction(function () use ($slug, $data) {
            $post = Post::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'list' => [],
                    'category' => $data['category'],
                    'tags' => $data['tags'],
                    'author_id' => $author->id,
                    'status' => 'draft',
                    'featured' => false,
                    'reading_time' => $data['reading_time'],
                ]
            );

            // Delete existing blocks and create new ones
            $post->blocks()->delete();

            // Create PostBlock records from content
            $order = 0;
            foreach ($data['content'] as $blockData) {
                $this->createPostBlock($post, $blockData, $order);
                $order++;
            }

            // Auto-generate table of contents
            $toc = $post->generateTableOfContents();
            $post->update(['list' => $toc]);

            // Track usage
            $author->incrementPostCount();

            // Generate images
            $this->generateImagesForPost($post, $data['structure']);

            return $post;
        });
    }

    /**
     * Generate post data with persona.
     */
    public function generatePostDataWithPersona(Author $persona, ?string $topic = null, array $options = []): array
    {
        $structure = $this->generatePostStructureWithPersona($persona, $topic, $options);
        $contentBlocks = $this->generateContentBlocksWithPersona($structure['blocks'], $persona);
        $tableOfContents = $this->generateTableOfContents($contentBlocks);

        return [
            'title' => $structure['title'],
            'excerpt' => $structure['excerpt'],
            'content' => $contentBlocks,
            'list' => $tableOfContents,
            'category' => $structure['category'],
            'tags' => $structure['tags'],
            'reading_time' => $this->estimateReadingTime($contentBlocks),
            'structure' => $structure,
        ];
    }

    /**
     * Generate post data without creating a database record.
     * Returns an array with all the generated content.
     *
     * @return array{title: string, excerpt: string, content: array, list: array, category: string, tags: array, reading_time: int, structure: array}
     */
    public function generatePostData(Author $author, ?string $topic = null, array $options = []): array
    {
        $authorAttributes = $this->extractAuthorAttributes($author);
        $authorAttributes['voice_bible'] = $author->voice_bible;
        $authorAttributes['author_name'] = $author->name;
        $structure = $this->generatePostStructure($author, $authorAttributes, $topic, $options);
        $contentBlocks = $this->generateContentBlocks($structure, $authorAttributes);
        $tableOfContents = $this->generateTableOfContents($contentBlocks);

        return [
            'title' => $structure['title'],
            'excerpt' => $structure['excerpt'],
            'content' => $contentBlocks,
            'list' => $tableOfContents,
            'category' => $structure['category'],
            'tags' => $structure['tags'],
            'reading_time' => $this->estimateReadingTime($contentBlocks),
            'structure' => $structure,
        ];
    }

    /**
     * Generate images for an existing post.
     * This includes the cover image and any content block images.
     */
    public function generateImagesForPost(Post $post, ?array $structure = null): void
    {
        $title = $post->title;
        $excerpt = $post->excerpt ?? '';

        $this->generateCoverImage($post, $title, $excerpt);

        if ($structure && ! empty($structure['blocks'])) {
            $this->generateContentImages($post, $structure['blocks']);
        } elseif (! empty($post->content) && is_array($post->content)) {
            // If no structure provided, use content blocks to find image blocks
            $this->generateContentImagesFromContent($post);
        }
    }

    /**
     * Generate images for PostBlock records that need images.
     */
    private function generateContentImagesFromContent(Post $post): void
    {
        // Use PostBlocks instead of JSON content
        $imageBlocks = $post->blocks()->where('type', 'image')->get();

        foreach ($imageBlocks as $block) {
            if ($block->hasPendingImage()) {
                // Skip if there's already a pending request for this block
                if (ImageGenerationRequest::hasPendingRequest(PostBlock::class, $block->id, 'data.url')) {
                    continue;
                }

                $description = $block->data['alt'] ?? $block->data['caption'] ?? 'Beautiful garden plant';
                $imagePrompt = $this->generateImagePrompt($description);

                try {
                    $options = ['aspectRatio' => '16:9', 'resolution' => '2K'];

                    if (! $this->imageGenerator->isSynchronous()) {
                        $options['imageUrls'] = [''];
                        $options['callBackUrl'] = url('api/webhooks/banana');
                    }

                    $response = $this->imageGenerator->generate($imagePrompt, $options);

                    if ($this->imageGenerator->isSynchronous()) {
                        $data = $block->data ?? [];
                        $data['url'] = $response;
                        $block->update(['data' => $data]);

                        ImageGenerationRequest::query()->create([
                            'external_id' => null,
                            'post_id' => $post->id,
                            'targetable_type' => PostBlock::class,
                            'targetable_id' => $block->id,
                            'prompt' => $imagePrompt,
                            'status' => 'completed',
                            'image_url' => $response,
                            'metadata' => [
                                'attribute' => 'data.url',
                                'model_name' => 'PostBlock',
                                'block_id' => $block->id,
                                'provider' => $this->imageGenerator->getProviderName(),
                            ],
                        ]);
                    } else {
                        $request = ImageGenerationRequest::query()->create([
                            'external_id' => $response,
                            'post_id' => $post->id,
                            'targetable_type' => PostBlock::class,
                            'targetable_id' => $block->id,
                            'prompt' => $imagePrompt,
                            'status' => 'pending',
                            'metadata' => [
                                'attribute' => 'data.url',
                                'model_name' => 'PostBlock',
                                'block_id' => $block->id,
                                'provider' => $this->imageGenerator->getProviderName(),
                            ],
                        ]);

                        PollImageGenerationStatus::dispatch($request)->delay(now()->addSeconds(60));
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to generate content image', [
                        'post_id' => $post->id,
                        'block_id' => $block->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Extract author attributes from detailed description.
     */
    private function extractAuthorAttributes(Author $author): array
    {
        return [
            'tone' => $author->tone ?? 'conversacional y educativo',
            'personality' => ! empty($author->personality_traits)
                ? implode(', ', $author->personality_traits)
                : 'entusiasta',
            'writing_style' => $author->sentence_style ?? 'claro y accesible',
            'themes' => $author->expertise_areas ?? ['jardinería', 'plantas'],
            'editorial_focus' => ! empty($author->recurring_topics)
                ? implode(', ', $author->recurring_topics)
                : 'educación práctica',
        ];
    }

    /**
     * Generate the post structure (title, blocks outline).
     */
    private function generatePostStructure(Author $author, array $authorAttributes, ?string $topic, array $options): array
    {
        $topicInstruction = $topic ? "El tema del post debe ser: {$topic}" : 'Elige un tema relevante';
        $themesString = is_array($authorAttributes['themes']) ? implode(', ', $authorAttributes['themes']) : $authorAttributes['themes'];
        $editorialFocus = is_array($authorAttributes['editorial_focus']) ? implode(', ', $authorAttributes['editorial_focus']) : $authorAttributes['editorial_focus'];

        $lengthInstruction = match ($options['length'] ?? 'medium') {
            'short' => 'El post debe ser corto (4-6 bloques)',
            'long' => 'El post debe ser largo (9-12 bloques)',
            default => 'El post debe ser de longitud media (6-9 bloques)',
        };

        $recentTitles = $this->recentPostTitles();
        $recentTitlesSection = empty($recentTitles)
            ? ''
            : "\nTÍTULOS RECIENTES DEL BLOG — prohibido repetir sus temas y prohibido imitar sus patrones de redacción:\n- ".implode("\n- ", $recentTitles)."\n";

        $prompt = <<<PROMPT
Genera la estructura editorial para un artículo del blog "Vida en el Jardín" (blog mexicano de jardinería urbana).

AUTOR: {$author->name}
- Tono: {$authorAttributes['tone']}
- Personalidad: {$authorAttributes['personality']}
- Estilo: {$authorAttributes['writing_style']}
- Especialidades: {$themesString}
{$topicInstruction}
{$lengthInstruction}
{$recentTitlesSection}
DISEÑA LA ESTRUCTURA TÚ MISMO — que no parezca plantilla. Restricciones:
- Entre 2 y 4 secciones con heading H2. Los headings no parafrasean el título ni se repiten entre sí; nada de headings genéricos tipo "Cierre y reflexión".
- Exactamente 1 bloque de imagen, colocado donde mejor apoye al contenido (varía su posición, no siempre a la mitad).
- Incluye una lista SOLO si el tema realmente la pide (pasos, calendario, dosis). Si el tema es de diseño, identificación o reflexión, omítela.
- La cita es OPCIONAL: inclúyela solo si un refrán popular real o una observación concreta del autor le suma algo. Nunca una frase motivacional abstracta.
- El cierre puede ser: un error común y cómo evitarlo, una anécdota breve del autor, un experimento propuesto al lector, o simplemente el consejo más importante al final. NO "reflexión final" genérica, y NO uses el mismo tipo de cierre que sugieren los títulos recientes — varía.
- Mínimo 5 párrafos en total. Cada description debe ser específica y no traslapar con otro bloque.

TÍTULO Y EXCERPT:
- Título: 40-65 caracteres, español mexicano. PROHIBIDO usar dos puntos (:) en el título — nada de "Tema: subtítulo". Usa pregunta directa, afirmación, cómo/por qué o un dato. Prohibido "guía práctica", "tips esenciales", "todo lo que necesitas saber".
- Excerpt: máximo 160 caracteres, 1-2 oraciones concretas. Prohibido empezar con "Descubre", "Aprende" o "Conoce".

Responde ÚNICAMENTE en JSON válido:
{
    "title": "Título del artículo",
    "excerpt": "Resumen concreto",
    "category": "Una de: Cuidado, Identificación, Decoración, Herramientas, Consejos",
    "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"],
    "blocks": [
        {"type": "paragraph|heading|image|list|quote", "description": "qué cubre este bloque, específico"}
    ]
}
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 1500,
        ]);

        // Limpiar la respuesta (remover markdown code blocks si existen)
        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        return json_decode(trim($response), true);
    }

    /**
     * Generate content for each block, threading what's already written into
     * every call so blocks don't re-introduce the topic or repeat advice.
     */
    private function generateContentBlocks(array $structure, array $authorAttributes): array
    {
        $blocks = $structure['blocks'] ?? [];
        $title = $structure['title'] ?? '';
        $systemPrompt = $this->buildAuthorSystemPrompt($authorAttributes);

        $outline = implode("\n", array_map(
            fn (array $b): string => "- [{$b['type']}] {$b['description']}",
            $blocks
        ));

        $contentBlocks = [];
        $writtenSoFar = [];
        $usedHeadings = [];

        foreach ($blocks as $index => $block) {
            $context = $this->buildRunningContext($title, $outline, $writtenSoFar);

            $contentBlock = match ($block['type']) {
                'heading' => $this->generateHeading($block['description'], $title, $usedHeadings, $systemPrompt),
                'image' => $this->generateImageBlock($block['description']),
                'list' => $this->generateList($block['description'], $context, $systemPrompt),
                'quote' => $this->generateQuote($block['description'], $title, $authorAttributes, $systemPrompt),
                default => $this->generateParagraph($block['description'], $context, $systemPrompt),
            };

            $contentBlocks[] = $contentBlock;

            switch ($contentBlock['type']) {
                case 'paragraph':
                    $writtenSoFar[] = $contentBlock['data']['text'];
                    break;
                case 'heading':
                    $writtenSoFar[] = '## '.$contentBlock['data']['text'];
                    $usedHeadings[] = $contentBlock['data']['text'];
                    break;
                case 'list':
                    $writtenSoFar[] = "Lista:\n- ".implode("\n- ", $contentBlock['data']['items']);
                    break;
                case 'quote':
                    $writtenSoFar[] = 'Cita: "'.$contentBlock['data']['text'].'"';
                    break;
            }
        }

        return $contentBlocks;
    }

    /**
     * Build the shared context injected into each block-generation call.
     */
    private function buildRunningContext(string $title, string $outline, array $writtenSoFar): string
    {
        $written = empty($writtenSoFar)
            ? '(nada todavía — este es el primer bloque del artículo)'
            : implode("\n\n", $writtenSoFar);

        return <<<CONTEXT
Estás escribiendo el artículo "{$title}" para el blog "Vida en el Jardín".

ESQUEMA COMPLETO DEL ARTÍCULO:
{$outline}

TEXTO YA ESCRITO HASTA AHORA:
{$written}
CONTEXT;
    }

    /**
     * System prompt with the author's persona plus hard anti-slop style rules.
     * Falls back to the structured persona attributes when voice_bible is empty.
     */
    private function buildAuthorSystemPrompt(array $authorAttributes): string
    {
        $authorName = $authorAttributes['author_name'] ?? 'el autor';

        $system = <<<SYSTEM
Eres {$authorName}, columnista de "Vida en el Jardín", un blog mexicano de jardinería urbana. Escribes SIEMPRE en primera persona, en español mexicano natural.

TU VOZ:
- Tono: {$authorAttributes['tone']}
- Rasgos: {$authorAttributes['personality']}
- Estilo: {$authorAttributes['writing_style']}

REGLAS DE ESTILO (obligatorias, por encima de todo lo demás):
- Suena a persona, no a folleto: prohibido "descubre", "sumérgete", "transforma tu espacio", "tu aliado perfecto", "manos a la obra".
- Prohibida la fórmula "no es solo X, es Y", los cierres motivacionales y los llamados a compartir o etiquetar.
- Evita tríadas de sustantivos o adjetivos ("cultivar, alimentar y soñar"). Máximo UNA imagen sensorial o metáfora por artículo; el resto, concreto.
- Lo concreto le gana a lo poético: especies, cantidades, frecuencias, costos aproximados, errores que tú ya cometiste.
- Varía la longitud de tus frases; incluye alguna corta. Puedes empezar una frase con "Y" o "Pero".
- No expliques lo obvio ni repitas una idea con otras palabras.
SYSTEM;

        if (! empty($authorAttributes['voice_bible'])) {
            $system .= "\n\nGUÍA DE VOZ DEL AUTOR:\n{$authorAttributes['voice_bible']}";
        }

        return $system;
    }

    /**
     * Titles of the most recent published posts, to steer new structures away
     * from repeating topics and title patterns.
     *
     * @return list<string>
     */
    private function recentPostTitles(int $limit = 12): array
    {
        return Post::query()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->pluck('title')
            ->all();
    }

    private function generateParagraph(string $description, string $context, string $systemPrompt): array
    {
        $prompt = <<<PROMPT
{$context}

ESCRIBE EL SIGUIENTE PÁRRAFO. Tema de este párrafo: {$description}

REGLAS:
- Entre 80 y 160 palabras, texto plano sin markdown.
- Continúa el hilo: NO vuelvas a presentar el tema, NO repitas consejos ni ejemplos ya dados, y NUNCA repitas textualmente una oración que ya aparece en el texto de arriba.
- No arranques igual que ningún párrafo anterior (si otro ya abre con pregunta, con "Si..." o con "En...", entra distinto).
- Aporta al menos un dato concreto nuevo, de tipo DISTINTO a los ya usados arriba: especie, cantidad, frecuencia o error común. Menciona precios máximo UNA vez en todo el artículo — si ya hay un precio arriba, no des otro.
- Prohibido el molde "no es solo X, es Y" y sus variantes.
- Estas instrucciones son invisibles para el lector: jamás menciones frases como "dato concreto", "como regla" o cualquier eco de estas reglas dentro del párrafo.
- Responde ÚNICAMENTE con el texto del párrafo.
PROMPT;

        $text = $this->textGenerator->generate($prompt, [
            'max_tokens' => 400,
            'system' => $systemPrompt,
        ]);

        return [
            'type' => 'paragraph',
            'data' => ['text' => trim($text)],
        ];
    }

    private function generateHeading(string $description, string $title, array $usedHeadings, string $systemPrompt): array
    {
        $usedSection = empty($usedHeadings)
            ? ''
            : "\nSubtítulos ya usados en este artículo (no los repitas ni los parafrasees):\n- ".implode("\n- ", $usedHeadings)."\n";

        $prompt = <<<PROMPT
Genera un subtítulo de sección (H2) para el artículo "{$title}".

Sección: {$description}
{$usedSection}
REGLAS:
- Máximo 8 palabras en español, sin puntuación final ni formato.
- Específico del contenido de la sección; prohibidos los genéricos ("Cierre y reflexión", "Consejos prácticos").
- No parafrasees el título del artículo ni repitas sus mismas palabras clave.

Responde SOLO con el texto del subtítulo.
PROMPT;

        $text = $this->textGenerator->generate($prompt, [
            'max_tokens' => 50,
            'system' => $systemPrompt,
        ]);

        return [
            'type' => 'heading',
            'data' => ['text' => trim($text), 'level' => 2],
        ];
    }

    private function generateImageBlock(string $description): array
    {
        // Return image block structure without URL - it will be populated by webhook later
        return [
            'type' => 'image',
            'data' => [
                'url' => null,
                'alt' => $description,
                'caption' => $description,
            ],
        ];
    }

    private function generateImagePrompt(string $description): string
    {
        $prompt = <<<PROMPT
You are an image-prompt generator.

TASK:
Generate ONE single image-generation prompt in English, based on the following gardening blog description:

{$description}

RULES:
- Output ONLY the final image prompt.
- Do NOT include explanations, titles, headings, variants, bullet points, or commentary.
- Do NOT mention tools, platforms, or engines.
- Do NOT add negative prompts unless explicitly asked.
- Write in a single paragraph.
- The result must be directly usable in an image generation model.
- No Text, no watermark, no formatting.

IMAGE STYLE REQUIREMENTS:
Photorealistic image of plants or gardening with:
lush tropical foliage, natural imperfections, cinematic warm color grading,
subtle film grain, soft diffused golden-hour light filtering through leaves,
balanced immersive composition centered on the main plant,
surrounding complementary greenery and shallow depth of field.
PROMPT;

        return $this->textGenerator->generate($prompt, [
            'max_tokens' => 200,
        ]);
    }

    private function generateList(string $description, string $context, string $systemPrompt): array
    {
        $prompt = <<<PROMPT
{$context}

AHORA GENERA LA LISTA de este bloque: {$description}

REGLAS:
- Entre 4 y 7 items, cada uno de 1-2 oraciones.
- Cada item aporta algo NUEVO: nada de repetir consejos que ya aparecen en el texto de arriba.
- Concreto y accionable: cantidades, frecuencias, especies, herramientas. Nada de items de relleno ("disfruta el proceso").
- Varía cómo empieza cada item (no todos con verbo en imperativo).

Responde en formato JSON:
{
    "items": ["item 1", "item 2", "item 3"]
}
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'max_tokens' => 400,
            'system' => $systemPrompt,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $data = json_decode(trim($response), true);

        return [
            'type' => 'list',
            'data' => [
                'items' => $data['items'] ?? [],
                'style' => 'unordered',
            ],
        ];
    }

    private function generateQuote(string $description, string $title, array $authorAttributes = [], string $systemPrompt = ''): array
    {
        $authorName = $authorAttributes['author_name'] ?? 'el autor';

        $prompt = <<<PROMPT
Para el artículo "{$title}" genera UNA cita ({$description}). Elige la opción que mejor le quede al tema:

a) Un refrán o dicho popular REAL del español relacionado con el campo, la siembra o la paciencia (author: "Refrán popular").
b) Una cita REAL de una persona real, solo si estás seguro de que existe (author: su nombre).
c) Una observación breve, concreta y en primera persona del propio autor, sacada de su experiencia (author: "{$authorName}").

PROHIBIDO: frases motivacionales abstractas ("la ciudad florece cuando...", "cada semilla es una promesa..."), metáforas encadenadas, y las palabras "esperanza", "alma", "corazón", "susurro", "soñar".
Entre 10 y 25 palabras, en español.

Responde en formato JSON: {"text": "La cita", "author": "Autor"}
PROMPT;

        $options = ['max_tokens' => 150];

        if ($systemPrompt !== '') {
            $options['system'] = $systemPrompt;
        }

        $response = $this->textGenerator->generate($prompt, $options);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $data = json_decode(trim($response), true);

        return [
            'type' => 'quote',
            'data' => [
                'text' => $data['text'] ?? '',
                'author' => $data['author'] ?? 'Anónimo',
            ],
        ];
    }

    /**
     * Create a PostBlock record from block data.
     */
    private function createPostBlock(Post $post, array $blockData, int $order): PostBlock
    {
        $type = $blockData['type'];
        $data = $blockData['data'] ?? [];

        $block = new PostBlock([
            'type' => $type,
            'order' => $order,
        ]);

        switch ($type) {
            case 'paragraph':
                $block->content = $data['text'] ?? null;
                break;

            case 'heading':
                $block->title = $data['text'] ?? null;
                $block->data = ['level' => $data['level'] ?? 2];
                break;

            case 'image':
                $block->data = [
                    'url' => $data['url'] ?? null,
                    'alt' => $data['alt'] ?? null,
                    'caption' => $data['caption'] ?? null,
                    'prompt' => $data['prompt'] ?? null,
                ];
                break;

            case 'list':
                $block->title = $data['title'] ?? null;
                $block->data = [
                    'items' => $data['items'] ?? [],
                    'ordered' => $data['ordered'] ?? false,
                ];
                break;

            case 'quote':
                $block->content = $data['text'] ?? null;
                $block->data = [
                    'author' => $data['author'] ?? null,
                    'source' => $data['source'] ?? null,
                ];
                break;

            case 'code':
                $block->title = $data['title'] ?? null;
                $block->content = $data['code'] ?? null;
                $block->data = [
                    'language' => $data['language'] ?? 'text',
                    'filename' => $data['filename'] ?? null,
                ];
                break;

            case 'video':
                $block->title = $data['title'] ?? null;
                $block->data = [
                    'url' => $data['url'] ?? null,
                    'provider' => $data['provider'] ?? 'youtube',
                    'thumbnail' => $data['thumbnail'] ?? null,
                    'caption' => $data['caption'] ?? null,
                ];
                break;
        }

        $post->blocks()->save($block);

        return $block;
    }

    /**
     * Generate images for content blocks that have type 'image'.
     */
    private function generateContentImages(Post $post, array $blocks): void
    {
        foreach ($blocks as $index => $block) {
            if ($block['type'] === 'image') {
                // Find the corresponding PostBlock record
                $postBlock = $post->blocks()->where('type', 'image')->where('order', $index)->first();

                if (! $postBlock) {
                    \Illuminate\Support\Facades\Log::warning('PostBlock not found for image generation', [
                        'post_id' => $post->id,
                        'block_index' => $index,
                    ]);

                    continue;
                }

                // Skip if there's already a pending request for this block
                if (ImageGenerationRequest::hasPendingRequest(PostBlock::class, $postBlock->id, 'data.url')) {
                    continue;
                }

                $imagePrompt = $this->generateImagePrompt($block['description']);

                try {
                    $options = ['aspectRatio' => '16:9', 'resolution' => '2K'];

                    if (! $this->imageGenerator->isSynchronous()) {
                        $options['imageUrls'] = [''];
                        $options['callBackUrl'] = url('api/webhooks/banana');
                    }

                    $response = $this->imageGenerator->generate($imagePrompt, $options);

                    if ($this->imageGenerator->isSynchronous()) {
                        $data = $postBlock->data ?? [];
                        $data['url'] = $response;
                        $postBlock->update(['data' => $data]);

                        ImageGenerationRequest::query()->create([
                            'external_id' => null,
                            'post_id' => $post->id,
                            'targetable_type' => PostBlock::class,
                            'targetable_id' => $postBlock->id,
                            'prompt' => $imagePrompt,
                            'status' => 'completed',
                            'image_url' => $response,
                            'metadata' => [
                                'attribute' => 'data.url',
                                'model_name' => 'PostBlock',
                                'block_id' => $postBlock->id,
                                'provider' => $this->imageGenerator->getProviderName(),
                            ],
                        ]);
                    } else {
                        $request = ImageGenerationRequest::query()->create([
                            'external_id' => $response,
                            'post_id' => $post->id,
                            'targetable_type' => PostBlock::class,
                            'targetable_id' => $postBlock->id,
                            'prompt' => $imagePrompt,
                            'status' => 'pending',
                            'metadata' => [
                                'attribute' => 'data.url',
                                'model_name' => 'PostBlock',
                                'block_id' => $postBlock->id,
                                'provider' => $this->imageGenerator->getProviderName(),
                            ],
                        ]);

                        PollImageGenerationStatus::dispatch($request)->delay(now()->addSeconds(60));
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to generate content image', [
                        'post_id' => $post->id,
                        'block_id' => $postBlock->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Generate cover image for the post.
     */
    private function generateCoverImage(Post $post, string $title, string $excerpt): void
    {
        // Skip if post already has a cover image or there's a pending request
        if (! empty($post->cover_image)) {
            return;
        }

        if (ImageGenerationRequest::hasPendingRequest(Post::class, $post->id, 'cover_image')) {
            return;
        }

        $prompt = <<<PROMPT
Create a captivating, photorealistic hero PHOTOGRAPH for a gardening blog.

Visual theme (use ONLY as inspiration for the scene — never render this text, or any words, in the image): {$excerpt}

Requirements:
- A real-looking photograph of plants, gardens or greenery, as if shot with a professional camera
- Lush, vibrant foliage; warm, inviting natural light; cinematic composition with depth and shallow depth of field
- The natural scene must fill the entire frame, edge to edge
- Absolutely NO text, NO words, NO letters, NO numbers, NO captions, NO titles, NO typography
- NO logos, NO watermarks, NO icons, NO badges, NO labels, NO color side-panels or borders
- This is NOT an infographic, NOT a poster, NOT a banner, NOT a flyer, NOT a graphic-design layout — only a clean, natural photograph
PROMPT;

        try {
            $options = ['aspectRatio' => '16:9', 'resolution' => '2K', 'directory' => 'posts'];

            if (! $this->imageGenerator->isSynchronous()) {
                $options['imageUrls'] = [''];
                $options['callBackUrl'] = url('api/webhooks/banana');
            }

            $response = $this->imageGenerator->generate($prompt, $options);

            if ($this->imageGenerator->isSynchronous()) {
                $post->update(['cover_image' => $response]);

                ImageGenerationRequest::query()->create([
                    'external_id' => null,
                    'post_id' => $post->id,
                    'targetable_type' => Post::class,
                    'targetable_id' => $post->id,
                    'prompt' => $prompt,
                    'status' => 'completed',
                    'image_url' => $response,
                    'metadata' => [
                        'attribute' => 'cover_image',
                        'model_name' => 'Post',
                        'provider' => $this->imageGenerator->getProviderName(),
                    ],
                ]);
            } else {
                $request = ImageGenerationRequest::query()->create([
                    'external_id' => $response,
                    'post_id' => $post->id,
                    'targetable_type' => Post::class,
                    'targetable_id' => $post->id,
                    'prompt' => $prompt,
                    'status' => 'pending',
                    'metadata' => [
                        'attribute' => 'cover_image',
                        'model_name' => 'Post',
                        'provider' => $this->imageGenerator->getProviderName(),
                    ],
                ]);

                PollImageGenerationStatus::dispatch($request)->delay(now()->addSeconds(60));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate post cover image', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate table of contents from headings in content blocks.
     */
    private function generateTableOfContents(array $contentBlocks): array
    {
        $toc = [];

        foreach ($contentBlocks as $index => $block) {
            if ($block['type'] === 'heading') {
                $toc[] = [
                    'id' => 'section-'.$index,
                    'text' => $block['data']['text'],
                ];
            }
        }

        return $toc;
    }

    /**
     * Estimate reading time based on content.
     */
    private function estimateReadingTime(array $contentBlocks): int
    {
        $wordCount = 0;

        foreach ($contentBlocks as $block) {
            $text = match ($block['type']) {
                'paragraph' => $block['data']['text'] ?? '',
                'heading' => $block['data']['text'] ?? '',
                'list' => implode(' ', $block['data']['items'] ?? []),
                'quote' => $block['data']['text'] ?? '',
                default => '',
            };

            $wordCount += str_word_count($text);
        }

        // Average reading speed: 200 words per minute
        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Generate post structure with persona.
     */
    private function generatePostStructureWithPersona(Author $persona, ?string $topic, array $options): array
    {
        $topicInstruction = $topic ? "The post topic should be: {$topic}" : 'Choose a relevant topic';

        $lengthInstruction = match ($options['length'] ?? 'medium') {
            'short' => 'The post should be short (4-5 blocks)',
            'long' => 'The post should be long (8-10 blocks)',
            default => 'The post should be medium length (5-8 blocks)',
        };

        $systemPrompt = $this->personaPromptBuilder->buildSystemPrompt($persona);
        $userPrompt = <<<PROMPT
{$topicInstruction}
{$lengthInstruction}

Create a structure for a gardening blog post. Return a JSON with this exact structure:
{
  "title": "Post title",
  "excerpt": "Brief summary (2-3 sentences)",
  "category": "gardening category",
  "tags": ["tag1", "tag2", "tag3"],
  "blocks": [
    {"type": "paragraph", "description": "Introduction - what this block will cover"},
    {"type": "heading", "description": "Section title"},
    {"type": "paragraph", "description": "Content description"},
    {"type": "list", "description": "List of items", "ordered": false},
    {"type": "image", "description": "What the image should show"},
    {"type": "quote", "description": "Inspirational quote"}
  ]
}

Stay in character. Write titles and descriptions that match your voice.
PROMPT;

        $response = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 1000,
        ]);

        return $this->parseStructureResponse($response);
    }

    /**
     * Generate content blocks with persona.
     */
    private function generateContentBlocksWithPersona(array $blocks, Author $persona): array
    {
        $content = [];
        $systemPrompt = $this->personaPromptBuilder->buildSystemPrompt($persona);

        foreach ($blocks as $block) {
            $blockType = $block['type'];
            $description = $block['description'];

            $blockContent = match ($blockType) {
                'paragraph' => $this->generateParagraphWithPersona($description, $persona, $systemPrompt),
                'heading' => $this->generateHeadingWithPersona($description, $persona, $systemPrompt),
                'list' => $this->generateListWithPersona($description, $block['ordered'] ?? false, $persona, $systemPrompt),
                'quote' => $this->generateQuoteWithPersona($description, $persona, $systemPrompt),
                'image' => $this->generateImageBlock($description),
                'code' => $this->generateCodeBlock($description),
                'video' => $this->generateVideoBlock($description),
                default => null,
            };

            if ($blockContent) {
                $content[] = $blockContent;
            }
        }

        return $content;
    }

    private function generateParagraphWithPersona(string $description, Author $persona, string $systemPrompt): array
    {
        $userPrompt = $this->personaPromptBuilder->buildParagraphPrompt($persona, $description);

        $text = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 400,
        ]);

        return [
            'type' => 'paragraph',
            'data' => ['text' => trim($text)],
        ];
    }

    private function generateHeadingWithPersona(string $description, Author $persona, string $systemPrompt): array
    {
        $userPrompt = $this->personaPromptBuilder->buildHeadingPrompt($persona, $description);

        $text = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 50,
        ]);

        return [
            'type' => 'heading',
            'data' => [
                'text' => trim($text),
                'level' => 2,
            ],
        ];
    }

    private function generateListWithPersona(string $description, bool $ordered, Author $persona, string $systemPrompt): array
    {
        $userPrompt = $this->personaPromptBuilder->buildListPrompt($persona, $description, $ordered);

        $response = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 400,
        ]);

        $items = $this->parseListResponse($response);

        return [
            'type' => 'list',
            'data' => [
                'style' => $ordered ? 'ordered' : 'unordered',
                'items' => $items,
            ],
        ];
    }

    private function generateQuoteWithPersona(string $description, Author $persona, string $systemPrompt): array
    {
        $userPrompt = $this->personaPromptBuilder->buildQuotePrompt($persona, $description);

        $text = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 150,
        ]);

        return [
            'type' => 'quote',
            'data' => [
                'text' => trim($text),
                'caption' => $author->name,
            ],
        ];
    }
}
