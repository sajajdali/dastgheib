<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\File\File;

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
        if (in_array($mime, ['image/gif', 'image/webp'], true) && $this->frameCount($source) > 1) {
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
            // The size is a soft target: never lower quality below 80 to hit it.
            for ($quality = (int) $settings['quality']; $quality >= 80; $quality = max(79, $quality - 4)) {
                $process = new Process([
                    'magick', $source.'[0]', '-auto-orient',
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
        $process = new Process(['magick', 'identify', '-ping', '-format', "%n\n", $source]);
        try {
            $process->run();
        } catch (\Throwable $exception) {
            return 1;
        }

        return $process->isSuccessful() ? max(1, (int) $process->getOutput()) : 1;
    }
}
