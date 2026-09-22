<?php

require_once __DIR__ . '/CavemenTicketTier.php';

/**
 * Branded "I'm going" card a registrant can post to Instagram, Snapchat or X.
 * Rendered with GD so it works on shared hosting; fonts come from the dompdf
 * package that is already installed for the PDF ticket.
 */
class CavemenShareCard
{
    /** @var array<string,array{int,int}> */
    const SIZES = [
        'square' => [1080, 1080],
        'story' => [1080, 1920],
    ];

    /** @var array<string,resource|\GdImage>|array<string,null> */
    private static $logoCache = [];

    /**
     * @param array<string,mixed> $ctx name, attendanceType, isDahk, eventName, seriesLabel, when, venue
     * @return string|null raw PNG bytes
     */
    public static function pngBytes(array $ctx, $size = 'square')
    {
        if (!extension_loaded('gd') || !function_exists('imagettftext')) {
            error_log('[cavemen] share card: GD with FreeType is required');

            return null;
        }
        if (!isset(self::SIZES[$size])) {
            $size = 'square';
        }
        [$w, $h] = self::SIZES[$size];
        $scale = $size === 'story' ? 1.15 : 1.0;

        $serif = self::font('DejaVuSerif-Bold.ttf');
        $sans = self::font('DejaVuSans.ttf');
        $sansBold = self::font('DejaVuSans-Bold.ttf');
        if ($serif === null || $sans === null || $sansBold === null) {
            error_log('[cavemen] share card: dompdf fonts not found — run composer install');

            return null;
        }

        $tier = CavemenTicketTier::forType($ctx['attendanceType'] ?? '', !empty($ctx['isDahk']));
        $paper = '#f6f1e8';
        $ink = $tier['ink'];
        $accent = $tier['accent'];
        $muted = '#6b6358';

        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, self::color($im, $paper));
        self::drawPattern($im, $w, $h);

        $inset = (int) round(34 * $scale);
        $border = (int) round(10 * $scale);
        self::frame($im, $inset, $inset, $w - $inset, $h - $inset, $border, self::color($im, $accent));
        $hair = $inset + $border + (int) round(12 * $scale);
        self::frame($im, $hair, $hair, $w - $hair, $h - $hair, 2, self::color($im, $ink, 88));

        $footerH = (int) round(104 * $scale);
        $footerTop = $h - $inset - $border - $footerH;
        $contentTop = $inset + $border;
        $pad = (int) round(96 * $scale);
        $maxW = $w - ($pad * 2);
        $cx = (int) ($w / 2);

        $available = $footerTop - $contentTop;
        $fonts = ['serif' => $serif, 'sans' => $sans, 'sansBold' => $sansBold];
        $palette = ['ink' => $ink, 'accent' => $accent, 'muted' => $muted];

        // Long names and event titles push the stack past the frame; shrink until it fits.
        $stack = [];
        $total = 0;
        for ($fit = 1.0; $fit >= 0.6; $fit -= 0.05) {
            $stack = self::buildStack($im, $ctx, $tier, $fonts, $palette, $cx, $maxW, $scale * $fit, $size);
            $total = 0;
            foreach ($stack as $item) {
                $total += $item[0];
            }
            if ($total <= $available) {
                break;
            }
        }

        $y = $contentTop + (int) (($available - $total) / 2);
        foreach ($stack as [$height, $draw]) {
            if ($draw !== null) {
                $draw($y);
            }
            $y += $height;
        }

        imagefilledrectangle($im, $inset + $border, $footerTop, $w - $inset - $border, $h - $inset - $border, self::color($im, $ink));
        $footerText = 'CAVEMEN.AFRICA   ·   @CAVEMENAFRICA';
        $fSize = 23 * $scale;
        $fWidth = self::textWidth($sansBold, $fSize, $footerText, 6 * $scale);
        self::text($im, $sansBold, $fSize, $cx - (int) ($fWidth / 2), $footerTop + (int) ($footerH / 2) + (int) round(9 * $scale), $footerText, self::color($im, $paper), 6 * $scale);

