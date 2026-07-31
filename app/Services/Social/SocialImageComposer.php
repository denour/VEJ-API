<?php

namespace App\Services\Social;

/**
 * Draws the social-media text (hook, category badge, brand) onto a background
 * image using GD + FreeType, so the copy is ALWAYS present and legible.
 *
 * We do this deterministically instead of asking the image model to render the
 * text: gpt-image-1 frequently produced garbled or missing text, leaving cards
 * that looked empty. See SocialMediaPublisher::generateSocialImage().
 *
 * Look is modeled on the very first hand-generated card (2026-07-25, "Jardines
 * sensoriales..."): a rounded translucent card holding a serif headline (lead
 * words bold white, rest bold italic in the brand accent color), a rounded
 * pill category badge, and the real brand lockup as a watermark.
 */
class SocialImageComposer
{
    private const FONT_HEADLINE_BOLD = 'fonts/PlayfairDisplay-Bold.ttf';

    private const FONT_HEADLINE_ITALIC = 'fonts/PlayfairDisplay-BoldItalic.ttf';

    private const FONT_LABEL = 'fonts/DejaVuSans-Bold.ttf';

    private const BRAND_LOGO = 'images/brand-logo.png';

    private const ACCENT_COLOR = [240, 190, 155];

    private const CARD_COLOR = [22, 34, 20];

    private const BRAND_GREEN = [34, 108, 62];

    /**
     * Instagram feed favors 4:5 portrait over square — it claims more of the
     * screen and reads better in-feed. Facebook renders 4:5 fine too, so we
     * use one target size for both platforms.
     */
    private const CANVAS_WIDTH = 1080;

    private const CANVAS_HEIGHT = 1350;

    /**
     * Overlay the hook, category badge and brand watermark on the background.
     * The background is cropped-to-fill the target canvas (like CSS
     * object-fit: cover), so any source aspect ratio works.
     *
     * @param  string  $backgroundPng  Raw PNG/JPEG bytes of the background image.
     * @return string Raw PNG bytes of the composited card.
     */
    public function overlay(string $backgroundPng, string $hook, string $category): string
    {
        // Silence GD's warning on bad data; we handle the false return ourselves.
        $source = @imagecreatefromstring($backgroundPng);

        if ($source === false) {
            throw new \RuntimeException('Could not decode background image for social overlay.');
        }

        $width = self::CANVAS_WIDTH;
        $height = self::CANVAS_HEIGHT;

        $image = $this->coverCrop($source, $width, $height);
        imagedestroy($source);

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $badgeBottom = $this->drawCategoryBadge($image, $category, $width, $height);
        $this->drawHook($image, $hook, $width, $height, $badgeBottom);
        $this->drawBrandWatermark($image, $width, $height);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        imagedestroy($image);

        return $png;
    }

