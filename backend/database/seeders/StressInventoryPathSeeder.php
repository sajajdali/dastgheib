<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local-only additive stress data for tabular booking «مسیر خدمت» Multiselects.
 *
 * Marker prefix: 〔stress〕 — reversible with:
 *   DELETE FROM inventories WHERE name LIKE '〔stress〕%';
 *   DELETE FROM inventory_sections WHERE name LIKE '〔stress〕%';
 *
 * Does NOT add children under existing inventory-bearing leaves (keeps demo usable).
 * May add sibling branches under existing roots.
 *
 * Run:
 *   php artisan tenants:seed --tenants=demo --class=Database\\Seeders\\StressInventoryPathSeeder
 */
final class StressInventoryPathSeeder extends Seeder
{
    public const MARKER = '〔stress〕';

    public function run(): void
    {
        if (! Schema::hasTable('inventory_sections') || ! Schema::hasTable('inventories')) {
            $this->command?->warn('inventory tables missing; skip.');

            return;
        }

        $removed = $this->purgeMarked();
        if ($removed['sections'] || $removed['inventories']) {
            $this->command?->info("پاک‌سازی قبلی: {$removed['sections']} بخش، {$removed['inventories']} خدمت.");
        }

        $now = now();
        $stats = ['sections' => 0, 'inventories' => 0, 'sample_paths' => []];

        DB::transaction(function () use ($now, &$stats) {
            // جنگل مستقل برای استرس عمیق (۳ ریشه × شاخه‌های پهن و عمیق).
            foreach (['درخت آلفا', 'درخت بتا', 'درخت گاما'] as $rootIndex => $rootLabel) {
                $rootId = $this->insertSection(null, 1, self::MARKER.' '.$rootLabel, 9000 + $rootIndex, $now);
                $stats['sections']++;
                $l2Ids = [];
                for ($i = 1; $i <= 12; $i++) {
                    $l2Ids[] = $this->insertSection($rootId, 2, self::MARKER." {$rootLabel} / زیر‌بخش {$i}", 100 + $i, $now);
                    $stats['sections']++;
                }

                foreach ($l2Ids as $l2Index => $l2Id) {
                    $deep = $l2Index < 5; // ۵ زیر‌بخش اول هر ریشه تا سطح ۴ عمیق می‌شوند
                    for ($j = 1; $j <= 10; $j++) {
                        $l3Id = $this->insertSection($l2Id, 3, self::MARKER." L3-{$rootIndex}-{$l2Index}-{$j}", 200 + $j, $now);
                        $stats['sections']++;

                        if (! $deep) {
                            $stats['inventories'] += $this->insertServices($l3Id, 6, "L3-{$rootIndex}-{$l2Index}-{$j}", $now);
                            if (count($stats['sample_paths']) < 4) {
                                $stats['sample_paths'][] = self::MARKER." {$rootLabel} › زیر‌بخش ".($l2Index + 1)." › L3-{$j} › (۶ خدمت)";
                            }
                            continue;
                        }

                        for ($k = 1; $k <= 8; $k++) {
                            $l4Id = $this->insertSection($l3Id, 4, self::MARKER." L4-{$rootIndex}-{$l2Index}-{$j}-{$k}", 300 + $k, $now);
                            $stats['sections']++;
                            $stats['inventories'] += $this->insertServices($l4Id, 6, "L4-{$rootIndex}-{$l2Index}-{$j}-{$k}", $now);
                            if (count($stats['sample_paths']) < 8 && $j === 1 && $k <= 2) {
                                $stats['sample_paths'][] = self::MARKER." {$rootLabel} › زیر‌بخش ".($l2Index + 1)." › L3-{$j} › L4-{$k} › (۶ خدمت)";
                            }
                        }
                    }
                }
            }

            // زیر شاخه‌های استرس کنار ریشه‌های واقعی کلینیک (بدون دست‌زدن به برگ‌های دارای خدمت).
            $existingRoots = InventorySection::query()
                ->whereNull('parent_id')
                ->where('name', 'not like', self::MARKER.'%')
                ->orderBy('id')
                ->get(['id', 'name']);

            foreach ($existingRoots as $root) {
                for ($i = 1; $i <= 8; $i++) {
                    $l2Id = $this->insertSection(
                        (int) $root->id,
                        2,
                        self::MARKER." شاخه کنار «{$root->name}» #{$i}",
                        8000 + $i,
                        $now
                    );
                    $stats['sections']++;
                    for ($j = 1; $j <= 10; $j++) {
                        $l3Id = $this->insertSection($l2Id, 3, self::MARKER." کنار-{$root->id}-{$i}-{$j}", 8100 + $j, $now);
                        $stats['sections']++;
                        // نیمی عمیق‌تر برای استرس سطح ۴ روی مسیر واقعی
                        if ($j <= 5) {
                            for ($k = 1; $k <= 8; $k++) {
                                $l4Id = $this->insertSection($l3Id, 4, self::MARKER." کنارL4-{$root->id}-{$i}-{$j}-{$k}", 8200 + $k, $now);
                                $stats['sections']++;
                                $stats['inventories'] += $this->insertServices($l4Id, 5, "side-{$root->id}-{$i}-{$j}-{$k}", $now);
                            }
                        } else {
                            $stats['inventories'] += $this->insertServices($l3Id, 5, "side-{$root->id}-{$i}-{$j}", $now);
                        }
                    }
                    if (count($stats['sample_paths']) < 12) {
                        $stats['sample_paths'][] = "{$root->name} › ".self::MARKER." شاخه کنار #{$i} › … › خدمت";
                    }
                }
            }
        });

        $this->command?->info("Stress path seed: {$stats['sections']} sections, {$stats['inventories']} inventories.");
        foreach ($stats['sample_paths'] as $path) {
            $this->command?->line('  · '.$path);
        }
        $this->command?->comment('Rollback: DELETE inventories/sections WHERE name LIKE \''.self::MARKER.'%\'');
    }

