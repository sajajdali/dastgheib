<?php

namespace Tests\Unit;

use App\Services\PatientImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PatientImageOptimizerTest extends TestCase
{
    public function test_small_photo_is_converted_to_webp_without_enlargement(): void
    {
        Storage::fake('public');

        $stored = app(PatientImageOptimizer::class)->store(
            UploadedFile::fake()->image('small.jpg', 200, 900),
            'patients/test/media',
            ['max_side' => 2400, 'quality' => 88, 'target_kb' => 1024]
        );

        $this->assertSame('image/webp', $stored['mime_type']);
        $this->assertStringEndsWith('.webp', $stored['path']);
        $this->assertSame([200, 900], array_slice(getimagesize(Storage::disk('public')->path($stored['path'])), 0, 2));
        $this->assertSame(Storage::disk('public')->size($stored['path']), $stored['size']);
    }

    public function test_large_photo_is_limited_by_its_longest_side(): void
    {
        Storage::fake('public');

        $stored = app(PatientImageOptimizer::class)->store(
            UploadedFile::fake()->image('large.png', 800, 1200),
            'patients/test/media',
            ['max_side' => 600, 'quality' => 88, 'target_kb' => 1024]
        );

        $this->assertSame([400, 600], array_slice(getimagesize(Storage::disk('public')->path($stored['path'])), 0, 2));
    }

    public function test_heic_photo_is_converted_when_the_server_supports_heic(): void
    {
        Storage::fake('public');
        $source = UploadedFile::fake()->image('source.png', 30, 40);
        $temporary = tempnam(sys_get_temp_dir(), 'photo-heic-test-');

        try {
            $convert = new Process(['magick', $source->getRealPath(), 'heic:'.$temporary]);
            $convert->run();
            if (! $convert->isSuccessful()) {
                $this->markTestSkipped('HEIC encoding is unavailable on this server.');
            }

            $file = new UploadedFile($temporary, 'source.heic', 'image/heic', null, true);
            $stored = app(PatientImageOptimizer::class)->store(
                $file,
                'patients/test/media',
                ['max_side' => 2400, 'quality' => 88, 'target_kb' => 1024]
            );

            $this->assertSame('image/webp', $stored['mime_type']);
            $this->assertSame([30, 40], array_slice(getimagesize(Storage::disk('public')->path($stored['path'])), 0, 2));
        } finally {
            @unlink($temporary);
        }
    }

    public function test_animated_gif_is_not_replaced_by_a_still_frame(): void
    {
        Storage::fake('public');
        $first = UploadedFile::fake()->image('first.png', 20, 20);
        $second = UploadedFile::fake()->image('second.png', 20, 20);
        $temporary = tempnam(sys_get_temp_dir(), 'photo-gif-test-');

        try {
            $convert = new Process(['magick', '-delay', '10', $first->getRealPath(), $second->getRealPath(), '-loop', '0', 'gif:'.$temporary]);
            $convert->run();
            $this->assertTrue($convert->isSuccessful());

            $file = new UploadedFile($temporary, 'animated.gif', 'image/gif', null, true);
            $stored = app(PatientImageOptimizer::class)->store(
                $file,
                'patients/test/media',
                ['max_side' => 2400, 'quality' => 88, 'target_kb' => 1024]
            );

            $this->assertSame('image/gif', $stored['mime_type']);
            $this->assertStringEndsWith('.gif', $stored['path']);
        } finally {
            @unlink($temporary);
        }
    }
}
