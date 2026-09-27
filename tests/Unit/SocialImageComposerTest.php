<?php

namespace Tests\Unit;

use App\Services\Social\SocialImageComposer;
use PHPUnit\Framework\TestCase;

class SocialImageComposerTest extends TestCase
{
    public function test_editorial_cover_highlights_only_the_requested_phrase_and_preserves_photo_space(): void
    {
        $composer = new SocialImageComposer;
        $background = $this->backgroundPng();
        $hook = 'Tu balcón también puede florecer';
        $plain = imagecreatefromstring($composer->overlay($background, $hook, 'Jardinería en casa'));
        $accented = imagecreatefromstring($composer->overlay($background, $hook, 'Jardinería en casa', 'florecer'));
        $invalid = imagecreatefromstring($composer->overlay($background, $hook, 'Jardinería en casa', 'texto inventado'));

        $countAccent = static function (\GdImage $image): int {
            $count = 0;
            for ($y = 130; $y < 600; $y++) {
                for ($x = 40; $x < 1000; $x++) {
                    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if ($color['red'] > 180 && $color['red'] - $color['green'] > 25 && $color['green'] - $color['blue'] > 15) {
                        $count++;
                    }
                }
            }

            return $count;
        };

        $this->assertSame(1080, imagesx($accented));
        $this->assertSame(1350, imagesy($accented));
        $this->assertSame(0, $countAccent($plain), 'Without editorial emphasis, words must not be arbitrarily italicized and colored.');
        $this->assertGreaterThan(500, $countAccent($accented), 'The requested phrase must be visible in the accent color.');
        $this->assertSame(0, $countAccent($invalid), 'Unknown emphasis must not insert or highlight unrelated copy.');

        $photo = imagecolorsforindex($accented, imagecolorat($accented, 950, 800));
        $this->assertGreaterThan(60, $photo['green'], 'Keep the main photo area visible instead of darkening the entire canvas.');

        imagedestroy($plain);
        imagedestroy($accented);
        imagedestroy($invalid);
    }

    private function backgroundPng(int $size = 512): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 80, 50));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    public function test_overlay_returns_valid_png_cropped_to_the_4_5_canvas(): void
    {
        // Square source on purpose: the composer must cover-crop it to 4:5,
        // not just pass the source dimensions through.
        $bg = $this->backgroundPng(512);

        $card = (new SocialImageComposer)->overlay($bg, 'Tu balcón puede florecer', 'Consejos');

        $this->assertNotSame('', $card);

        $result = imagecreatefromstring($card);
        $this->assertNotFalse($result);
        $this->assertSame(1080, imagesx($result));
        $this->assertSame(1350, imagesy($result));

        // Compositing must actually change the pixels (card + text drawn).
        $this->assertNotSame($bg, $card);
    }

    public function test_overlay_wraps_long_hooks_without_error(): void
    {
        $bg = $this->backgroundPng(512);
        $longHook = 'Un gancho largo que obliga a envolver el texto en varias líneas para caber bien';

        $card = (new SocialImageComposer)->overlay($bg, $longHook, 'Jardinería');

        $this->assertNotFalse(imagecreatefromstring($card));
    }

    public function test_overlay_handles_empty_hook_and_category(): void
    {
        $bg = $this->backgroundPng(400);

        $card = (new SocialImageComposer)->overlay($bg, '', '');

        $result = imagecreatefromstring($card);
        $this->assertNotFalse($result);
        $this->assertSame(1080, imagesx($result));
    }

    public function test_overlay_throws_on_undecodable_background(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SocialImageComposer)->overlay('this is not an image', 'Hola', 'Consejos');
    }
}
