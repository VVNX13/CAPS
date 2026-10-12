<?php

use App\Support\QrisHelper;
use Illuminate\Support\Str;

if (! function_exists('rupiah')) {
    /**
     * Format a number as Indonesian Rupiah, e.g. 25000 -> "Rp 25.000".
     */
    function rupiah(float|int|string|null $number, bool $withPrefix = true): string
    {
        $formatted = number_format((float) $number, 0, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }
}

if (! function_exists('qris_image_url')) {
    /**
     * Convenience wrapper around the QRIS QR image generator.
     */
    function qris_image_url(string $payload, int $size = 280): string
    {
        return QrisHelper::getQrImageUrl($payload, $size);
    }
}

if (! function_exists('asset_versioned')) {
    /**
     * asset() with the file mtime appended as a cache-busting query string.
     *
     * The service worker caches same-origin assets aggressively, so a plain
     * asset() URL keeps serving a stale file after it changes on disk. Bumping
     * the URL whenever the file changes makes that class of bug impossible.
     */
    function asset_versioned(string $path): string
    {
        $url = asset($path);
        $file = public_path(Str::ltrim($path, '/'));

        if (! is_file($file)) {
            return $url;
        }

        return $url.'?v='.filemtime($file);
    }
}
