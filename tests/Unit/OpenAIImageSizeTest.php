<?php

namespace Tests\Unit;

use App\Services\AI\OpenAIImageGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OpenAIImageSizeTest extends TestCase
{
    public function test_portrait_dimensions_respect_the_selected_model(): void
    {
        $size = new ReflectionMethod(OpenAIImageGenerator::class, 'getSize');
        $generator = new OpenAIImageGenerator(model: 'gpt-image-2');

        $this->assertSame('1024x1280', $size->invoke($generator, ['aspectRatio' => '4:5']));
        $this->assertSame('1024x1280', $size->invoke($generator, ['aspectRatio' => '4:5', 'model' => 'gpt-image-2-2026-04-21']));
        $this->assertSame('1024x1536', $size->invoke($generator, ['aspectRatio' => '4:5', 'model' => 'gpt-image-1']));
        $this->assertSame('1536x1024', $size->invoke($generator, ['aspectRatio' => '16:9']));
        $this->assertSame('1024x1024', $size->invoke($generator, []));
    }
}
