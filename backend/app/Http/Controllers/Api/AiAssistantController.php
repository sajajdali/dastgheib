<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantConversation;
use App\Models\AiAssistantMessage;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use RuntimeException;

final class AiAssistantController extends Controller
{
    public function settings(Request $request, AssistantSettings $settings): JsonResponse
    {
        return response()->json([...$settings->publicData(), 'can_manage'=>$request->user()->can('assistant.manage')]);
    }

    public function updateSettings(Request $request, AssistantSettings $settings): JsonResponse
    {
        $models = array_keys(config('assistant.models', []));
        $validated = $request->validate([
            'enabled'=>['required','boolean'],
            'default_model'=>['required',Rule::in($models)],
            'allowed_models'=>['required','array','min:1'],
            'allowed_models.*'=>['required','string','distinct',Rule::in($models)],
            'daily_limit'=>['required','integer','between:1,500'],
        ]);
        if (! in_array($validated['default_model'], $validated['allowed_models'], true)) {
            return response()->json(['message'=>'مدل پیش‌فرض باید در فهرست مدل‌های مجاز باشد.'], 422);
        }
        $settings->save($validated);
        return $this->settings($request, $settings);
    }

    public function conversations(Request $request): JsonResponse
    {
        return response()->json(AiAssistantConversation::query()->where('user_id', $request->user()->id)
            ->latest('updated_at')->limit(30)->get(['id','title','model','updated_at']));
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $row = $this->owned($request, $conversation);
        return response()->json(['conversation'=>$row, 'messages'=>$row->messages()->orderBy('id')->limit(100)
            ->get(['id','role','content','presentation','created_at'])]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation)->delete();
        return response()->json(['ok'=>true]);
    }

    public function message(Request $request, AssistantSettings $settings, AssistantService $assistant, OpenAiClient $client): JsonResponse
    {
        $validated = $request->validate([
            'message'=>['required','string','min:2','max:2000'],
            'model'=>['nullable','string',Rule::in(array_keys(config('assistant.models', [])))],
            'conversation_id'=>['nullable','integer','min:1'],
        ]);
        $current = $settings->current();
        if (! $current['enabled']) return response()->json(['message'=>'دستیار برای این کلینیک غیرفعال است.'], 403);
        if (! $client->configured()) return response()->json(['message'=>'کلید OpenAI روی سرور تنظیم نشده است.'], 503);
        $model = $validated['model'] ?? $current['default_model'];
        if (! in_array($model, $current['allowed_models'], true)) return response()->json(['message'=>'این مدل برای کلینیک مجاز نیست.'], 422);
        $conversation = isset($validated['conversation_id']) ? $this->owned($request, (int) $validated['conversation_id']) : null;
        $key = $this->rateKey($request);
        if (RateLimiter::tooManyAttempts($key, (int) $current['daily_limit'])) {
            return response()->json(['message'=>'سقف پیام‌های امروز به دستیار پر شده است.'], 429);
        }
        RateLimiter::hit($key, 86400);
        try {
            return response()->json($assistant->send($request->user(), trim($validated['message']), $model, $conversation));
        } catch (RuntimeException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 502);
        }
    }

    public function transcribe(Request $request, AssistantSettings $settings, OpenAiClient $client): JsonResponse
    {
        $validated = $request->validate(['audio'=>['required','file','max:10240','mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,video/webm,video/mp4']]);
        if (! $settings->current()['enabled']) return response()->json(['message'=>'دستیار برای این کلینیک غیرفعال است.'], 403);
        if (! $client->configured()) return response()->json(['message'=>'کلید OpenAI روی سرور تنظیم نشده است.'], 503);
        $key = 'assistant:voice:'.$this->rateKey($request);
        if (RateLimiter::tooManyAttempts($key, 30)) return response()->json(['message'=>'سقف رونویسی ویس پر شده است.'], 429);
        RateLimiter::hit($key, 86400);
        try {
            return response()->json(['text'=>$client->transcribe($validated['audio'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 502);
        }
    }

    private function owned(Request $request, int $id): AiAssistantConversation
    {
        return AiAssistantConversation::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function rateKey(Request $request): string
    {
        $tenant = function_exists('tenant') && tenant() ? tenant()->getTenantKey() : 'central';
        return 'assistant:message:'.$tenant.':'.$request->user()->id.':'.now('Asia/Tehran')->toDateString();
    }
}
