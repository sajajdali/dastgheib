<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportBuilderTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportBuilderTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = ReportBuilderTemplate::query()
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ReportBuilderTemplate $template) => $template->toFrontend())
            ->values();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedTemplate($request);
        $fingerprint = ReportBuilderTemplate::fingerprintFor($data['name'], $data['source'], $data['columns']);

        if (! empty($data['client_key'])) {
            $existing = ReportBuilderTemplate::query()->where('client_key', $data['client_key'])->first();
            if ($existing) {
                return response()->json(['template' => $existing->toFrontend(), 'deduped' => true]);
            }
        }

        $existing = ReportBuilderTemplate::query()->where('fingerprint', $fingerprint)->first();
        if ($existing) {
            return response()->json(['template' => $existing->toFrontend(), 'deduped' => true]);
        }

        $template = ReportBuilderTemplate::query()->create([
            'name' => $data['name'],
            'source' => $data['source'],
            'columns' => $data['columns'],
            'created_by' => $request->user()?->id,
            'creator_name' => $request->user()?->name ?: 'سیستم',
            'client_key' => $data['client_key'] ?? null,
            'fingerprint' => $fingerprint,
        ]);

        return response()->json(['template' => $template->toFrontend()], 201);
    }

    public function update(Request $request, ReportBuilderTemplate $reportBuilderTemplate): JsonResponse
    {
        $data = $this->validatedTemplate($request, requireClientKey: false);
        $fingerprint = ReportBuilderTemplate::fingerprintFor($data['name'], $data['source'], $data['columns']);

        $duplicate = ReportBuilderTemplate::query()
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $reportBuilderTemplate->id)
            ->exists();
        abort_if($duplicate, 422, 'قالبی با همین نام، منبع و ستون‌ها از قبل وجود دارد.');

        $reportBuilderTemplate->update([
            'name' => $data['name'],
            'source' => $data['source'],
            'columns' => $data['columns'],
            'fingerprint' => $fingerprint,
        ]);

        return response()->json(['template' => $reportBuilderTemplate->fresh()->toFrontend()]);
    }

    public function destroy(ReportBuilderTemplate $reportBuilderTemplate): JsonResponse
    {
        $reportBuilderTemplate->delete();

        return response()->json(['success' => true]);
    }

    /**
     * مهاجرت یک‌بارهٔ قالب‌های localStorage با جلوگیری از درج تکراری.
     */
    public function migrate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'templates' => ['required', 'array', 'max:100'],
            'templates.*.client_key' => ['nullable', 'string', 'max:64'],
            'templates.*.name' => ['required', 'string', 'max:120'],
            'templates.*.source' => ['required', 'string', Rule::in(array_keys(config('dynamic_reports.sources', [])))],
            'templates.*.columns' => ['required', 'array', 'min:1', 'max:'.max(1, count(config('dynamic_reports.fields', [])))],
            // بدون distinct سراسری — چند قالب در یک درخواست ممکن است ستون‌های مشترک داشته باشند.
            'templates.*.columns.*' => ['required', 'string', Rule::in(array_keys(config('dynamic_reports.fields', [])))],
        ]);

        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($payload, $request, &$created, &$skipped) {
            foreach ($payload['templates'] as $item) {
                $name = trim((string) $item['name']) ?: 'قالب بدون نام';
                $source = $item['source'];
                $columns = array_values(array_unique($item['columns']));
                $clientKey = isset($item['client_key']) && $item['client_key'] !== ''
                    ? (string) $item['client_key']
                    : null;
                $fingerprint = ReportBuilderTemplate::fingerprintFor($name, $source, $columns);

                if ($clientKey && ReportBuilderTemplate::query()->where('client_key', $clientKey)->exists()) {
                    $skipped++;
                    continue;
                }
                if (ReportBuilderTemplate::query()->where('fingerprint', $fingerprint)->exists()) {
                    $skipped++;
                    continue;
                }

                $template = ReportBuilderTemplate::query()->create([
                    'name' => $name,
                    'source' => $source,
                    'columns' => $columns,
                    'created_by' => $request->user()?->id,
                    'creator_name' => $request->user()?->name ?: 'سیستم',
                    'client_key' => $clientKey,
                    'fingerprint' => $fingerprint,
                ]);
                $created[] = $template->toFrontend();
            }
        });

        $templates = ReportBuilderTemplate::query()
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ReportBuilderTemplate $template) => $template->toFrontend())
            ->values();

        return response()->json([
            'created' => $created,
            'created_count' => count($created),
            'skipped_count' => $skipped,
            'templates' => $templates,
        ]);
    }

    private function validatedTemplate(Request $request, bool $requireClientKey = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'source' => ['required', 'string', Rule::in(array_keys(config('dynamic_reports.sources', [])))],
            'columns' => ['required', 'array', 'min:1', 'max:'.max(1, count(config('dynamic_reports.fields', [])))],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('dynamic_reports.fields', [])))],
            'client_key' => [$requireClientKey ? 'required' : 'nullable', 'string', 'max:64'],
        ];

        $data = $request->validate($rules);
        $data['name'] = trim((string) $data['name']) ?: 'قالب بدون نام';
        $data['columns'] = array_values($data['columns']);

        return $data;
    }
}
