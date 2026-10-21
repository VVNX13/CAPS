<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menu photo handling.
 *
 * The `products.image` column holds one of three things:
 *   null                  -> no photo, the UI draws a local placeholder
 *   https://...           -> an external URL the owner pasted in
 *   products/xxx.jpg      -> an uploaded file under storage/app/public
 *
 * The three cases are distinguished by looking at the value, so old rows with
 * pasted URLs keep working and no migration is needed.
 *
 * Uploads are resized on the way in. Phone photos are routinely 4000px wide and
 * 3-5 MB, which is wasteful on a cafe's mobile data, and the menu only ever
 * shows them at a few hundred pixels.
 */
class ProductImage
{
    public const DIRECTORY = 'products';

    public const MAX_UPLOAD_KB = 4096;

    /** Longest edge after resizing, in pixels. */
    public const MAX_DIMENSION = 900;

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * The public URL used in the page, or null when the owner pasted a URL.
     */
    public static function url(?string $image, ?string $name = null): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (self::isExternal($image)) {
            if ($name && str_contains($image, 'unsplash.com')) {
                $local = self::localIllustrationFor($name);
                if ($local) {
                    return $local;
                }
            }
            return $image;
        }

        return '/storage/'.$image;
    }

    public static function localIllustrationFor(string $name): ?string
    {
        $pName = strtolower($name);
        if (str_contains($pName, 'gula aren') || str_contains($pName, 'aren') || str_contains($pName, 'caramel') || str_contains($pName, 'kopi')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_15-PM-2.png';
        }
        if (str_contains($pName, 'v60') || str_contains($pName, 'gayo') || str_contains($pName, 'americano') || str_contains($pName, 'berry') || str_contains($pName, 'sparkler') || str_contains($pName, 'jus') || str_contains($pName, 'alpukat') || str_contains($pName, 'mangga')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_16-PM-3.png';
        }
        if (str_contains($pName, 'matcha')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_17-PM-4.png';
        }
        if (str_contains($pName, 'nasi goreng')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_19-PM-5.png';
        }
        if (str_contains($pName, 'ayam bakar') || str_contains($pName, 'ayam') || str_contains($pName, 'croissant') || str_contains($pName, 'sandwich')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_20-PM-6.png';
        }
        if (str_contains($pName, 'mie') || str_contains($pName, 'seafood') || str_contains($pName, 'bakso')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_28_55-PM-1.png';
        }
        if (str_contains($pName, 'sop') || str_contains($pName, 'buntut') || str_contains($pName, 'soto')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_28_56-PM-2.png';
        }
        if (str_contains($pName, 'kentang') || str_contains($pName, 'truffle') || str_contains($pName, 'fries') || str_contains($pName, 'tahu') || str_contains($pName, 'cireng')) {
            return '/assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_21-PM-7.png';
        }
        return null;
    }

    public static function isExternal(?string $image): bool
    {
        return str_starts_with(trim((string) $image), 'http://')
            || str_starts_with(trim((string) $image), 'https://');
    }

    /**
     * Store an uploaded photo, downscaling it first.
     *
     * @return string the value to persist in products.image
     */
    public static function store(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $name = Str::random(20).'.'.$extension;
        $relative = self::DIRECTORY.'/'.$name;

        $disk = Storage::disk('public');

        if (! $disk->exists(self::DIRECTORY)) {
            $disk->makeDirectory(self::DIRECTORY);
        }

        $temporary = $file->getRealPath();

        if (self::canResize($file)) {
            $temporary = self::resize($temporary, $extension);
        }

        $disk->put($relative, (string) file_get_contents($temporary));

        if ($temporary !== $file->getRealPath() && is_file($temporary)) {
            @unlink($temporary);
        }

        return $relative;
    }

    /**
     * Remove a previously uploaded file. External URLs are left alone.
     */
    public static function delete(?string $image): void
    {
        $image = trim((string) $image);

        if ($image === '' || self::isExternal($image)) {
            return;
        }

        Storage::disk('public')->delete($image);
    }

    private static function canResize(UploadedFile $file): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && in_array($file->getMimeType(), ['image/jpeg', 'image/png'], true);
    }

    /**
     * Downscale to MAX_DIMENSION, preserving aspect ratio.
     *
     * @return string path to the resized temporary file
     */
    private static function resize(string $source, string $extension): string
    {
        $info = @getimagesize($source);

        if ($info === false) {
            return $source;
        }

        [$width, $height] = $info;

        $type = $info[2];
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            default => false,
        };

        if ($image === false) {
            return $source;
        }

        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // PNG transparency must survive the copy or drinks get a black box.
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $target = tempnam(sys_get_temp_dir(), 'img').'.'.$extension;

        if ($extension === 'png') {
            imagepng($canvas, $target, 6);
        } else {
            imagejpeg($canvas, $target, 85);
        }

        imagedestroy($image);
        imagedestroy($canvas);

        return is_file($target) ? $target : $source;
    }
}
