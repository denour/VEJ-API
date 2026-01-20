<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SlugValidationTest extends TestCase
{
    /**
     * Test slug regex validation
     */
    public function test_slug_regex_validation(): void
    {
        $pattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

        // Valid slugs
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
            $this->assertEquals(
                1,
                preg_match($pattern, $slug),
                "Slug '{$slug}' should be valid but was rejected"
            );
        }

        // Invalid slugs
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
            $this->assertEquals(
                0,
                preg_match($pattern, $slug),
                "Slug '{$slug}' should be invalid but was accepted"
            );
        }
    }
}
