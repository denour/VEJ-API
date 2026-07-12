<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Models\Post;
use App\Services\AI\AuthorDescriptionGeneratorService;
use App\Services\AI\PersonaPromptBuilder;
use App\Services\AI\PostGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostTextPromptGenerationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{prompt: string, options: array}> */
    private array $capturedCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /**
     * Run generatePostData with a text generator that captures every prompt
     * and returns canned responses in order.
     *
     * @param  list<string>  $responses
     */
    private function generateCapturingPrompts(Author $author, array $responses): array
    {
        $this->capturedCalls = [];
        $queue = $responses;

        $mockTextGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockTextGenerator->method('generate')
            ->willReturnCallback(function (string $prompt, array $options = []) use (&$queue) {
                $this->capturedCalls[] = ['prompt' => $prompt, 'options' => $options];

                return array_shift($queue) ?? 'respuesta de relleno';
            });

        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);
        $mockImageGenerator->method('generate')->willReturn('https://example.com/image.jpg');

        $service = new PostGeneratorService(
            $mockTextGenerator,
            $mockImageGenerator,
            new AuthorDescriptionGeneratorService($mockTextGenerator),
            new PersonaPromptBuilder
        );

        return $service->generatePostData($author);
    }

    private function makeAuthor(): Author
    {
        return Author::create([
            'name' => 'Clara Molina',
            'description' => 'Experta en sustratos',
            'tone' => 'didáctico y cercano',
            'personality_traits' => ['optimista pragmática', 'observadora'],
            'sentence_style' => 'oraciones claras con anécdotas',
        ]);
    }

    private function structureJson(): string
    {
        return json_encode([
            'title' => 'Sustratos vivos en balcones chicos',
            'excerpt' => 'Cómo armar un sustrato que retenga agua sin encharcarse.',
            'category' => 'Cuidado',
            'tags' => ['sustrato', 'balcón'],
            'blocks' => [
                ['type' => 'paragraph', 'description' => 'Apertura sobre sustratos'],
                ['type' => 'heading', 'description' => 'Sección de mezcla'],
                ['type' => 'paragraph', 'description' => 'Detalle de la mezcla'],
                ['type' => 'list', 'description' => 'Pasos de armado'],
                ['type' => 'quote', 'description' => 'Cita sobre paciencia'],
            ],
        ]);
    }

    private function cannedBlockResponses(): array
    {
        return [
            $this->structureJson(),
            'Primer párrafo sobre sustratos con fibra de coco.',
            'Cómo preparar la mezcla base',
            'Segundo párrafo con proporciones de perlita.',
            json_encode(['items' => ['Cierne la composta', 'Agrega perlita al 20%']]),
            json_encode(['text' => 'Quien siembra en buena tierra, cosecha sin apuro.', 'author' => 'Refrán popular']),
        ];
    }

    public function test_structure_prompt_includes_recent_titles_and_bans_template_phrasing(): void
    {
        $author = $this->makeAuthor();

        Post::factory()->create([
            'author_id' => $author->id,
            'title' => 'Riego eficiente en azoteas para verano',
            'published_at' => now()->subDay(),
        ]);

        $this->generateCapturingPrompts($author, $this->cannedBlockResponses());

        $structurePrompt = $this->capturedCalls[0]['prompt'];

        $this->assertStringContainsString('Riego eficiente en azoteas para verano', $structurePrompt);
        $this->assertStringContainsString('prohibido repetir sus temas', $structurePrompt);
        $this->assertStringContainsString('guía práctica', $structurePrompt);
        $this->assertStringContainsString('La cita es OPCIONAL', $structurePrompt);
        $this->assertStringNotContainsString('ESTRUCTURA OBLIGATORIA', $structurePrompt);
    }

    public function test_each_block_prompt_receives_previously_written_text(): void
    {
        $author = $this->makeAuthor();

        $this->generateCapturingPrompts($author, $this->cannedBlockResponses());

        $firstParagraphPrompt = $this->capturedCalls[1]['prompt'];
        $this->assertStringContainsString('este es el primer bloque', $firstParagraphPrompt);

        $secondParagraphPrompt = $this->capturedCalls[3]['prompt'];
        $this->assertStringContainsString('Primer párrafo sobre sustratos con fibra de coco.', $secondParagraphPrompt);
        $this->assertStringContainsString('Cómo preparar la mezcla base', $secondParagraphPrompt);
        $this->assertStringContainsString('NO repitas consejos', $secondParagraphPrompt);

        $listPrompt = $this->capturedCalls[4]['prompt'];
        $this->assertStringContainsString('Segundo párrafo con proporciones de perlita.', $listPrompt);
    }

    public function test_block_calls_use_author_persona_system_prompt_when_voice_bible_is_empty(): void
    {
        $author = $this->makeAuthor();

        $this->generateCapturingPrompts($author, $this->cannedBlockResponses());

        $paragraphOptions = $this->capturedCalls[1]['options'];

        $this->assertArrayHasKey('system', $paragraphOptions);
        $this->assertStringContainsString('Eres Clara Molina', $paragraphOptions['system']);
        $this->assertStringContainsString('didáctico y cercano', $paragraphOptions['system']);
        $this->assertStringContainsString('no es solo X, es Y', $paragraphOptions['system']);
    }

    public function test_quote_prompt_forbids_motivational_abstraction(): void
    {
        $author = $this->makeAuthor();

        $this->generateCapturingPrompts($author, $this->cannedBlockResponses());

        $quotePrompt = $this->capturedCalls[5]['prompt'];

        $this->assertStringContainsString('refrán o dicho popular REAL', $quotePrompt);
        $this->assertStringContainsString('PROHIBIDO', $quotePrompt);
        $this->assertStringNotContainsString('Inspiradora y memorable', $quotePrompt);
    }

    public function test_heading_prompt_lists_already_used_headings(): void
    {
        $author = $this->makeAuthor();

        $structure = json_decode($this->structureJson(), true);
        $structure['blocks'][] = ['type' => 'heading', 'description' => 'Segunda sección'];

        $responses = $this->cannedBlockResponses();
        $responses[0] = json_encode($structure);
        $responses[] = 'Errores comunes al regar';

        $this->generateCapturingPrompts($author, $responses);

        $secondHeadingPrompt = $this->capturedCalls[6]['prompt'];

        $this->assertStringContainsString('Subtítulos ya usados', $secondHeadingPrompt);
        $this->assertStringContainsString('Cómo preparar la mezcla base', $secondHeadingPrompt);
    }
}
