<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * درخت پویای بخش‌ها/خدمات برای پیگیری کلی — همان منبع نوبت‌دهی جدولی.
 */
class FollowupSectionTree
{
    public const UNKNOWN_PATH = 'مسیر نامشخص';

    /** @var Collection<int, InventorySection>|null */
    private ?Collection $sectionsById = null;

    /**
     * @return array{tree: list<array<string, mixed>>, sections: list<array<string, mixed>>, services: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        if (! Schema::hasTable('inventory_sections')) {
            return ['tree' => [], 'sections' => [], 'services' => []];
        }

        $sections = InventorySection::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'level', 'name', 'sort_order']);

        $services = Schema::hasTable('inventories')
            ? Inventory::query()
                ->where('active', true)
                ->whereNotNull('section_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'section_id', 'name', 'sort_order'])
            : collect();

        $byParent = $sections->groupBy(fn (InventorySection $section) => $section->parent_id === null ? 0 : (int) $section->parent_id);
        $servicesBySection = $services->groupBy(fn (Inventory $item) => (int) $item->section_id);

        $flatSections = [];
        $flatServices = [];
        $tree = $this->buildNodes(
            $byParent,
            $servicesBySection,
            0,
            [],
            [],
            $flatSections,
            $flatServices,
        );

        return [
            'tree' => $tree,
            'sections' => array_values($flatSections),
            'services' => array_values($flatServices),
        ];
    }

    /**
     * @param  Collection<int|string, Collection<int, InventorySection>>  $byParent
     * @param  Collection<int|string, Collection<int, Inventory>>  $servicesBySection
     * @param  list<int>  $ancestorIds
     * @param  list<string>  $ancestorNames
     * @param  array<int, array<string, mixed>>  $flatSections
     * @param  array<int, array<string, mixed>>  $flatServices
     * @return list<array<string, mixed>>
     */
    private function buildNodes(
        Collection $byParent,
        Collection $servicesBySection,
        int $parentKey,
        array $ancestorIds,
        array $ancestorNames,
        array &$flatSections,
        array &$flatServices,
    ): array {
        $children = $byParent->get($parentKey, collect());
        $nodes = [];

        foreach ($children as $section) {
            $id = (int) $section->id;
            $name = trim((string) $section->name);
            $pathIds = [...$ancestorIds, $id];
            $pathNames = [...$ancestorNames, $name];
            $path = implode(' / ', array_filter($pathNames, fn ($part) => $part !== ''));
            $childNodes = $this->buildNodes(
                $byParent,
                $servicesBySection,
                $id,
                $pathIds,
                $pathNames,
                $flatSections,
                $flatServices,
            );

            $sectionServices = [];
            foreach ($servicesBySection->get($id, collect()) as $service) {
                $servicePayload = [
                    'id' => (int) $service->id,
                    'name' => trim((string) $service->name),
                    'section_id' => $id,
                    'path' => $path === '' ? trim((string) $service->name) : $path.' / '.trim((string) $service->name),
                    'path_ids' => $pathIds,
                ];
                $sectionServices[] = $servicePayload;
                $flatServices[(int) $service->id] = $servicePayload;
            }

            $node = [
                'id' => $id,
                'name' => $name,
                'parent_id' => $section->parent_id === null ? null : (int) $section->parent_id,
                'level' => (int) ($section->level ?: max(1, count($pathIds))),
                'path' => $path,
                'path_ids' => $pathIds,
                'is_leaf' => $childNodes === [],
                'children' => $childNodes,
                'services' => $sectionServices,
            ];
            $nodes[] = $node;
            $flatSections[$id] = [
                'id' => $id,
                'name' => $name,
                'parent_id' => $node['parent_id'],
                'level' => $node['level'],
                'path' => $path,
                'path_ids' => $pathIds,
                'is_leaf' => $node['is_leaf'],
            ];
        }

        return $nodes;
    }

