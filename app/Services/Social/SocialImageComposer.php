<?php

namespace App\Services\Social;

/**
 * Composes a portrait social image with deterministic editorial typography.
 */
class SocialImageComposer
{
    private const WIDTH = 1080;

    private const HEIGHT = 1350;

    private const IVORY = [247, 241, 222];

    private const PEACH = [240, 176, 132];

    private const FOREST = [23, 42, 28];

    private const FONT_HEADLINE = 'fonts/PlayfairDisplay-Bold.ttf';

    private const FONT_ACCENT = 'fonts/PlayfairDisplay-BoldItalic.ttf';

    private const FONT_CATEGORY = 'fonts/DejaVuSans-Bold.ttf';

    private const BRAND_LOGO = 'images/brand-logo.png';

    /**
     * @param  string  $backgroundBytes  Raw PNG or JPEG bytes.
     * @return string Raw PNG bytes of the finished card.
     */
    public function overlay(string $backgroundBytes, string $hook, string $category, string $accent = ''): string
    {
        $source = @imagecreatefromstring($backgroundBytes);

        if ($source === false) {
            throw new \RuntimeException('Could not decode background image for social overlay.');
        }

        $image = $this->coverCrop($source);
        imagedestroy($source);

        return $this->render($image, $hook, $category, $accent);
    }

    /**
     * Branded last resort when neither a portrait nor the post cover can be used.
     *
     * @return string Raw PNG bytes of the finished card.
     */
    public function forestFallback(string $hook, string $category, string $accent = ''): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($image, 0, 0, imagecolorallocate($image, ...self::FOREST));