    /**
     * Resize+crop $source to exactly fill a $targetWidth x $targetHeight
     * canvas without distortion (equivalent to CSS object-fit: cover).
     *
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function coverCrop($source, int $targetWidth, int $targetHeight)
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $scaledWidth = (int) ceil($sourceWidth * $scale);
        $scaledHeight = (int) ceil($sourceHeight * $scale);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $srcX = (int) (($scaledWidth - $targetWidth) / 2 / $scale);
        $srcY = (int) (($scaledHeight - $targetHeight) / 2 / $scale);
        $srcW = (int) ($targetWidth / $scale);
        $srcH = (int) ($targetHeight / $scale);

        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $srcW, $srcH);

        return $canvas;
    }

    /**
     * The hook inside a rounded translucent card: the first couple of words in
     * bold serif white (the "lead"), the rest in bold italic serif accent color.
     * Both wrap independently and stack top-to-bottom, left-aligned.
     *
     * @param  \GdImage  $image
     */
    private function drawHook($image, string $hook, int $width, int $height, int $top): void
    {
        $hook = trim(preg_replace('/\s+/', ' ', $hook) ?? '');

        if ($hook === '') {
            return;
        }

        $margin = (int) ($width * 0.06);
        $padding = (int) ($width * 0.05);
        $maxTextWidth = min((int) ($width * 0.56), $width - (2 * $margin) - (2 * $padding));

        $boldFont = $this->resourcePath(self::FONT_HEADLINE_BOLD);
        $italicFont = $this->resourcePath(self::FONT_HEADLINE_ITALIC);

        [$lead, $accent] = $this->splitHook($hook);

        $fontSize = (int) ($width * 0.072);

        do {
            $leadLines = $lead !== '' ? $this->wrapText($lead, $boldFont, $fontSize, $maxTextWidth) : [];
            $accentLines = $accent !== '' ? $this->wrapText($accent, $italicFont, $fontSize, $maxTextWidth) : [];
            $totalLines = count($leadLines) + count($accentLines);
            $fontSize -= 4;
        } while ($totalLines > 5 && $fontSize > 22);

        $fontSize += 4;
        $lineHeight = (int) ($fontSize * 1.25);

        $allLines = array_merge(
            array_map(fn (string $l) => ['text' => $l, 'font' => $boldFont, 'color' => [255, 255, 255]], $leadLines),
            array_map(fn (string $l) => ['text' => $l, 'font' => $italicFont, 'color' => self::ACCENT_COLOR], $accentLines),
        );

        if ($allLines === []) {
            return;
        }

        $blockWidth = 0;
        foreach ($allLines as $line) {
            $box = imagettfbbox($fontSize, 0, $line['font'], $line['text']);
            $blockWidth = max($blockWidth, $box[2] - $box[0]);
        }

        $cardX0 = $margin;
        $cardY0 = max($top, (int) ($height * 0.12));
        $cardX1 = min($width - $margin, $cardX0 + $blockWidth + (2 * $padding));
        $cardY1 = $cardY0 + (2 * $padding) + ($lineHeight * count($allLines));

        $cardColor = imagecolorallocatealpha(
            $image,
            self::CARD_COLOR[0],
            self::CARD_COLOR[1],
            self::CARD_COLOR[2],
            40,
        );
        $this->roundedRect($image, $cardX0, $cardY0, $cardX1, $cardY1, (int) ($width * 0.035), $cardColor);

        $textX = $cardX0 + $padding;
        $textY = $cardY0 + $padding + (int) ($fontSize * 0.9);

        foreach ($allLines as $i => $line) {
            $color = imagecolorallocate($image, ...$line['color']);
            imagettftext($image, $fontSize, 0, $textX, $textY + ($i * $lineHeight), $color, $line['font'], $line['text']);
        }
    }

    /**
     * Split the hook into a short bold "lead" and the remaining italic accent
     * phrase, mirroring the reference card (e.g. "Jardines sensoriales" / "que
     * cuidan tu movilidad en casa").
     *
     * @return array{0: string, 1: string}
     */
    private function splitHook(string $hook): array
    {
        $words = preg_split('/\s+/', $hook) ?: [];
        $leadWordCount = count($words) > 3 ? 2 : 1;

        if (count($words) <= $leadWordCount) {
            return [$hook, ''];
        }

        $lead = implode(' ', array_slice($words, 0, $leadWordCount));
        $accent = implode(' ', array_slice($words, $leadWordCount));

        return [$lead, $accent];
    }

    /**
     * Category badge: a rounded pill in the top-left corner with a small leaf
     * icon and the label. Returns the badge's bottom y so the hook card can
     * start below it without overlapping.
     *
     * @param  \GdImage  $image
     */
    private function drawCategoryBadge($image, string $category, int $width, int $height): int
    {
        $category = trim($category);
        $margin = (int) ($width * 0.06);

        if ($category === '') {
            return $margin;
        }

        $label = mb_strtoupper($category);
        $font = $this->resourcePath(self::FONT_LABEL);
        $fontSize = (int) ($width * 0.024);
        $iconDiameter = (int) ($fontSize * 2.4);
        $padY = (int) ($fontSize * 0.75);
        $padX = (int) ($fontSize * 0.9);
        $gap = (int) ($fontSize * 0.6);

        $box = imagettfbbox($fontSize, 0, $font, $label);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];

        $pillHeight = max($iconDiameter, $textHeight) + (2 * $padY);
        $pillWidth = $padX + $iconDiameter + $gap + $textWidth + $padX;

        $x0 = $margin;
        $y0 = $margin;
        $x1 = $x0 + $pillWidth;
        $y1 = $y0 + $pillHeight;

        $pill = imagecolorallocatealpha($image, self::BRAND_GREEN[0], self::BRAND_GREEN[1], self::BRAND_GREEN[2], 15);
        $this->roundedRect($image, $x0, $y0, $x1, $y1, (int) ($pillHeight / 2), $pill);

