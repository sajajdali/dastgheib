<?php

namespace Tests\Unit;

use App\Services\Assistant\OpenAiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class OpenAiClientTest extends TestCase
{
    public function test_response_request_is_sent_with_server_key(): void
    {
        config(['assistant.api_key'=>'test-secret']);
        Http::fake(['api.openai.com/v1/responses'=>Http::response(['output'=>[]], 200)]);

        $this->assertSame(['output'=>[]], app(OpenAiClient::class)->respond(['model'=>'gpt-6-luna','store'=>false]));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['store'] === false && $request['model'] === 'gpt-6-luna');
    }

    public function test_voice_is_uploaded_to_transcription_endpoint(): void
    {
        config(['assistant.api_key'=>'test-secret']);
        Http::fake(['api.openai.com/v1/audio/transcriptions'=>Http::response(['text'=>'امروز چند نوبت داریم؟'], 200)]);
        $file = UploadedFile::fake()->create('voice.webm', 24, 'audio/webm');

        $this->assertSame('امروز چند نوبت داریم؟', app(OpenAiClient::class)->transcribe($file));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/audio/transcriptions'));
    }

    public function test_exhausted_credit_has_a_clear_error(): void
    {
        config(['assistant.api_key'=>'test-secret']);
        Http::fake(['api.openai.com/v1/responses'=>Http::response([
            'error'=>['code'=>'credit_balance_exhausted','type'=>'insufficient_quota'],
        ], 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('اعتبار حساب OpenAI کافی نیست.');
        app(OpenAiClient::class)->respond(['model'=>'gpt-6-luna','input'=>'سلام']);
    }
}
