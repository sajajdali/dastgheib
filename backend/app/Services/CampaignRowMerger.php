<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * ادغام سه‌طرفهٔ ردیف‌های کمپین (سرنخ‌ها) بر اساس _localId.
 *
 * base   = ردیف‌هایی که کاربر آخرین بار از سرور گرفته است،
 * theirs = ردیف‌های فعلی سرور (ممکن است کاربر دیگری تغییرشان داده باشد)،
 * mine   = ردیف‌هایی که این کاربر می‌فرستد.
 *
 * تغییر هر کاربر فقط روی فیلدهایی اعمال می‌شود که خودش عوض کرده است؛
 * فقط وقتی هر دو طرف یک فیلد از یک ردیف را متفاوت تغییر داده باشند، تداخل گزارش می‌شود.
 */
class CampaignRowMerger
{
    /**
     * @return array{rows: array<int, array>, conflicts: array<int, array{row_id: string, fields: array<int, string>}>}
     */
    public function merge(array $base, array $theirs, array $mine): array
    {
        $baseById = $this->index($base);
        $theirsById = $this->index($theirs);
        $mine = array_map(fn ($row) => $this->withId($row), array_values(array_filter($mine, 'is_array')));
        $mineById = $this->index($mine);

        $result = [];
        $conflicts = [];

        foreach ($mine as $row) {
            $id = $row['_localId'];
            $baseRow = $baseById[$id] ?? null;
            $theirRow = $theirsById[$id] ?? null;

            if ($theirRow === null) {
                // ردیف جدید این کاربر، یا ردیفی که دیگری حذف کرده است.
                if ($baseRow !== null && $this->same($row, $baseRow)) continue;
                $result[] = $row;
                continue;
            }

            [$merged, $conflictFields] = $this->mergeRow($baseRow ?? $theirRow, $theirRow, $row);
            if ($conflictFields) $conflicts[] = ['row_id' => (string) $id, 'fields' => $conflictFields];
            $result[] = $merged;
        }

        // ردیف‌هایی که فقط روی سرور هستند.
        $previousId = null;
        foreach ($theirs as $row) {
            if (! is_array($row) || ! isset($row['_localId'])) continue;
            $id = (string) $row['_localId'];
            if (isset($mineById[$id])) {
                $previousId = $id;
                continue;
            }
            $baseRow = $baseById[$id] ?? null;
            // این کاربر حذفش کرده و دیگری هم دست به آن نزده است.
            if ($baseRow !== null && $this->same($row, $baseRow)) continue;

            $position = $previousId === null ? 0 : $this->positionAfter($result, $previousId);
            array_splice($result, $position, 0, [$row]);
            $previousId = $id;
        }

        return ['rows' => array_values($result), 'conflicts' => $conflicts];
    }

    /** هر ردیف بدون شناسه یک شناسهٔ پایدار می‌گیرد تا ادغام‌های بعدی ممکن باشد. */
    public function withIds(array $rows): array
    {
        return array_map(fn ($row) => is_array($row) ? $this->withId($row) : $row, $rows);
    }

    private function withId(array $row): array
    {
        if (! isset($row['_localId']) || trim((string) $row['_localId']) === '') {
            $row['_localId'] = 'srv-'.Str::uuid()->toString();
        }
        $row['_localId'] = (string) $row['_localId'];

        return $row;
    }

    private function mergeRow(array $base, array $theirs, array $mine): array
    {
        $merged = [];
        $conflicts = [];
        $keys = array_unique(array_merge(array_keys($base), array_keys($theirs), array_keys($mine)));

        foreach ($keys as $key) {
            $baseValue = $base[$key] ?? null;
            $theirValue = $theirs[$key] ?? null;
            $myValue = $mine[$key] ?? null;

            if ($this->same($myValue, $baseValue)) {
                $value = $theirValue;
            } elseif ($this->same($theirValue, $baseValue) || $this->same($myValue, $theirValue)) {
                $value = $myValue;
            } else {
                $conflicts[] = (string) $key;
                $value = $theirValue;
            }

            if (array_key_exists($key, $mine) || array_key_exists($key, $theirs)) {
                $merged[$key] = $value;
            }
        }

        return [$merged, $conflicts];
    }

    private function index(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['_localId']) && trim((string) $row['_localId']) !== '') {
                $byId[(string) $row['_localId']] = $row;
            }
        }

        return $byId;
    }

    private function positionAfter(array $rows, string $id): int
    {
        foreach ($rows as $index => $row) {
            if ((string) ($row['_localId'] ?? '') === $id) return $index + 1;
        }

        return count($rows);
    }

    /** مقایسه بدون حساسیت به ""/null، فاصلهٔ اضافه، نوع عدد/رشته و ترتیب کلیدها. */
    private function same(mixed $left, mixed $right): bool
    {
        return $this->normalize($left) === $this->normalize($right);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($value === []) return null;
            $normalized = [];
            foreach ($value as $key => $item) $normalized[$key] = $this->normalize($item);
            if (! array_is_list($normalized)) ksort($normalized);

            return $normalized;
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }
        if (is_int($value) || is_float($value)) return (string) $value;

        return $value;
    }
}
