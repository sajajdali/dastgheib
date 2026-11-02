<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\ExecutableFinder;

class PatientImageOptimizer
{
    /** @return array{path: string, size: int, mime_type: string} */
    public function store(UploadedFile $file, string $directory, array $settings): array
    {
        $mime = (string) $file->getMimeType();
        if (! Str::startsWith($mime, 'image/')) {
            $path = $file->store($directory, 'public');

            return ['path' => $path, 'size' => (int) $file->getSize(), 'mime_type' => $mime];
        }

        $source = $file->getRealPath();
        if (! $source) {
            throw ValidationException::withMessages(['file' => 'فایل تصویر خوانده نشد.']);
        }

        // Preserve animations instead of silently replacing them with one still frame.
        if (($mime === 'image/gif' && ! $this->magickBinary())
            || (in_array($mime, ['image/gif', 'image/webp'], true) && $this->frameCount($source) > 1)) {
            $path = $file->store($directory, 'public');

            return ['path' => $path, 'size' => (int) $file->getSize(), 'mime_type' => $mime];
        }

        $maxSide = (int) $settings['max_side'];
        $targetBytes = (int) $settings['target_kb'] * 1024;
        $dimensions = @getimagesize($source);
        if ($mime === 'image/webp' && $dimensions
            && max($dimensions[0], $dimensions[1]) <= $maxSide
            && $file->getSize() <= $targetBytes) {
            $path = $file->store($directory, 'public');

            return ['path' => $path, 'size' => (int) $file->getSize(), 'mime_type' => 'image/webp'];
        }

        $temporary = tempnam(sys_get_temp_dir(), 'clinic-photo-');
        if ($temporary === false) {
            throw ValidationException::withMessages(['file' => 'فضای موقت برای پردازش عکس در دسترس نیست.']);
        }

        try {
            $magick = $this->magickBinary();
            if (! $magick) {
                if (! $this->convertWithGd($source, $temporary, $mime, $maxSide, $targetBytes, (int) $settings['quality'])) {
                    if (in_array($mime, ['image/heic', 'image/heif'], true)) {
                        // HEIC/HEIF cannot be decoded by GD. Keep the original upload instead of losing it.
                        $path = $file->store($directory, 'public');

                        return ['path' => $path, 'size' => (int) $file->getSize(), 'mime_type' => $mime];
                    }
                    throw ValidationException::withMessages(['file' => 'تبدیل این عکس به WebP انجام نشد.']);
                }
            }
            // The size is a soft target: never lower quality below 80 to hit it.
            for ($quality = (int) $settings['quality']; $magick && $quality >= 80; $quality = max(79, $quality - 4)) {
                $process = new Process([
                    $magick, $source.'[0]', '-auto-orient',
                    '-resize', "{$maxSide}x{$maxSide}>",
                    '-strip', '-quality', (string) $quality,
                    'webp:'.$temporary,
                ]);
                $process->setTimeout(90);

                try {
                    $process->run();
                } catch (\Throwable $exception) {
                    throw ValidationException::withMessages(['file' => 'پردازش عکس در سرور در دسترس نیست.']);
                }

                if (! $process->isSuccessful() || ! is_file($temporary) || filesize($temporary) === 0) {
                    throw ValidationException::withMessages(['file' => 'تبدیل این عکس به WebP انجام نشد.']);
                }

                if (filesize($temporary) <= $targetBytes || $quality <= 80) {
                    break;
                }
            }

            $path = Storage::disk('public')->putFileAs(
                $directory,
                new File($temporary),
                Str::random(40).'.webp'
            );
            if (! $path) {
                throw ValidationException::withMessages(['file' => 'ذخیره عکس پردازش‌شده انجام نشد.']);
            }

            return [
                'path' => $path,
                'size' => Storage::disk('public')->size($path),
                'mime_type' => 'image/webp',
            ];
        } finally {
            @unlink($temporary);
        }
    }

    private function frameCount(string $source): int
    {
        $magick = $this->magickBinary();
        if (! $magick) return 1;
        $process = new Process([$magick, 'identify', '-ping', '-format', "%n\n", $source]);
        try {
            $process->run();
        } catch (\Throwable $exception) {
            return 1;
        }

        return $process->isSuccessful() ? max(1, (int) $process->getOutput()) : 1;
    }

    protected function magickBinary(): ?string
    {
        return (new ExecutableFinder)->find('magick');
    }

    private function convertWithGd(string $source, string $destination, string $mime, int $maxSide, int $targetBytes, int $quality): bool
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')
            || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return false;

        $image = @imagecreatefromstring(file_get_contents($source));
        if (! $image) return false;

        try {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
                if ($orientation === 3) $image = imagerotate($image, 180, 0);
                if ($orientation === 6) $image = imagerotate($image, 270, 0);
                if ($orientation === 8) $image = imagerotate($image, 90, 0);
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, $maxSide / max($width, $height));
            $output = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
            try {
                for ($q = $quality; $q >= 80; $q = max(79, $q - 4)) {
                    if (! imagewebp($output, $destination, $q)) return false;
                    if (filesize($destination) <= $targetBytes || $q <= 80) break;
                }
            } finally {
                imagedestroy($output);
            }

            return is_file($destination) && filesize($destination) > 0;
        } finally {
            imagedestroy($image);
        }
    }
}
