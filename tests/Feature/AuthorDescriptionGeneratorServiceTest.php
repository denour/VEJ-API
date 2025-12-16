<?php

namespace Tests\Feature;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Services\AI\AuthorDescriptionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorDescriptionGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_detailed_description_for_author(): void
    {
        $author = Author::create([
            'name' => 'María González',
            'description' => 'Experta en jardinería tropical y plantas exóticas',
        ]);

        $mockGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockGenerator->expects($this->once())
            ->method('generate')
            ->willReturn("Tono: Conversacional y cercano\nPersonalidad: Entusiasta y educativo\nTemas: Plantas tropicales, cuidados básicos, jardinería urbana");

        $service = new AuthorDescriptionGeneratorService($mockGenerator);
        $result = $service->generateDetailedDescription($author);

        $this->assertStringContainsString('Tono:', $result);
        $this->assertStringContainsString('Personalidad:', $result);
        $this->assertStringContainsString('Temas:', $result);
    }

    public function test_extracts_attributes_from_detailed_description(): void
    {
        $detailedDescription = "Tono: Conversacional y cercano\nPersonalidad: Entusiasta y educativo\nTemas: Plantas tropicales, cuidados básicos, jardinería urbana";

        $mockGenerator = $this->createMock(TextGeneratorInterface::class);
        $service = new AuthorDescriptionGeneratorService($mockGenerator);

        $attributes = $service->extractAttributes($detailedDescription);

        $this->assertEquals('Conversacional y cercano', $attributes['tone']);
        $this->assertEquals('Entusiasta y educativo', $attributes['personality']);
        $this->assertCount(3, $attributes['themes']);
        $this->assertContains('Plantas tropicales', $attributes['themes']);
        $this->assertContains('cuidados básicos', $attributes['themes']);
        $this->assertContains('jardinería urbana', $attributes['themes']);
    }

    public function test_extracts_attributes_handles_extra_whitespace(): void
    {
        $detailedDescription = "  Tono:   Profesional  \n  Personalidad:   Experto   \n  Temas:   Botánica  ,  Cultivo  ,  Sostenibilidad  ";

        $mockGenerator = $this->createMock(TextGeneratorInterface::class);
        $service = new AuthorDescriptionGeneratorService($mockGenerator);

        $attributes = $service->extractAttributes($detailedDescription);

        $this->assertEquals('Profesional', $attributes['tone']);
        $this->assertEquals('Experto', $attributes['personality']);
        $this->assertCount(3, $attributes['themes']);
        $this->assertContains('Botánica', $attributes['themes']);
    }

    public function test_integration_generate_and_extract(): void
    {
        $author = Author::create([
            'name' => 'Carlos Ruiz',
            'description' => 'Especialista en jardinería orgánica',
        ]);

        $mockGenerator = $this->createMock(TextGeneratorInterface::class);
        $mockGenerator->method('generate')
            ->willReturn("Tono: Técnico y profesional\nPersonalidad: Detallista y meticuloso\nTemas: Jardinería orgánica, compostaje, control de plagas natural");

        $service = new AuthorDescriptionGeneratorService($mockGenerator);

        $description = $service->generateDetailedDescription($author);
        $attributes = $service->extractAttributes($description);

        $this->assertNotEmpty($attributes['tone']);
        $this->assertNotEmpty($attributes['personality']);
        $this->assertNotEmpty($attributes['themes']);
        $this->assertEquals('Técnico y profesional', $attributes['tone']);
    }
}
