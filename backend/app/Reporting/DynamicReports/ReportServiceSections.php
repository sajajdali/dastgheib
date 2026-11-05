<?php

namespace App\Reporting\DynamicReports;

use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * بخش و زیر بخش خدمات از درخت بخش‌های انبار. نوبت‌های جدید فقط section_id ذخیره می‌کنند و
 * نوبت‌های قدیمی نام بخش و زیر بخش را؛ گزارش‌ساز هر دو را می‌خواند.
 */
final class ReportServiceSections
{
    private ?Collection $sections = null;

    public function rootNames(): array
    {
        return $this->all()->whereNull('parent_id')->sortBy('id')->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
    }

    public function childNames(): array
    {
        return $this->all()->whereNotNull('parent_id')->sortBy('id')->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
    }

    /** شناسهٔ بخش‌هایی که نامشان انتخاب شده، به‌اضافهٔ همهٔ زیرمجموعه‌هایشان. */
    public function idsForNames(array $names): array
    {
        $names = collect($names)->map(fn ($name) => trim((string) $name))->filter();
        $ids = $this->all()->filter(fn ($section) => $names->contains(trim((string) $section->name)))->pluck('id');
        $all = $ids;
        while ($ids->isNotEmpty()) {
            $ids = $this->all()->whereIn('parent_id', $ids->all())->pluck('id')->diff($all);
            $all = $all->merge($ids);
        }

        return $all->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /** نام بخش اصلی و زیر بخش هر خدمت نوبت. */
    public function namesForServices(mixed $services): array
    {
        $roots = [];
        $children = [];
        foreach (is_array($services) ? $services : [] as $service) {
            if (! is_array($service)) continue;
            // بدون section_id، بخش خدمت از همان کالای انبار خوانده می‌شود.
            $section = $this->find($service['section_id'] ?? $service['sectionId'] ?? null)
                ?? $this->find($this->inventorySections()[trim((string) ($service['name'] ?? ''))] ?? null);
            if ($section) {
                $root = $section;
                for ($guard = 0; $root->parent_id && $guard < 10; $guard++) $root = $this->find($root->parent_id) ?? $root;
                $roots[] = trim((string) $root->name);
                if ($section->parent_id) $children[] = trim((string) $section->name);
                continue;
            }
            // در نوبت‌های قدیمی «subsection» گروه اصلی و «section» زیر بخش است.
            if (filled($service['subsection'] ?? null)) $roots[] = trim((string) $service['subsection']);
            if (filled($service['section'] ?? null)) $children[] = trim((string) $service['section']);
        }

        return [
            'roots' => array_values(array_unique(array_filter($roots))),
            'children' => array_values(array_unique(array_filter($children))),
        ];
    }

    private function find(mixed $id): ?object
    {
        return is_numeric($id) ? $this->all()->firstWhere('id', (int) $id) : null;
    }

    private ?array $inventorySections = null;

    private function inventorySections(): array
    {
        return $this->inventorySections ??= Schema::hasTable('inventories')
            ? Inventory::query()->whereNotNull('section_id')->pluck('section_id', 'name')->all()
            : [];
    }

    private function all(): Collection
    {
        return $this->sections ??= Schema::hasTable('inventory_sections')
            ? InventorySection::query()->get(['id', 'parent_id', 'name'])
            : collect();
    }
}