        $iconCx = $x0 + $padX + (int) ($iconDiameter / 2);
        $iconCy = $y0 + (int) ($pillHeight / 2);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledellipse($image, $iconCx, $iconCy, $iconDiameter, $iconDiameter, $white);
        $this->drawLeafGlyph($image, $iconCx, $iconCy, (int) ($iconDiameter * 0.55));

        $textX = $x0 + $padX + $iconDiameter + $gap;
        $textY = $y0 + (int) (($pillHeight + $textHeight) / 2);
        imagettftext($image, $fontSize, 0, $textX, $textY, $white, $font, $label);

        return $y1;
    }

    /**
     * A minimal two-leaflet glyph, brand green, centered at ($cx, $cy): two
     * tapered ovals angled outward from a shared base, like the brand icon.
     *
     * @param  \GdImage  $image
     */
    private function drawLeafGlyph($image, int $cx, int $cy, int $size): void
    {
        $green = imagecolorallocate($image, self::BRAND_GREEN[0], self::BRAND_GREEN[1], self::BRAND_GREEN[2]);
        $baseY = $cy + (int) ($size * 0.4);

        $this->filledLeaf($image, $cx, $baseY, $size, -35, $green);
        $this->filledLeaf($image, $cx, $baseY, $size, 35, $green);
    }

    /**
     * A single tapered leaf: a teardrop polygon anchored at ($baseX, $baseY),
     * pointing away at $angleDegrees from straight up.
     *
     * @param  \GdImage  $image
     */
    private function filledLeaf($image, int $baseX, int $baseY, int $length, float $angleDegrees, int $color): void
    {
        $angle = deg2rad($angleDegrees - 90);
        $tipX = $baseX + (int) (cos($angle) * $length);
        $tipY = $baseY + (int) (sin($angle) * $length);
        $perp = $angle + M_PI_2;
        $bulge = $length * 0.32;

        $midX = ($baseX + $tipX) / 2;
        $midY = ($baseY + $tipY) / 2;

        $points = [
            $baseX, $baseY,
            (int) ($midX + cos($perp) * $bulge), (int) ($midY + sin($perp) * $bulge),
            $tipX, $tipY,
            (int) ($midX - cos($perp) * $bulge), (int) ($midY - sin($perp) * $bulge),
        ];

        imagefilledpolygon($image, $points, $color);
    }

    /**
     * Brand watermark: the real lockup (icon + wordmark) from
     * resources/images/brand-logo.png, bottom-right, alpha-composited.
     *
     * @param  \GdImage  $image
     */
    private function drawBrandWatermark($image, int $width, int $height): void
    {
        $logoPath = $this->resourcePath(self::BRAND_LOGO);

        if (! is_file($logoPath)) {
            return;
        }

        $logo = @imagecreatefrompng($logoPath);

        if ($logo === false) {
            return;
        }

        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);

        $targetWidth = (int) ($width * 0.34);
        $targetHeight = (int) ($targetWidth * ($logoHeight / $logoWidth));

        $margin = (int) ($width * 0.05);
        $x = $width - $targetWidth - $margin;
        $y = $height - $targetHeight - $margin;

        imagecopyresampled($image, $logo, $x, $y, 0, 0, $targetWidth, $targetHeight, $logoWidth, $logoHeight);
        imagedestroy($logo);
    }

    /**
     * Fill a rounded rectangle: a body rectangle plus four corner circles, all
     * in the given (possibly translucent) color. GD has no native primitive.
     *
     * @param  \GdImage  $image
     */
    private function roundedRect($image, int $x0, int $y0, int $x1, int $y1, int $radius, int $color): void
    {
        $radius = min($radius, (int) (($x1 - $x0) / 2), (int) (($y1 - $y0) / 2));

        imagefilledrectangle($image, $x0 + $radius, $y0, $x1 - $radius, $y1, $color);
        imagefilledrectangle($image, $x0, $y0 + $radius, $x1, $y1 - $radius, $color);

        imagefilledellipse($image, $x0 + $radius, $y0 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 - $radius, $y0 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x0 + $radius, $y1 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 - $radius, $y1 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * Greedily wrap text to fit within a pixel width for the given font/size.
     *
     * @return list<string>
     */
    private function wrapText(string $text, string $font, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $candidateWidth = $box[2] - $box[0];

            if ($candidateWidth > $maxWidth && $current !== '') {
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

    private function resourcePath(string $relative): string
    {
        // Resolve relative to the app root without the Laravel container, so the
        // composer stays usable from plain unit tests. app/Services/Social → base.
        return dirname(__DIR__, 3).'/resources/'.$relative;
    }
}
