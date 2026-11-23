<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PatientMediaController;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class PatientMediaUploadMessageTest extends TestCase
{
    public function test_oversized_heic_upload_error_is_persian_and_mentions_webp(): void
    {
        $controller = app(PatientMediaController::class);
        $method = new ReflectionMethod($controller, 'uploadedFileFailureMessage');
        $method->setAccessible(true);

        $file = UploadedFile::fake()->createWithContent('photo.heic', str_repeat('a', 100));
        // Simulate PHP rejecting the upload for size (common with brew PHP 2M limit).
        $file = new class($file->getPathname(), 'photo.heic', 'image/heic', UPLOAD_ERR_INI_SIZE, true) extends UploadedFile {
        };

        $message = $method->invoke($controller, $file);

        $this->assertStringContainsString('HEIC', $message);
        $this->assertStringContainsString('WebP', $message);
        $this->assertDoesNotMatchRegularExpression('/failed to upload/i', $message);
    }

    public function test_invalid_upload_bag_is_thrown_before_laravel_english_message(): void
    {
        $controller = app(PatientMediaController::class);
        $method = new ReflectionMethod($controller, 'assertUploadedMediaFiles');
        $method->setAccessible(true);

        $bad = new UploadedFile(
            tempnam(sys_get_temp_dir(), 'heic'),
            'angle.heic',
            'image/heic',
            UPLOAD_ERR_INI_SIZE,
            true
        );

        try {
            $method->invoke($controller, [$bad]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['files.0'][0] ?? '';
            $this->assertStringContainsString('سقف حجم', $message);
            $this->assertStringContainsString('HEIC', $message);
        }
    }
}
