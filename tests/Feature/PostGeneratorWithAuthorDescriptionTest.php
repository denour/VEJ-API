<?php

namespace Tests\Feature;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Services\AI\AuthorDescriptionGeneratorService;
use App\Services\AI\PersonaPromptBuilder;
use App\Services\AI\PostGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostGeneratorWithAuthorDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_post_with_author_detailed_description(): void
    {
        $author = Author::create([
            'name' => 'Laura Fernández',
            'description' => 'Experta en plantas tropicales',
            'detailed_description' => "Tono: Entusiasta y cercano\nPersonalidad: Apasionada y educativa\nTemas: Plantas tropicales, cuidados de interior, jardinería sostenible",
        ]);

        $mockTextGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);

        $jsonStructure = json_encode([
            'title' => 'Cuidados Esenciales para Plantas Tropicales',
            'excerpt' => 'Aprende a mantener tus plantas tropicales saludables.',
            'category' => 'Cuidado',
            'tags' => ['tropicales', 'interior', 'cuidados'],
            'blocks' => [
                ['type' => 'heading', 'description' => 'Introducción'],
                ['type' => 'paragraph', 'description' => 'Descripción de plantas tropicales'],
            ],
        ]);

        $mockTextGenerator->method('generate')
            ->willReturnOnConsecutiveCalls(
                $jsonStructure,
                'Introducción',
                'Las plantas tropicales son maravillosas y con los cuidados adecuados prosperarán en tu hogar.'
            );

        $mockImageGenerator->method('generate')
            ->willReturn('https://example.com/image.jpg');

        $authorDescriptionService = new AuthorDescriptionGeneratorService($mockTextGenerator);
        $service = new PostGeneratorService($mockTextGenerator, $mockImageGenerator, $authorDescriptionService, new PersonaPromptBuilder);

        $post = $service->generatePost($author);

        $this->assertNotNull($post->id);
        $this->assertEquals($author->id, $post->author_id);
        $this->assertEquals('Cuidados Esenciales para Plantas Tropicales', $post->title);
        $this->assertEquals('Cuidado', $post->category);
        $this->assertIsArray($post->content);
        $this->assertIsArray($post->tags);
    }

    public function test_generates_post_with_custom_topic(): void
    {
        $author = Author::create([
            'name' => 'Pedro Martínez',
            'description' => 'Especialista en jardinería urbana',
            'detailed_description' => "Tono: Profesional\nPersonalidad: Meticuloso\nTemas: Jardinería urbana, balcones, espacios pequeños",
        ]);

        $mockTextGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);

        $jsonStructure = json_encode([
            'title' => 'Jardinería en Balcones',
            'excerpt' => 'Maximiza tu espacio urbano.',
            'category' => 'Decoración',
            'tags' => ['balcones', 'urbano'],
            'blocks' => [
                ['type' => 'paragraph', 'description' => 'Tips para balcones'],
            ],
        ]);

        $mockTextGenerator->method('generate')
            ->willReturnOnConsecutiveCalls(
                $jsonStructure,
                'Los balcones pequeños pueden convertirse en jardines productivos.'
            );

        $mockImageGenerator->method('generate')
            ->willReturn('https://example.com/balcony.jpg');

        $authorDescriptionService = new AuthorDescriptionGeneratorService($mockTextGenerator);
        $service = new PostGeneratorService($mockTextGenerator, $mockImageGenerator, $authorDescriptionService, new PersonaPromptBuilder);

        $post = $service->generatePost($author, 'Jardinería en balcones');

        $this->assertNotNull($post->id);
        $this->assertEquals('Jardinería en Balcones', $post->title);
    }

    public function test_generates_post_uses_default_attributes_when_no_detailed_description(): void
    {
        $author = Author::create([
            'name' => 'Ana López',
            'description' => 'Jardinera aficionada',
        ]);

        $mockTextGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);

        $jsonStructure = json_encode([
            'title' => 'Consejos Básicos de Jardinería',
            'excerpt' => 'Inicia tu aventura en jardinería.',
            'category' => 'Consejos',
            'tags' => ['básico', 'principiantes'],
            'blocks' => [
                ['type' => 'paragraph', 'description' => 'Consejos iniciales'],
            ],
        ]);

        $mockTextGenerator->method('generate')
            ->willReturnOnConsecutiveCalls(
                $jsonStructure,
                'Comenzar en jardinería es fácil con estos consejos.'
            );

        $mockImageGenerator->method('generate')
            ->willReturn('https://example.com/basics.jpg');

        $authorDescriptionService = new AuthorDescriptionGeneratorService($mockTextGenerator);
        $service = new PostGeneratorService($mockTextGenerator, $mockImageGenerator, $authorDescriptionService, new PersonaPromptBuilder);

        $post = $service->generatePost($author);

        $this->assertNotNull($post->id);
        $this->assertEquals($author->id, $post->author_id);
    }

    public function test_generates_post_with_custom_length_option(): void
    {
        $author = Author::create([
            'name' => 'Carlos Ruiz',
            'description' => 'Experto en compostaje',
            'detailed_description' => "Tono: Técnico\nPersonalidad: Detallista\nTemas: Compostaje, sostenibilidad",
        ]);

        $mockTextGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockImageGenerator = $this->createMock(ImageGeneratorInterface::class);

        $jsonStructure = json_encode([
            'title' => 'Guía Completa de Compostaje',
            'excerpt' => 'Todo sobre compostaje casero.',
            'category' => 'Consejos',
            'tags' => ['compostaje', 'sostenibilidad'],
            'blocks' => [
                ['type' => 'heading', 'description' => 'Introducción al compostaje'],
                ['type' => 'paragraph', 'description' => 'Qué es el compostaje'],
                ['type' => 'list', 'description' => 'Materiales compostables'],
            ],
        ]);

        $mockTextGenerator->method('generate')
            ->willReturnOnConsecutiveCalls(
                $jsonStructure,
                'Introducción al compostaje',
                'El compostaje es una técnica esencial.',
                json_encode(['items' => ['Restos de frutas', 'Hojas secas']])
            );

        $mockImageGenerator->method('generate')
            ->willReturn('https://example.com/compost.jpg');

        $authorDescriptionService = new AuthorDescriptionGeneratorService($mockTextGenerator);
        $service = new PostGeneratorService($mockTextGenerator, $mockImageGenerator, $authorDescriptionService, new PersonaPromptBuilder);

        $post = $service->generatePost($author, null, ['length' => 'long']);

        $this->assertNotNull($post->id);
        $this->assertGreaterThan(0, $post->reading_time);
    }
}
