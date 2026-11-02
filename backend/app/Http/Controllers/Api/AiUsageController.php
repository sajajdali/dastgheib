<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Assistant\Billing\AiUsageFilters;
use App\Services\Assistant\Billing\AiUsageReport;
use App\Services\Assistant\Billing\AiWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** گزارش مصرف دستیار در سایت کلینیک. کاربر بدون assistant.usage.view فقط مصرف خودش را می‌بیند. */
final class AiUsageController extends Controller
{
    public function balance(Request $request, AiWallet $wallet): JsonResponse
    {
        return response()->json(self::balanceData($request, $wallet));
    }

    public function summary(Request $request, AiUsageReport $report): JsonResponse
    {
        $filters = $this->scope($request, AiUsageFilters::fromRequest($request));

        return response()->json([
            'periods' => $report->periods($this->scope($request, [])),
            'range' => $report->totals($filters),
            'can_view_all' => $this->canViewAll($request),
        ]);
    }

    public function grouped(Request $request, AiUsageReport $report): JsonResponse
    {
        $filters = $this->scope($request, AiUsageFilters::fromRequest($request));
        [$groupBy, $thenBy] = AiUsageFilters::grouping($request);

        return response()->json($report->grouped($filters, $groupBy, $thenBy));
    }

    public function requests(Request $request, AiUsageReport $report): JsonResponse
    {
        $filters = $this->scope($request, AiUsageFilters::fromRequest($request));

        return response()->json($report->requests($filters, max(1, (int) $request->query('page', 1))));
    }

    public function options(Request $request, AiUsageReport $report): JsonResponse
    {
        return response()->json([...$report->filterOptions($this->scope($request, [])), 'can_view_all' => $this->canViewAll($request)]);
    }

    public function export(Request $request, AiUsageReport $report): StreamedResponse
    {
        $filters = $this->scope($request, AiUsageFilters::fromRequest($request));

        return self::csv($report, $filters, 'ai-usage-'.now('Asia/Tehran')->format('Ymd-His').'.csv', false);
    }

    public function transactions(Request $request): JsonResponse
    {
        abort_unless($this->canViewAll($request), 403, 'دسترسی مشاهدهٔ تراکنش‌های موجودی را ندارید.');
        $rows = DB::connection(config('tenancy.database.central_connection'))->table('central_ai_wallet_transactions')
            ->where('tenant_id', tenant()->getTenantKey())->where('type', '!=', 'usage')
            ->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => collect($rows->items())->map(fn ($row) => [
                'id' => (int) $row->id, 'type' => $row->type,
                'type_label' => ['charge' => 'شارژ', 'adjustment' => 'اصلاح', 'refund' => 'برگشت'][$row->type] ?? $row->type,
                'amount_usd' => AiUsageReport::usd((int) $row->amount_micros),
                'balance_after_usd' => AiUsageReport::usd((int) $row->balance_after_micros),
                'reference' => $row->reference, 'note' => $row->note, 'created_at' => (string) $row->created_at,
            ])->all(),
            'meta' => ['page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public static function balanceData(Request $request, AiWallet $wallet): array
    {
        $state = $wallet->state((string) tenant()->getTenantKey());
        $days = null;
        $weekly = (int) DB::connection(config('tenancy.database.central_connection'))->table('central_ai_usage')
            ->where('tenant_id', tenant()->getTenantKey())->where('created_at', '>=', now()->subDays(7))->sum('charge_micros');
        if ($weekly > 0) $days = (int) floor($state['balance_micros'] / ($weekly / 7));

        return [
            'balance_usd' => AiUsageReport::usd($state['balance_micros']),
            'available_usd' => AiUsageReport::usd($state['available_micros']),
            'min_balance_usd' => AiUsageReport::usd($state['min_balance_micros']),
            'low_balance_usd' => AiUsageReport::usd($state['low_balance_micros']),
            'blocked' => $state['blocked'],
            'low' => $state['low'],
            'days_left_estimate' => $days,
            'can_manage' => $request->user()->can('assistant.manage'),
        ];
    }

    public static function csv(AiUsageReport $report, array $filters, string $filename, bool $central): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $filters, $central): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            $head = ['شناسه', 'زمان', 'تاریخ شمسی', 'کاربر', 'مدل', 'نوع', 'دور', 'شناسهٔ پرسش', 'گفت‌وگو', 'توکن ورودی', 'ورودی cache', 'توکن خروجی', 'reasoning', 'توکن صوت', 'ثانیهٔ صوت', 'هزینه (دلار)', 'وضعیت', 'تخمینی'];
            if ($central) array_push($head, 'کلینیک', 'قیمت تمام‌شده (دلار)', 'ضریب', 'وصول‌نشده (دلار)');
            fputcsv($out, $head);
            $report->each($filters, function (array $row) use ($out, $central): void {
                $line = [$row['id'], $row['created_at'], $row['jalali_date'], $row['user_name'], $row['model'], $row['type_label'], $row['round'],
                    $row['request_group'], $row['conversation_id'], $row['input_tokens'], $row['cached_input_tokens'], $row['output_tokens'],
                    $row['reasoning_tokens'], $row['audio_input_tokens'], $row['audio_seconds'], number_format($row['charge_usd'], 6, '.', ''),
                    $row['status_label'], $row['estimated'] ? 'بله' : 'خیر'];
                if ($central) array_push($line, $row['tenant_id'], number_format($row['cost_usd'], 6, '.', ''), $row['markup_multiplier'], number_format($row['uncollected_usd'], 6, '.', ''));
                fputcsv($out, $line);
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function scope(Request $request, array $filters): array
    {
        $filters['tenant_id'] = (string) tenant()->getTenantKey();
        if (! $this->canViewAll($request)) {
            unset($filters['user_ids']);
            $filters['user_id'] = $request->user()->id;
        }

        return $filters;
    }

    private function canViewAll(Request $request): bool
    {
        return $request->user()->can('assistant.usage.view');
    }
}
