<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomaticSmsScenario;
use App\Models\AutomaticSmsProject;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticSmsScenarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'projects' => AutomaticSmsProject::query()->with(['scenarios.steps'])->latest()->get(),
            'tags' => $this->tags(),
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'is_active' => ['required', 'boolean']]);
        $project = DB::transaction(function () use ($data) {
            if ($data['is_active']) AutomaticSmsProject::query()->where('is_active', true)->update(['is_active' => false]);
            return AutomaticSmsProject::create($data);
        });
        return response()->json(['project' => $project->load('scenarios.steps')], 201);
    }

    public function updateProject(Request $request, AutomaticSmsProject $automaticSmsProject): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'is_active' => ['required', 'boolean']]);

        $project = DB::transaction(function () use ($data, $automaticSmsProject) {
            if ($data['is_active']) AutomaticSmsProject::query()->whereKeyNot($automaticSmsProject->id)->where('is_active', true)->update(['is_active' => false]);
            $automaticSmsProject->update($data);
            return $automaticSmsProject->fresh()->load('scenarios.steps');
        });
        return response()->json(['project' => $project]);
    }

    public function destroyProject(AutomaticSmsProject $automaticSmsProject): JsonResponse
    {
        $automaticSmsProject->delete();
        return response()->json([], 204);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $scenario = DB::transaction(function () use ($data) {
            $scenario = AutomaticSmsScenario::create(collect($data)->except('steps')->all());
            $this->syncSteps($scenario, $data['steps']);
            return $scenario->load('steps');
        });
        return response()->json(['scenario' => $scenario], 201);
    }

    public function update(Request $request, AutomaticSmsScenario $automaticSmsScenario): JsonResponse
    {
        $data = $this->validated($request);
        $scenario = DB::transaction(function () use ($data, $automaticSmsScenario) {
            $automaticSmsScenario->update(collect($data)->except('steps')->all());
            $this->syncSteps($automaticSmsScenario, $data['steps']);
            return $automaticSmsScenario->fresh()->load('steps');
        });
        return response()->json(['scenario' => $scenario]);
    }

    public function destroy(AutomaticSmsScenario $automaticSmsScenario): JsonResponse
    {
        $automaticSmsScenario->delete();
        return response()->json([], 204);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:automatic_sms_projects,id'],
            'name' => ['required', 'string', 'max:120'],
            'inventory_tag' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.days_after' => ['required', 'integer', 'min:1', 'max:3650'],
            'steps.*.send_at' => ['required', 'date_format:H:i'],
            // کاربر باید بتواند کارت سناریوی تازه را مانند طرح ابتدا بسازد و نام الگو را بعداً وارد کند.
            'steps.*.sms_template' => ['nullable', 'string', 'max:120'],
        ]);

        $currentScenario = $request->route('automaticSmsScenario');
        $changesTagOrProject = ! $currentScenario
            || (int) $currentScenario->project_id !== (int) $data['project_id']
            || $currentScenario->inventory_tag !== $data['inventory_tag'];

        if ($changesTagOrProject && AutomaticSmsScenario::query()
            ->where('project_id', $data['project_id'])
            ->where('inventory_tag', $data['inventory_tag'])
            ->when($currentScenario, fn ($query) => $query->whereKeyNot($currentScenario->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'inventory_tag' => ['برای این خدمت، در پروژه انتخاب‌شده قبلاً سناریو تعریف شده است.'],
            ]);
        }

        return $data;
    }

    private function syncSteps(AutomaticSmsScenario $scenario, array $steps): void
    {
        $scenario->steps()->delete();
        foreach (array_values($steps) as $index => $step) {
            $scenario->steps()->create([
                'sort_order' => $index + 1,
                'days_after' => $step['days_after'],
                'send_at' => $step['send_at'],
                'sms_template' => trim($step['sms_template']),
            ]);
        }
    }

    private function tags(): array
    {
        $stored = json_decode((string) AppSetting::getByKey('service_tags', '[]'), true);
        $tags = collect(is_array($stored) ? $stored : [])
            ->map(fn ($tag) => trim((string) (is_array($tag) ? ($tag['name'] ?? '') : $tag)))
            ->filter()->unique()->values()->all();

        return $tags !== []
            ? $tags
            : ['بوتاکس پیشانی', 'بوتاکس دور چشم', 'ژل لب', 'فرم‌دهی لب', 'لیزر صورت', 'لیزر فول فیس'];
    }

}
