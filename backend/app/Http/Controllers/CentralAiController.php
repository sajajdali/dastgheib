<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AiUsageController;
use App\Models\Tenant;
use App\Services\Assistant\Billing\AiBillingSettings;
use App\Services\Assistant\Billing\AiPriceBook;
use App\Services\Assistant\Billing\AiUsageFilters;
use App\Services\Assistant\Billing\AiUsageReport;
use App\Services\Assistant\Billing\AiWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** موجودی، شارژ، نرخ مدل‌ها و ضریب سود دستیار هوش مصنوعی در پنل مرکزی. */
final class CentralAiController extends Controller
{
    public function __construct(
        private readonly AiBillingSettings $settings,
        private readonly AiWallet $wallet,
        private readonly AiPriceBook $prices,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsData(),
            'models' => $this->modelsData(),
            'wallets' => $this->walletsData(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'markup_multiplier' => ['required', 'numeric', 'min:1', 'max:100'],
            'min_balance_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
            'low_balance_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);
        $this->settings->save([
            'markup_multiplier' => round((float) $data['markup_multiplier'], 4),
            'min_balance_micros' => $this->micros($data['min_balance_usd']),
            'low_balance_micros' => $this->micros($data['low_balance_usd']),
        ]);

        return response()->json(['message' => 'تنظیمات دستیار ذخیره شد.', 'settings' => $this->settingsData(), 'wallets' => $this->walletsData()]);
    }

    public function storePrice(Request $request): JsonResponse
    {
        $models = array_keys($this->modelCatalog());
        $data = $request->validate([
            'model' => ['required', 'string', Rule::in($models)],
            'input_per_m_usd' => ['required', 'numeric', 'min:0', 'max:10000'],
            'cached_input_per_m_usd' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'output_per_m_usd' => ['required', 'numeric', 'min:0', 'max:10000'],
            'audio_input_per_m_usd' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'per_minute_usd' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);
        $kind = $data['model'] === config('assistant.transcription_model') ? 'transcription' : 'chat';
        $total = (float) $data['input_per_m_usd'] + (float) $data['output_per_m_usd'] + (float) ($data['audio_input_per_m_usd'] ?? 0) + (float) ($data['per_minute_usd'] ?? 0);
        if ($total <= 0) throw ValidationException::withMessages(['input_per_m_usd' => 'دست‌کم یکی از نرخ‌ها باید بیشتر از صفر باشد.']);

        DB::connection(config('tenancy.database.central_connection'))->table('central_ai_model_prices')->insert([
            'model' => $data['model'],
            'kind' => $kind,
            'input_per_m' => $this->micros($data['input_per_m_usd']),
            'cached_input_per_m' => isset($data['cached_input_per_m_usd']) ? $this->micros($data['cached_input_per_m_usd']) : null,
            'output_per_m' => $this->micros($data['output_per_m_usd']),
            'audio_input_per_m' => isset($data['audio_input_per_m_usd']) ? $this->micros($data['audio_input_per_m_usd']) : null,
            'per_minute' => isset($data['per_minute_usd']) ? $this->micros($data['per_minute_usd']) : null,
            'effective_from' => now(),
            'central_admin_id' => Auth::guard('central')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'نرخ تازه ثبت شد و از همین حالا اعمال می‌شود.', 'models' => $this->modelsData()]);
    }

    public function updateWallet(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate(['markup_multiplier' => ['nullable', 'numeric', 'min:1', 'max:100']]);
        $this->wallet->state($tenant->getTenantKey());
        DB::connection(config('tenancy.database.central_connection'))->table('central_ai_wallets')
            ->where('tenant_id', $tenant->getTenantKey())
            ->update(['markup_multiplier' => isset($data['markup_multiplier']) ? round((float) $data['markup_multiplier'], 4) : null, 'updated_at' => now()]);

        return response()->json(['message' => 'ضریب سود این سایت ذخیره شد.', 'wallets' => $this->walletsData()]);
    }

    public function charge(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'paid_toman' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->wallet->charge($tenant->getTenantKey(), $this->micros($data['amount_usd']), Auth::guard('central')->id(),
            $data['paid_toman'] ?? null, $data['reference'] ?? null, $data['note'] ?? null);

        return response()->json(['message' => 'موجودی سایت شارژ شد.', 'wallets' => $this->walletsData()]);
    }

    public function adjust(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'between:-100000,100000', 'not_in:0'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        try {
            $this->wallet->adjust($tenant->getTenantKey(), (int) round(((float) $data['amount_usd']) * 1000000), Auth::guard('central')->id(), $data['note']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount_usd' => $exception->getMessage()]);
        }

        return response()->json(['message' => 'اصلاح موجودی ثبت شد.', 'wallets' => $this->walletsData()]);
    }

    public function transactions(Tenant $tenant): JsonResponse
    {
        $rows = DB::connection(config('tenancy.database.central_connection'))->table('central_ai_wallet_transactions')
            ->where('tenant_id', $tenant->getTenantKey())->where('type', '!=', 'usage')->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => collect($rows->items())->map(fn ($row) => [
                'id' => (int) $row->id, 'type' => $row->type,
                'type_label' => ['charge' => 'شارژ', 'adjustment' => 'اصلاح', 'refund' => 'برگشت'][$row->type] ?? $row->type,
                'amount_usd' => AiUsageReport::usd((int) $row->amount_micros),
                'balance_after_usd' => AiUsageReport::usd((int) $row->balance_after_micros),
                'paid_toman' => $row->paid_toman !== null ? (int) $row->paid_toman : null,
                'reference' => $row->reference, 'note' => $row->note, 'created_at' => (string) $row->created_at,
            ])->all(),
            'meta' => ['page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $report = AiUsageReport::central();
        $filters = AiUsageFilters::fromRequest($request, true);

        return response()->json([
            'periods' => $report->periods(array_intersect_key($filters, ['tenant_id' => true])),
            'range' => $report->totals($filters),
            'can_view_all' => true,
        ]);
    }

    public function grouped(Request $request): JsonResponse
    {
        $filters = AiUsageFilters::fromRequest($request, true);
        [$groupBy, $thenBy] = AiUsageFilters::grouping($request, true);

        return response()->json(AiUsageReport::central()->grouped($filters, $groupBy, $thenBy));
    }

    public function requests(Request $request): JsonResponse
    {
        return response()->json(AiUsageReport::central()->requests(AiUsageFilters::fromRequest($request, true), max(1, (int) $request->query('page', 1))));
    }

    public function options(Request $request): JsonResponse
    {
        $filters = AiUsageFilters::fromRequest($request, true);

        return response()->json([
            ...AiUsageReport::central()->filterOptions(array_intersect_key($filters, ['tenant_id' => true])),
            'tenants' => Tenant::query()->orderBy('id')->get()->map(fn (Tenant $tenant) => ['id' => $tenant->getTenantKey(), 'name' => (string) ($tenant->name ?? $tenant->getTenantKey())])->all(),
            'can_view_all' => true,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return AiUsageController::csv(AiUsageReport::central(), AiUsageFilters::fromRequest($request, true),
            'central-ai-usage-'.now('Asia/Tehran')->format('Ymd-His').'.csv', true);
    }

    private function settingsData(): array
    {
        $settings = $this->settings->current();

        return [
            'markup_multiplier' => (float) $settings->markup_multiplier,
            'min_balance_usd' => AiUsageReport::usd((int) $settings->min_balance_micros),
            'low_balance_usd' => AiUsageReport::usd((int) $settings->low_balance_micros),
        ];
    }

    private function modelCatalog(): array
    {
        return [
            ...config('assistant.models', []),
            (string) config('assistant.transcription_model') => 'رونویسی صوت',
        ];
    }

    private function modelsData(): array
    {
        $history = DB::connection(config('tenancy.database.central_connection'))->table('central_ai_model_prices')
            ->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('model');
        $price = fn ($row) => $row ? [
            'id' => (int) $row->id,
            'input_per_m_usd' => AiUsageReport::usd((int) $row->input_per_m),
            'cached_input_per_m_usd' => $row->cached_input_per_m !== null ? AiUsageReport::usd((int) $row->cached_input_per_m) : null,
            'output_per_m_usd' => AiUsageReport::usd((int) $row->output_per_m),
            'audio_input_per_m_usd' => $row->audio_input_per_m !== null ? AiUsageReport::usd((int) $row->audio_input_per_m) : null,
            'per_minute_usd' => $row->per_minute !== null ? AiUsageReport::usd((int) $row->per_minute) : null,
            'effective_from' => (string) $row->effective_from,
        ] : null;

        return collect($this->modelCatalog())->map(fn ($label, $model) => [
            'id' => $model,
            'label' => $label,
            'kind' => $model === config('assistant.transcription_model') ? 'transcription' : 'chat',
            'current' => $price($this->prices->current($model)),
            'history' => ($history[$model] ?? collect())->take(10)->map($price)->values()->all(),
        ])->values()->all();
    }

    private function walletsData(): array
    {
        $central = DB::connection(config('tenancy.database.central_connection'));
        $globalMultiplier = (float) $this->settings->current()->markup_multiplier;
        $usage = $central->table('central_ai_usage')->where('created_at', '>=', now()->subDays(30))
            ->select('tenant_id', DB::raw('SUM(charge_micros) as charge'), DB::raw('SUM(cost_micros) as cost'))
            ->groupBy('tenant_id')->get()->keyBy('tenant_id');

        return Tenant::query()->orderBy('id')->get()->map(function (Tenant $tenant) use ($globalMultiplier, $usage) {
            $id = (string) $tenant->getTenantKey();
            $state = $this->wallet->state($id);
            $override = DB::connection(config('tenancy.database.central_connection'))->table('central_ai_wallets')->where('tenant_id', $id)->value('markup_multiplier');

            return [
                'tenant_id' => $id,
                'name' => (string) ($tenant->name ?? $id),
                'ai_enabled' => $tenant->assistantEnabled(),
                'balance_usd' => AiUsageReport::usd($state['balance_micros']),
                'held_usd' => AiUsageReport::usd($state['held_micros']),
                'blocked' => $state['blocked'],
                'low' => $state['low'],
                'markup_multiplier' => $override !== null ? (float) $override : null,
                'effective_multiplier' => $override !== null ? (float) $override : $globalMultiplier,
                'charge_30d_usd' => AiUsageReport::usd((int) ($usage[$id]->charge ?? 0)),
                'cost_30d_usd' => AiUsageReport::usd((int) ($usage[$id]->cost ?? 0)),
            ];
        })->all();
    }

    private function micros(mixed $usd): int
    {
        return (int) round(((float) $usd) * 1000000);
    }
}