    /**
     * شناسهٔ بخش انتخاب‌شده + همهٔ نسل‌ها (برای فیلتر ancestor).
     *
     * @return list<int>
     */
    public function descendantSectionIds(int $sectionId): array
    {
        if (! Schema::hasTable('inventory_sections')) {
            return [$sectionId];
        }

        $sections = InventorySection::query()->get(['id', 'parent_id']);
        $children = [];
        foreach ($sections as $section) {
            $parent = $section->parent_id === null ? null : (int) $section->parent_id;
            if ($parent === null) {
                continue;
            }
            $children[$parent][] = (int) $section->id;
        }

        $ids = [];
        $stack = [$sectionId];
        $seen = [];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $ids[] = $current;
            foreach ($children[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * تکمیل مسیر از subsectionId / نام قدیمی.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function enrichLeadRow(array $row): array
    {
        $hasPath = is_array($row['sectionPathIds'] ?? null) && $row['sectionPathIds'] !== [];
        if ($hasPath && ! empty($row['sectionPath'])) {
            return $row;
        }

        $sections = $this->sectionsById();
        $subsectionId = isset($row['subsectionId']) && $row['subsectionId'] !== '' && $row['subsectionId'] !== null
            ? (int) $row['subsectionId']
            : null;

        if ($subsectionId && $sections->has($subsectionId)) {
            return array_merge($row, $this->pathFieldsForSection($sections, $subsectionId, $row));
        }

        $name = trim((string) ($row['subsectionName'] ?? ''));
        if ($name === '') {
            return $row;
        }

        $leaf = $this->leafLabel($name);
        $matches = $sections->filter(function (InventorySection $section) use ($leaf, $name): bool {
            $sectionName = trim((string) $section->name);

            return $sectionName === $name || $sectionName === $leaf || $this->leafLabel($sectionName) === $leaf;
        });

        if ($matches->count() === 1) {
            return array_merge($row, $this->pathFieldsForSection($sections, (int) $matches->first()->id, $row));
        }

        if ($matches->count() > 1 || $name !== '') {
            $row['sectionPath'] = self::UNKNOWN_PATH;
            $row['sectionPathIds'] = [];
            if (empty($row['rootSectionId'])) {
                $row['rootSectionId'] = null;
            }
        }

        return $row;
    }

    /**
     * @return Collection<int, InventorySection>
     */
    private function sectionsById(): Collection
    {
        if ($this->sectionsById === null) {
            $this->sectionsById = Schema::hasTable('inventory_sections')
                ? InventorySection::query()->get(['id', 'parent_id', 'name'])->keyBy('id')
                : collect();
        }

        return $this->sectionsById;
    }

    /**
     * @param  Collection<int, InventorySection>  $sections
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function pathFieldsForSection(Collection $sections, int $sectionId, array $row): array
    {
        $pathIds = [];
        $names = [];
        $current = $sections->get($sectionId);
        $guard = 0;
        while ($current && $guard < 40) {
            array_unshift($pathIds, (int) $current->id);
            array_unshift($names, trim((string) $current->name));
            $parentId = $current->parent_id === null ? null : (int) $current->parent_id;
            $current = $parentId ? $sections->get($parentId) : null;
            $guard++;
        }

        $serviceId = isset($row['serviceId']) && $row['serviceId'] !== '' && $row['serviceId'] !== null
            ? (int) $row['serviceId']
            : null;
        $serviceName = trim((string) ($row['serviceName'] ?? ''));
        $path = implode(' / ', array_filter($names));
        if ($serviceName !== '') {
            $path = $path === '' ? $serviceName : $path.' / '.$serviceName;
        }

        return [
            'rootSectionId' => $pathIds[0] ?? null,
            'sectionPathIds' => $pathIds,
            'sectionPath' => $path,
            'subsectionId' => $sectionId,
            'subsectionName' => $names === [] ? ($row['subsectionName'] ?? '') : $names[count($names) - 1],
            'serviceId' => $serviceId,
            'serviceName' => $serviceName !== '' ? $serviceName : ($row['serviceName'] ?? ''),
        ];
    }

    private function leafLabel(string $value): string
    {
        $text = str_replace(['ي', 'ك'], ['ی', 'ک'], trim($value));
        $parts = preg_split('/\s*(?:←|→|->|\/|‹|›)\s*/u', $text) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return $parts === [] ? $text : (string) end($parts);
    }
}
