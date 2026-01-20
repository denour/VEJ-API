<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthorSlugValidationTest extends TestCase
{
    public function test_valid_slugs_pass_validation(): void
    {
        $validSlugs = [
            'luz-solis',
            'lucia-valverde',
            'maria-garcia',
            'juan123',
            'autor-de-jardineria-2024',
            'simple',
            'with-multiple-dashes',
            'abc123xyz',
        ];

        foreach ($validSlugs as $slug) {
            $validator = Validator::make(
                ['slug' => $slug],
                ['slug' => 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']
            );

            $this->assertFalse(
                $validator->fails(),
                "Slug '{$slug}' should be valid but validation failed: ".json_encode($validator->errors()->all())
            );
        }
    }

    public function test_invalid_slugs_fail_validation(): void
    {
        $invalidSlugs = [
            'luz_solis',           // underscores
            'Lucia-Valverde',      // uppercase
            'lucia-',              // ends with dash
            '-lucia',              // starts with dash
            'lucia--valverde',     // double dash
            'lucia valverde',      // spaces
            'lucía-solis',         // accents
        ];

        foreach ($invalidSlugs as $slug) {
            $validator = Validator::make(
                ['slug' => $slug],
                ['slug' => 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']
            );

            $this->assertTrue(
                $validator->fails(),
                "Slug '{$slug}' should be invalid but validation passed"
            );
        }
    }

    public function test_slug_generation_from_name(): void
    {
        $testCases = [
            'Luz Solis' => 'luz-solis',
            'Lucía Valverde' => 'lucia-valverde',
            'María García' => 'maria-garcia',
            'Juan Pablo Pérez' => 'juan-pablo-perez',
        ];

        foreach ($testCases as $name => $expectedSlug) {
            $generated = str($name)->slug();
            $this->assertEquals(
                $expectedSlug,
                $generated,
                "Name '{$name}' should generate slug '{$expectedSlug}' but got '{$generated}'"
            );
        }
    }
}