    /** @return array{sections:int,inventories:int} */
    public function purgeMarked(): array
    {
        $inventories = 0;
        $sections = 0;
        if (Schema::hasTable('inventories')) {
            $inventories = DB::table('inventories')->where('name', 'like', self::MARKER.'%')->delete();
        }
        if (Schema::hasTable('inventory_sections')) {
            // children first by descending level
            for ($level = 12; $level >= 1; $level--) {
                $sections += DB::table('inventory_sections')
                    ->where('level', $level)
                    ->where('name', 'like', self::MARKER.'%')
                    ->delete();
            }
            $sections += DB::table('inventory_sections')->where('name', 'like', self::MARKER.'%')->delete();
        }

        return ['sections' => $sections, 'inventories' => $inventories];
    }

    private function insertSection(?int $parentId, int $level, string $name, int $sortOrder, $now): int
    {
        return (int) DB::table('inventory_sections')->insertGetId([
            'parent_id' => $parentId,
            'level' => $level,
            'name' => $name,
            'color' => null,
            'photos_required' => 0,
            'commission_type' => 'percent',
            'commission_value' => 0,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertServices(int $sectionId, int $count, string $slug, $now): int
    {
        $rows = [];
        for ($n = 1; $n <= $count; $n++) {
            $rows[] = [
                'section_id' => $sectionId,
                'name' => self::MARKER." خدمت {$slug}-{$n}",
                'service_tags' => json_encode([['صورت', 'گردن', 'دست', 'شکم'][($n - 1) % 4]], JSON_UNESCAPED_UNICODE),
                'amount' => (string) (500000 + ($n * 75000)),
                'price' => null,
                'count' => 0,
                'stock' => null,
                'min_stock' => 5,
                'active' => 1,
                'followup_days' => 0,
                'sort_order' => $n,
                'default_commission_type' => 'percent',
                'default_commission_value' => 0,
                'track_stock' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('inventories')->insert($rows);

        return count($rows);
    }
}