        // Flat brand colours quantise cleanly and roughly halve the email attachment.
        imagetruecolortopalette($im, true, 255);
        ob_start();
        imagepng($im, null, 9);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes !== false && $bytes !== '' ? $bytes : null;
    }

    /**
     * @param array<string,mixed> $ctx
     * @param array<string,mixed> $tier
     * @param array<string,string> $fonts
     * @param array<string,string> $palette
     * @return array<int,array{0:int,1:callable|null}>
     */
    private static function buildStack($im, array $ctx, array $tier, array $fonts, array $palette, $cx, $maxW, $s, $size)
    {
        $ink = $palette['ink'];
        $stack = [];

        $logo = self::logo();
        if ($logo !== null) {
            $logoW = (int) round(($size === 'story' ? 440 : 400) * $s);
            $logoH = (int) round(imagesy($logo) * ($logoW / imagesx($logo)));
            $stack[] = [$logoH, function ($y) use ($im, $logo, $cx, $logoW, $logoH) {
                imagecopyresampled($im, $logo, $cx - (int) ($logoW / 2), $y, 0, 0, $logoW, $logoH, imagesx($logo), imagesy($logo));
            }];
            $stack[] = [(int) round(38 * $s), null];
        }

        $kicker = strtoupper(trim((string) ($ctx['seriesLabel'] ?? 'Asali · Poetry Sessions')));
        $stack[] = self::textBlock($im, $kicker, $fonts['sansBold'], 21 * $s, $ink, $cx, $maxW, 7 * $s);
        $stack[] = [(int) round(28 * $s), null];
        $stack[] = self::textBlock($im, self::headline($tier), $fonts['serif'], 78 * $s, $ink, $cx, $maxW, 0, 1.08);
        $stack[] = [(int) round(28 * $s), null];
        $stack[] = self::ruleBlock($im, $cx, (int) round(150 * $s), (int) round(7 * $s), $palette['accent']);
        $stack[] = [(int) round(28 * $s), null];

        $name = trim((string) ($ctx['name'] ?? ''));
        if ($name !== '') {
            $stack[] = self::textBlock($im, $name, $fonts['serif'], 52 * $s, $ink, $cx, $maxW, 0, 1.18);
            $stack[] = [(int) round(24 * $s), null];
        }

        $badge = strtoupper($tier['mark'] . '  ' . $tier['badge']);
        $stack[] = self::badgeBlock($im, $badge, $fonts['sansBold'], 23 * $s, $palette['accent'], $ink, $cx, $s);
        $stack[] = [(int) round(32 * $s), null];
        $stack[] = self::textBlock($im, trim((string) ($ctx['eventName'] ?? '')), $fonts['sansBold'], 33 * $s, $ink, $cx, $maxW, 0, 1.25);

        $when = trim((string) ($ctx['when'] ?? ''));
        if ($when !== '') {
            $stack[] = [(int) round(16 * $s), null];
            $stack[] = self::textBlock($im, $when, $fonts['sans'], 28 * $s, $palette['muted'], $cx, $maxW, 0, 1.3);
        }
        $venue = trim((string) ($ctx['venue'] ?? ''));
        if ($venue !== '') {
            $stack[] = [(int) round(10 * $s), null];
            $stack[] = self::textBlock($im, $venue, $fonts['sans'], 26 * $s, $palette['muted'], $cx, $maxW, 0, 1.3);
        }

        return $stack;
    }

    /**
     * @param array<string,mixed> $tier
     */
    private static function headline(array $tier)
    {
        if ($tier['key'] === 'performer') {
            return 'I’M PERFORMING';
        }
        if ($tier['key'] === 'audience') {
            return 'I’LL BE IN THE ROOM';
        }

        return 'I’LL BE THERE';
    }

    /**
     * A block is [height, draw callback]. The callback receives its top y.
     *
     * @return array{0:int,1:callable|null}
     */
    private static function textBlock($im, $text, $font, $size, $hex, $cx, $maxW, $tracking = 0, $lineHeight = 1.2)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [0, null];
        }
        $size = self::fitSize($font, $size, $text, $maxW, $tracking);
        $lines = self::wrap($font, $size, $text, $maxW, $tracking);
        $lineH = (int) round($size * $lineHeight * 1.34);
        $height = $lineH * count($lines);
        $color = self::color($im, $hex);

        return [$height, function ($y) use ($im, $lines, $font, $size, $color, $cx, $tracking, $lineH) {
            $baseline = $y + (int) round($size * 1.28);
            foreach ($lines as $line) {
                $lw = self::textWidth($font, $size, $line, $tracking);
                self::text($im, $font, $size, $cx - (int) ($lw / 2), $baseline, $line, $color, $tracking);
                $baseline += $lineH;
            }
        }];
    }

    /**
     * @return array{0:int,1:callable}
     */
    private static function ruleBlock($im, $cx, $width, $thickness, $hex)
    {
        $color = self::color($im, $hex);

        return [$thickness, function ($y) use ($im, $cx, $width, $thickness, $color) {
            imagefilledrectangle($im, $cx - (int) ($width / 2), $y, $cx + (int) ($width / 2), $y + $thickness, $color);
        }];
    }

    /**
     * @return array{0:int,1:callable}
     */
    private static function badgeBlock($im, $text, $font, $size, $bgHex, $inkHex, $cx, $scale)
    {
        $tracking = 5 * $scale;
        $padX = (int) round(34 * $scale);
        $padY = (int) round(20 * $scale);
        $tw = self::textWidth($font, $size, $text, $tracking);
        $height = (int) round($size * 1.34) + ($padY * 2);
        $bg = self::color($im, $bgHex);
        $ink = self::color($im, $inkHex);

        return [$height, function ($y) use ($im, $text, $font, $size, $cx, $tw, $padX, $padY, $height, $bg, $ink, $tracking) {
            $x1 = $cx - (int) ($tw / 2) - $padX;
            $x2 = $cx + (int) ($tw / 2) + $padX;
            self::roundedRect($im, $x1, $y, $x2, $y + $height, (int) ($height / 2), $bg);
            self::text($im, $font, $size, $cx - (int) ($tw / 2), $y + $padY + (int) round($size * 1.1), $text, $ink, $tracking);
        }];
    }

    private static function text($im, $font, $size, $x, $y, $text, $color, $tracking = 0)
    {
        if ($tracking <= 0) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);

            return;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            imagettftext($im, $size, 0, (int) round($x), $y, $color, $font, $char);
            $x += self::advance($font, $size, $char) + $tracking;
        }
    }

    private static function textWidth($font, $size, $text, $tracking = 0)
    {
        if ($tracking <= 0) {
            return self::advance($font, $size, $text);
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $w = 0;
        foreach ($chars as $char) {
            $w += self::advance($font, $size, $char) + $tracking;
        }

        return (int) round($w - $tracking);
    }

    private static function advance($font, $size, $text)
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : ($box[2] - $box[0]);
    }

    private static function fitSize($font, $size, $text, $maxW, $tracking)
    {
        $longest = 0;
        foreach (preg_split('/\s+/', $text) ?: [] as $word) {
            $longest = max($longest, self::textWidth($font, $size, $word, $tracking));
        }
        while ($longest > $maxW && $size > 12) {
            $size -= 2;
            $longest = 0;
            foreach (preg_split('/\s+/', $text) ?: [] as $word) {
                $longest = max($longest, self::textWidth($font, $size, $word, $tracking));
            }
        }

        return $size;
    }

    /**
     * @return string[]
     */
    private static function wrap($font, $size, $text, $maxW, $tracking)
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (self::textWidth($font, $size, $candidate, $tracking) <= $maxW || $current === '') {
                $current = $candidate;
                continue;
            }
            $lines[] = $current;
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function frame($im, $x1, $y1, $x2, $y2, $thickness, $color)
    {
        imagefilledrectangle($im, $x1, $y1, $x2, $y1 + $thickness, $color);
        imagefilledrectangle($im, $x1, $y2 - $thickness, $x2, $y2, $color);
        imagefilledrectangle($im, $x1, $y1, $x1 + $thickness, $y2, $color);
        imagefilledrectangle($im, $x2 - $thickness, $y1, $x2, $y2, $color);
    }

    private static function roundedRect($im, $x1, $y1, $x2, $y2, $r, $color)
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        $d = $r * 2;
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $d, $d, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $d, $d, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $d, $d, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $d, $d, $color);
    }

    private static function drawPattern($im, $w, $h)
    {
        $path = dirname(__DIR__) . '/assets/cavemen-pattern.png';
        if (!is_readable($path)) {
            return;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = max($w / $sw, $h / $sh);
        $tw = (int) ceil($sw * $ratio);
        $th = (int) ceil($sh * $ratio);
        $scaled = imagecreatetruecolor($tw, $th);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        imagecopymerge($im, $scaled, 0, 0, 0, 0, $w, $h, 22);
        imagedestroy($scaled);
        imagedestroy($src);
    }

    /**
     * Logo art sits inside a large transparent square; trim it so layout is predictable.
     *
     * @return resource|\GdImage|null
     */
    private static function logo()
    {
        if (array_key_exists('mark', self::$logoCache)) {
            return self::$logoCache['mark'];
        }
        self::$logoCache['mark'] = null;
        $path = dirname(__DIR__) . '/assets/Logo-no-bg.PNG';
        if (!is_readable($path)) {
            return null;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            return null;
        }
        imagealphablending($src, false);
        imagesavealpha($src, true);

        $bounds = self::opaqueBounds($src);
        if ($bounds === null) {
            self::$logoCache['mark'] = $src;

            return $src;
        }
        [$x1, $y1, $x2, $y2] = $bounds;
        $cw = $x2 - $x1 + 1;
        $ch = $y2 - $y1 + 1;
        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefilledrectangle($out, 0, 0, $cw, $ch, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $src, 0, 0, $x1, $y1, $cw, $ch);
        imagedestroy($src);
        self::$logoCache['mark'] = $out;

        return $out;
    }

    /**
     * Bounding box of non-transparent pixels, measured on a downscaled copy for speed.
     *
     * @return array{0:int,1:int,2:int,3:int}|null
     */
    private static function opaqueBounds($src)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $step = max(1, (int) floor(min($w, $h) / 400));
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $alpha = (imagecolorat($src, $x, $y) >> 24) & 0x7F;
                if ($alpha > 100) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0) {
            return null;
        }

        return [
            max(0, $minX - $step),
            max(0, $minY - $step),
            min($w - 1, $maxX + $step),
            min($h - 1, $maxY + $step),
        ];
    }

    private static function color($im, $hex, $alpha = 0)
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return $alpha > 0
            ? imagecolorallocatealpha($im, $r, $g, $b, min(127, $alpha))
            : imagecolorallocate($im, $r, $g, $b);
    }

    private static function font($file)
    {
        $path = dirname(__DIR__) . '/vendor/dompdf/dompdf/lib/fonts/' . $file;

        return is_readable($path) ? $path : null;
    }
}
