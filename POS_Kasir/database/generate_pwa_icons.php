<?php

/**
 * Generates the PWA icon set into public/assets/icons.
 *
 * Run with:  php database/generate_pwa_icons.php
 *
 * Kept in the repo so the icons can be regenerated or tweaked without an
 * external design tool. Re-run it after changing the palette or the glyph.
 */
$dir = __DIR__.'/../public/assets/icons';

if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/**
 * Draws a filled ring (annulus) so the cup handle reads as a handle.
 */
function ring($image, int $cx, int $cy, float $rx, float $ry, int $thickness, int $color): void
{
    $steps = 720;

    for ($i = 0; $i < $steps; $i++) {
        $angle = 2 * M_PI * $i / $steps;

        for ($t = 0; $t <= $thickness; $t++) {
            $r = $t / 2;
            $px = (int) round($cx + ($rx - $r) * cos($angle));
            $py = (int) round($cy + ($ry - $r) * sin($angle));
            imagesetpixel($image, $px, $py, $color);
        }
    }
}

/**
 * Draws a quarter arc, used for the rounded corners of the cup.
 *
 * Angles are in image coordinates (y grows downwards), so:
 * 180-270 = top-left, 270-360 = top-right, 0-90 = bottom-right, 90-180 = bottom-left.
 */
function arc($image, int $cx, int $cy, float $r, float $from, float $to, int $thickness, int $color): void
{
    $steps = max(24, (int) round($r * 2));

    for ($i = 0; $i <= $steps; $i++) {
        $angle = deg2rad($from + ($to - $from) * $i / $steps);

        for ($t = 0; $t <= $thickness; $t++) {
            $rr = $r - $t / 2;
            $px = (int) round($cx + $rr * cos($angle));
            $py = (int) round($cy + $rr * sin($angle));
            imagesetpixel($image, $px, $py, $color);
        }
    }
}

/**
 * Rounded rectangle outline.
 */
function strokeRect($image, int $x, int $y, int $w, int $h, int $r, int $thickness, int $color): void
{
    $r = max(0, min($r, (int) floor(min($w, $h) / 2)));

    if ($r === 0) {
        imagefilledrectangle($image, $x, $y, $x + $w, $y + $thickness, $color);
        imagefilledrectangle($image, $x, $y + $h - $thickness, $x + $w, $y + $h, $color);
        imagefilledrectangle($image, $x, $y, $x + $thickness, $y + $h, $color);
        imagefilledrectangle($image, $x + $w - $thickness, $y, $x + $w, $y + $h, $color);

        return;
    }

    // Sides.
    imagefilledrectangle($image, $x, $y + $r, $x + $thickness, $y + $h - $r, $color);
    imagefilledrectangle($image, $x + $w - $thickness, $y + $r, $x + $w, $y + $h - $r, $color);
    // Top and bottom edges.
    imagefilledrectangle($image, $x + $r, $y, $x + $w - $r, $y + $thickness, $color);
    imagefilledrectangle($image, $x + $r, $y + $h - $thickness, $x + $w - $r, $y + $h, $color);
    // Corner arcs.
    arc($image, $x + $r, $y + $r, $r, 180, 270, $thickness, $color);
    arc($image, $x + $w - $r, $y + $r, $r, 270, 360, $thickness, $color);
    arc($image, $x + $w - $r, $y + $h - $r, $r, 0, 90, $thickness, $color);
    arc($image, $x + $r, $y + $h - $r, $r, 90, 180, $thickness, $color);
}

/**
 * @param  int  $size  Square size in pixels.
 * @param  float  $scale  Glyph scale relative to the canvas.
 */
function makeIcon(int $size, float $scale, string $path): void
{
    $image = imagecreatetruecolor($size, $size);
    imageantialias($image, true);

    // Diagonal gradient from deep navy to a brighter blue.
    $from = [30, 58, 138];   // #1e3a8a
    $to = [37, 99, 235];     // #2563eb

    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $t = (($x + $y) / (2 * ($size - 1)));
            $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
            $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
            $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
            imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
        }
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $unit = $size * $scale;

    // Glyph parts, measured before positioning so the block can be centred.
    $cupW = (int) round($unit * 0.30);
    $cupH = (int) round($unit * 0.30);
    $handleR = (int) round($unit * 0.10);
    $thickness = max(2, (int) round($unit * 0.026));

    $saucerH = max(2, (int) round($unit * 0.024));
    $saucerGap = (int) round($unit * 0.05);
    $saucerW = (int) round(($cupW + $handleR * 1.2) * 1.18);

    $steamH = (int) round($unit * 0.16);
    $steamGap = (int) round($unit * 0.06);

    $blockH = $steamH + $steamGap + $cupH + $saucerGap + $saucerH;
    $blockW = $saucerW;

    $originX = (int) round(($size - $blockW) / 2);
    $originY = (int) round(($size - $blockH) / 2);

    // Centre the cup inside the saucer.
    $cupX = $originX + (int) round(($blockW - $cupW) / 2);
    $cupY = $originY + $steamH + $steamGap;

    // Steam: three smooth sine waves sitting just above the cup.
    $amplitude = $unit * 0.020;
    $wavelength = $steamH * 0.85;
    for ($i = -1; $i <= 1; $i++) {
        $baseX = $cupX + $cupW / 2 + $i * $unit * 0.075;
        for ($s = 0; $s <= $steamH; $s++) {
            $offset = sin(($s / $wavelength) * 2 * M_PI) * $amplitude;
            $px = (int) round($baseX + $offset);
            $py = $cupY - $steamGap - $s;
            imagesetpixel($image, $px, $py, $white);
            imagesetpixel($image, $px, $py - 1, $white);
        }
    }

    // Cup body.
    strokeRect($image, $cupX, $cupY, $cupW, $cupH, (int) round($unit * 0.045), $thickness, $white);

    // Handle: half ring on the right side only.
    for ($angle = -80; $angle <= 80; $angle++) {
        $rad = deg2rad($angle);
        for ($t = 0; $t <= $thickness; $t++) {
            $r = $handleR - $t / 2;
            $px = (int) round($cupX + $cupW + $r * cos($rad) * 0.95);
            $py = (int) round($cupY + $cupH * 0.34 + $r * sin($rad));
            imagesetpixel($image, $px, $py, $white);
        }
    }

    // Saucer.
    $saucerY = $cupY + $cupH + $saucerGap;
    $saucerX = $originX;
    imagefilledrectangle(
        $image,
        $saucerX,
        $saucerY,
        $saucerX + $blockW,
        $saucerY + $saucerH,
        $white
    );

    imagepng($image, $path, 9);
    imagedestroy($image);
}

// Standard icons.
makeIcon(192, 1.0, $dir.'/icon-192.png');
makeIcon(512, 1.0, $dir.'/icon-512.png');

// Maskable icon: full-bleed square with a smaller glyph so Android can crop safely.
makeIcon(512, 0.60, $dir.'/icon-maskable-512.png');

foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png'] as $file) {
    printf("%-26s %6.1f KB\n", $file, filesize($dir.'/'.$file) / 1024);
}
