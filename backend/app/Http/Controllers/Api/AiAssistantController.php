<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantConversation;
use App\Models\AiAssistantMessage;
use App\Services\Assistant\AssistantListShaper;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\Billing\AiBalanceException;
use App\Services\Assistant\Billing\AiMeter;
use App\Services\Assistant\Billing\AiPriceBook;
use App\Services\Assistant\Billing\AiPriceMissingException;
use App\Services\Assistant\Billing\AiUsageContext;
use App\Services\Assistant\Billing\AiWallet;
use App\Services\Assistant\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

final class AiAssistantController extends Controller
{
    public function settings(Request $request, AssistantSettings $settings, AiWallet $wallet): JsonResponse
    {
        return response()->json([
            ...$settings->publicData(),
            'can_manage'=>$request->user()->can('assistant.manage'),
            'can_view_all_usage'=>$request->user()->can('assistant.usage.view'),
            'balance'=>AiUsageController::balanceData($request, $wallet),
        ]);
    }

    public function updateSettings(Request $request, AssistantSettings $settings, AiWallet $wallet): JsonResponse
    {
        $models = array_keys(config('assistant.models', []));
        $validated = $request->validate([
            'enabled'=>['required','boolean'],
            'default_model'=>['required',Rule::in($models)],
            'allowed_models'=>['required','array','min:1'],
            'allowed_models.*'=>['required','string','distinct',Rule::in($models)],
        ]);
        if (! in_array($validated['default_model'], $validated['allowed_models'], true)) {
            return response()->json(['message'=>'مدل پیش‌فرض باید در فهرست مدل‌های مجاز باشد.'], 422);
        }
        $settings->save($validated);
        return $this->settings($request, $settings, $wallet);
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

    public function message(Request $request, AssistantSettings $settings, AssistantService $assistant, OpenAiClient $client, AiWallet $wallet, AiPriceBook $prices): JsonResponse
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
        if ($blocked = $this->billingBlock($request, $wallet, $prices, $model)) return $blocked;
        try {
            return response()->json($assistant->send($request->user(), trim($validated['message']), $model, $conversation));
        } catch (AiBalanceException $exception) {
            return response()->json($exception->payload($request->user()->can('assistant.manage')), 402);
        } catch (AiPriceMissingException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 502);
        }
    }

    public function transcribe(Request $request, AssistantSettings $settings, OpenAiClient $client, AiMeter $meter, AiWallet $wallet, AiPriceBook $prices): JsonResponse
    {
        $validated = $request->validate(['audio'=>['required','file','max:10240','mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,video/webm,video/mp4']]);
        if (! config('assistant.voice_enabled')) return response()->json(['message'=>'پیام صوتی فعلاً غیرفعال است.'], 403);
        if (! $settings->current()['enabled']) return response()->json(['message'=>'دستیار برای این کلینیک غیرفعال است.'], 403);
        if (! $client->configured()) return response()->json(['message'=>'کلید OpenAI روی سرور تنظیم نشده است.'], 503);
        if ($blocked = $this->billingBlock($request, $wallet, $prices, (string) config('assistant.transcription_model'))) return $blocked;
        try {
            return response()->json(['text'=>$meter->transcribe(AiUsageContext::forCurrentTenant($request->user(), 'transcription'), $validated['audio'])]);
        } catch (AiBalanceException $exception) {
            return response()->json($exception->payload($request->user()->can('assistant.manage')), 402);
        } catch (AiPriceMissingException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 502);
        }
    }

    /** صفحهٔ بعدی فهرست یک کارت؛ بدون فراخوانی AI و بدون هزینه. */
    public function listPage(Request $request, AssistantListShaper $lists): JsonResponse
    {
        $validated = $request->validate([
            'tool'=>['required','string',Rule::in(AssistantListShaper::LIST_TOOLS)],
            'arguments'=>['nullable','array'],
            'offset'=>['required','integer','min:0','max:100000'],
            'limit'=>['required','integer','min:1','max:'.AssistantListShaper::MAX_PAGE],
        ]);
        try {
            return response()->json($lists->page($request->user(), $validated['tool'], $validated['arguments'] ?? [], (int) $validated['offset'], (int) $validated['limit']));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message'=>$exception->getMessage()], 422);
        }
    }

    private function billingBlock(Request $request, AiWallet $wallet, AiPriceBook $prices, string $model): ?JsonResponse
    {
        if (! $prices->current($model)) return response()->json(['message'=>(new AiPriceMissingException($model))->getMessage()], 422);
        try {
            $wallet->ensureUsable((string) tenant()->getTenantKey());
        } catch (AiBalanceException $exception) {
            return response()->json($exception->payload($request->user()->can('assistant.manage')), 402);
        }
        return null;
    }

    private function owned(Request $request, int $id): AiAssistantConversation
    {
        return AiAssistantConversation::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
