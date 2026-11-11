<?php

namespace MattBloomfield\RecipeHelper\sharecards;

use GdImage;
use RuntimeException;

/**
 * Draws a 1200×630 social share card (Open Graph / X large image) with GD.
 *
 * Layout: text panel on the left (kicker, title, facts, site URL); on the
 * right either the recipe photo (cover-cropped) or a dark brand panel with
 * the wordmark. Colors are the "hearth" palette from src/css/_components/_theme.css.
 *
 * Pure GD + bundled TTFs, so it renders the same locally and on the server.
 */
final class ShareCardRenderer
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const PANEL_X = 640;      // where the right-hand panel starts
    private const PAD = 64;
    private const TEXT_WIDTH = self::PANEL_X - 2 * self::PAD;

    private const COLORS = [
        'surface' => 0xFAF6F0,
        'ink' => 0x2E241C,
        'primary' => 0x5C4232,
        'accent' => 0xBC6248,
        'muted' => 0x6E5E50,
        'shell' => 0x2B211A,
        'shellInk' => 0xE0D4C2,
        'border' => 0xE3D8C8,
    ];

    private readonly string $serif;
    private readonly string $sans;
    private readonly string $sansBold;

    public function __construct(?string $fontDir = null)
    {
        $fontDir ??= dirname(__DIR__) . '/resources/fonts';
        $this->serif = "$fontDir/Fraunces-SemiBold.ttf";
        $this->sans = "$fontDir/Karla-Regular.ttf";
        $this->sansBold = "$fontDir/Karla-Bold.ttf";
    }

    /**
     * @param array{kicker: string, title: string, facts: list<string>, footer: string, wordmark: string} $card
     * @param string|null $photo Raw image bytes (JPEG/PNG/GIF/WebP; HEIC if Imagick is available).
     * @return string JPEG bytes
     */
    public function render(array $card, ?string $photo = null): string
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT) ?: throw new RuntimeException('GD could not allocate the canvas.');
        imagealphablending($im, true);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $this->color($im, 'surface'));

        $photoImage = $photo !== null ? $this->decode($photo) : null;
        if ($photoImage) {
            $this->drawCover($im, $photoImage, self::PANEL_X, 0, self::WIDTH - self::PANEL_X, self::HEIGHT);
        } else {
            $this->drawBrandPanel($im, $card['wordmark']);
        }
        // Accent seam between the panels.
        imagefilledrectangle($im, self::PANEL_X - 8, 0, self::PANEL_X - 1, self::HEIGHT, $this->color($im, 'accent'));

        $this->drawText($im, $card);

        ob_start();
        imagejpeg($im, null, 88);

        return (string)ob_get_clean();
    }

    private function drawText(GdImage $im, array $card): void
    {
        $x = self::PAD;
        $baseY = self::HEIGHT - self::PAD;

        // Measure first so the kicker/title/facts block can be centered
        // vertically in the space above the footer.
        [$size, $lines] = $this->fitTitle($card['title']);
        $lineHeight = (int)round($size * 1.12);
        $facts = $this->fitFacts($card['facts']);
        $kickerH = $card['kicker'] !== '' ? 56 : 0;
        $factsH = $facts !== '' ? 64 : 0;
        $blockH = $kickerH + count($lines) * $lineHeight + $factsH;
        $areaTop = self::PAD;
        $areaBottom = $baseY - 80;
        $y = $areaTop + max(0, (int)(($areaBottom - $areaTop - $blockH) / 2));

        // Kicker: small, letter-spaced, uppercase.
        if ($kickerH) {
            $this->drawTracked($im, mb_strtoupper($card['kicker']), $this->sansBold, 20, $x, $y + 20, $this->color($im, 'accent'), 3);
            $y += $kickerH;
        }

        // Title: Fraunces, largest size that fits.
        foreach ($lines as $line) {
            $y += $lineHeight;
            imagettftext($im, $size, 0, $x, $y, $this->color($im, 'ink'), $this->serif, $line);
        }

        // Facts (e.g. time · servings), whole items only.
        if ($factsH) {
            $y += $factsH;
            imagettftext($im, 26, 0, $x, $y, $this->color($im, 'muted'), $this->sans, $facts);
        }

        // Footer: accent rule + site URL, pinned to the bottom.
        imagefilledrectangle($im, $x, $baseY - 48, $x + 56, $baseY - 44, $this->color($im, 'accent'));
        imagettftext($im, 24, 0, $x, $baseY, $this->color($im, 'primary'), $this->sansBold, $card['footer']);
    }

    private function drawBrandPanel(GdImage $im, string $wordmark): void
    {
        $x0 = self::PANEL_X;
        $w = self::WIDTH - $x0;
        imagefilledrectangle($im, $x0, 0, self::WIDTH, self::HEIGHT, $this->color($im, 'shell'));

        // Wordmark, one word per line, centered in the panel.
        $words = preg_split('/\s+/', trim($wordmark)) ?: [];
        $size = 64;
        $lineHeight = 78;
        $top = (int)((self::HEIGHT - $lineHeight * count($words)) / 2) + $size;
        foreach ($words as $i => $word) {
            $box = imagettfbbox($size, 0, $this->serif, $word);
            $textW = $box[2] - $box[0];
            $color = $this->color($im, $i === count($words) - 1 ? 'accent' : 'shellInk');
            imagettftext($im, $size, 0, $x0 + (int)(($w - $textW) / 2), $top + $i * $lineHeight, $color, $this->serif, $word);
        }
    }

    /** @return array{0: int, 1: list<string>} */
    private function fitTitle(string $title): array
    {
        foreach ([68, 60, 54, 48, 44, 40] as $size) {
            $lines = $this->wrap($title, $this->serif, $size, self::TEXT_WIDTH);
            $maxLines = $size >= 60 ? 3 : 4;
            if (count($lines) <= $maxLines) {
                return [$size, $lines];
            }
        }
        $lines = array_slice($this->wrap($title, $this->serif, 40, self::TEXT_WIDTH), 0, 4);
        $lines[3] = $this->ellipsize($lines[3] . '…', $this->serif, 40, self::TEXT_WIDTH);

        return [40, $lines];
    }

    /** Joins facts with " · ", dropping trailing facts (not cutting words) until it fits. */
    private function fitFacts(array $facts): string
    {
        $facts = array_values(array_filter($facts, fn($f) => trim((string)$f) !== ''));
        while ($facts) {
            $text = implode('  ·  ', $facts);
            if ($this->width($text, $this->sans, 26) <= self::TEXT_WIDTH) {
                return $text;
            }
            array_pop($facts);
        }

        return '';
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : "$line $word";
            if ($line !== '' && $this->width($candidate, $font, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function ellipsize(string $text, string $font, int $size, int $maxWidth): string
    {
        if ($this->width($text, $font, $size) <= $maxWidth) {
            return $text;
        }
        $text = rtrim($text, '…');
        while (mb_strlen($text) > 1 && $this->width($text . '…', $font, $size) > $maxWidth) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text . '…';
    }

    private function width(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box[2] - $box[0];
    }

    private function drawTracked(GdImage $im, string $text, string $font, int $size, int $x, int $y, int $color, int $tracking): void
    {
        foreach (mb_str_split($text) as $char) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $char);
            $x += $this->width($char, $font, $size) + $tracking;
        }
    }

    private function drawCover(GdImage $dst, GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cropW = (int)round($w / $scale);
        $cropH = (int)round($h / $scale);
        imagecopyresampled($dst, $src, $x, $y, (int)(($sw - $cropW) / 2), (int)(($sh - $cropH) / 2), $w, $h, $cropW, $cropH);
    }

    private function decode(string $bytes): ?GdImage
    {
        $image = @imagecreatefromstring($bytes);
        if ($image instanceof GdImage) {
            return $image;
        }
        // HEIC and other formats GD can't read: convert with Imagick when the server has it.
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->readImageBlob($bytes);
                $imagick->autoOrient();
                $imagick->setImageFormat('jpeg');
                $image = @imagecreatefromstring($imagick->getImageBlob());

                return $image instanceof GdImage ? $image : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function color(GdImage $im, string $name): int
    {
        $rgb = self::COLORS[$name];

        return imagecolorallocate($im, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