        return $this->render($image, $hook, $category, $accent);
    }

    private function render(\GdImage $image, string $hook, string $category, string $accent): string
    {
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $this->drawTopGradient($image);
        $this->drawBottomScrim($image);
        $this->drawCategory($image, $category);
        $this->drawHook($image, $hook, $accent);
        $this->drawBrandLogo($image);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function coverCrop(\GdImage $source): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);
        $cropX = (int) floor(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) floor(($sourceHeight - $cropHeight) / 2);

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled(
            $image, $source, 0, 0, $cropX, $cropY,
            self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight,
        );

        return $image;
    }

    private function drawTopGradient(\GdImage $image): void
    {
        for ($y = 0; $y < 690; $y += 6) {
            $vertical = 1 - ($y / 690);

            for ($x = 0; $x < 930; $x += 6) {
                $horizontal = 1 - ($x / 930);
                $opacity = (int) round(108 * $horizontal * $vertical);

                if ($opacity < 2) {
                    continue;
                }

                $color = imagecolorallocatealpha(
                    $image, self::FOREST[0], self::FOREST[1], self::FOREST[2], 127 - $opacity,
                );
                imagefilledrectangle($image, $x, $y, min($x + 5, 929), min($y + 5, 689), $color);
            }
        }
    }

    private function drawBottomScrim(\GdImage $image): void
    {
        for ($y = 1030; $y < self::HEIGHT; $y += 6) {
            $vertical = min(1, ($y - 1030) / 145);

            for ($x = 0; $x < 760; $x += 6) {
                $horizontal = $x < 430 ? 1 : max(0, (760 - $x) / 330);
                $opacity = (int) round(78 * $vertical * $horizontal);

                if ($opacity < 2) {
                    continue;
                }

                $color = imagecolorallocatealpha(
                    $image, self::FOREST[0], self::FOREST[1], self::FOREST[2], 127 - $opacity,
                );
                imagefilledrectangle($image, $x, $y, min($x + 5, 759), min($y + 5, self::HEIGHT - 1), $color);
            }
        }
    }

    private function drawCategory(\GdImage $image, string $category): void
    {
        $category = mb_strtoupper($this->normalizeText($category));

        if ($category === '') {
            return;
        }

        $font = $this->resourcePath(self::FONT_CATEGORY);
        $size = 16;
        $tracking = 3;
        $original = $category;

        while ($this->trackedWidth($category, $font, $size, $tracking) > 900) {
            $category = mb_substr($category, 0, -1);
        }

        if ($category !== $original) {
            while ($this->trackedWidth($category.'…', $font, $size, $tracking) > 900) {
                $category = mb_substr($category, 0, -1);
            }

            $category = rtrim($category).'…';
        }

        $color = imagecolorallocate($image, ...self::IVORY);
        $x = 72;

        foreach (mb_str_split($category) as $character) {
            imagettftext($image, $size, 0, $x, 93, $color, $font, $character);
            $x += $this->textWidth($character, $font, $size) + $tracking;
        }
    }

    private function drawHook(\GdImage $image, string $hook, string $accent): void
    {
        $hook = $this->normalizeText($hook);

        if ($hook === '') {
            return;
        }

        $accent = $this->normalizeText($accent);
        $hasAccent = $accent !== '' && mb_strlen($accent) < mb_strlen($hook)
            && (bool) preg_match('/\s'.preg_quote($accent, '/').'$/iu', $hook);
        $lead = $hasAccent ? trim(mb_substr($hook, 0, mb_strlen($hook) - mb_strlen($accent))) : $hook;
        $accent = $hasAccent ? mb_substr($hook, -mb_strlen($accent)) : '';

        $headlineFont = $this->resourcePath(self::FONT_HEADLINE);
        $accentFont = $this->resourcePath(self::FONT_ACCENT);
        $maxWidth = 650;
        $leadLines = [];
        $accentLines = [];

        for ($size = 76; $size >= 36; $size -= 2) {
            $leadLines = $this->wrapText($lead, $headlineFont, $size, $maxWidth);
            $accentLines = $accent !== '' ? $this->wrapText($accent, $accentFont, $size, $maxWidth) : [];

            if (count($leadLines) + count($accentLines) <= 3) {
                break;
            }
        }

        if (count($leadLines) + count($accentLines) > 3) {
            $leadLimit = $accentLines === [] ? 3 : max(1, 3 - min(2, count($accentLines)));
            $leadLines = $this->limitLines($leadLines, $leadLimit, $headlineFont, $size, $maxWidth);
            $accentLines = $this->limitLines($accentLines, 3 - count($leadLines), $accentFont, $size, $maxWidth);
        }

        $lineHeight = (int) round($size * 1.15);
        $baseline = 215;
        $ivory = imagecolorallocate($image, ...self::IVORY);
        $peach = imagecolorallocate($image, ...self::PEACH);

        foreach ($leadLines as $line) {
            imagettftext($image, $size, 0, 72, $baseline, $ivory, $headlineFont, $line);
            $baseline += $lineHeight;
        }

        foreach ($accentLines as $line) {
            imagettftext($image, $size, 0, 72, $baseline, $peach, $accentFont, $line);
            $baseline += $lineHeight;
        }
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        if ($text === '') {
            return [];
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            while ($this->textWidth($word, $font, $size) > $maxWidth) {
                $part = '';

                foreach (mb_str_split($word) as $character) {
                    if ($part !== '' && $this->textWidth($part.$character, $font, $size) > $maxWidth) {
                        break;
                    }

                    $part .= $character;
                }

                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                $lines[] = $part;
                $word = mb_substr($word, mb_strlen($part));
            }

            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->textWidth($candidate, $font, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function limitLines(array $lines, int $limit, string $font, int $size, int $maxWidth): array
    {
        if ($limit <= 0) {
            return [];
        }

        if (count($lines) <= $limit) {
            return $lines;
        }

        $visible = array_slice($lines, 0, $limit);
        $visible[$limit - 1] = $this->truncateToWidth($visible[$limit - 1].'…', $font, $size, $maxWidth);

        return $visible;
    }

    private function truncateToWidth(string $text, string $font, int $size, int $maxWidth): string
    {
        if ($this->textWidth($text, $font, $size) <= $maxWidth) {
            return $text;
        }

        if (str_ends_with($text, '…')) {
            $text = mb_substr($text, 0, -1);
        }

        while ($text !== '' && $this->textWidth($text.'…', $font, $size) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box[2] - $box[0];
    }

    private function trackedWidth(string $text, string $font, int $size, int $tracking): int
    {
        $width = 0;

        foreach (mb_str_split($text) as $character) {
            $width += $this->textWidth($character, $font, $size) + $tracking;
        }

        return $width;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function drawBrandLogo(\GdImage $image): void
    {
        $logo = @imagecreatefrompng($this->resourcePath(self::BRAND_LOGO));

        if ($logo === false) {
            throw new \RuntimeException('Could not load the original brand logo.');
        }

        $cropX = 218;
        $cropY = 394;
        $cropWidth = 610;
        $cropHeight = 212;
        $mark = imagecreatetruecolor($cropWidth, $cropHeight);
        imagealphablending($mark, false);
        imagesavealpha($mark, true);
        imagefill($mark, 0, 0, imagecolorallocatealpha($mark, 0, 0, 0, 127));

        for ($y = 0; $y < $cropHeight; $y++) {
            for ($x = 0; $x < $cropWidth; $x++) {
                $pixel = imagecolorsforindex($logo, imagecolorat($logo, $cropX + $x, $cropY + $y));

                if ($pixel['red'] < 75 || $pixel['red'] < $pixel['green'] || $pixel['green'] <= $pixel['blue']) {
                    continue;
                }

                $opacity = min(127, (int) round(($pixel['red'] - 65) * 127 / 170));
                $color = imagecolorallocatealpha(
                    $mark, $pixel['red'], $pixel['green'], $pixel['blue'], 127 - $opacity,
                );
                imagesetpixel($mark, $x, $y, $color);
            }
        }

        $targetWidth = 305;
        $targetHeight = (int) round($targetWidth * $cropHeight / $cropWidth);
        imagecopyresampled(
            $image, $mark, 64, self::HEIGHT - $targetHeight - 65, 0, 0,
            $targetWidth, $targetHeight, $cropWidth, $cropHeight,
        );

        imagedestroy($mark);
        imagedestroy($logo);
    }

    private function resourcePath(string $relative): string
    {
        return dirname(__DIR__, 3).'/resources/'.$relative;
    }
}
