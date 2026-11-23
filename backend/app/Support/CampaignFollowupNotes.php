<?php

namespace App\Support;

/**
 * یادداشت‌های چندتایی توضیحات ردیف پیگیری کمپین.
 * فیلد description برای سازگاری و ستون جدول همیشه متن آخرین یادداشت است.
 */
class CampaignFollowupNotes
{
    public static function normalizeRow(array $row, ?string $fallbackAuthor = null): array
    {
        $seed = trim((string) ($row['_localId'] ?? $row['rowId'] ?? ''));
        $notes = self::normalizeNotes($row['notes'] ?? null, $row['description'] ?? null, $fallbackAuthor, $seed);
        $row['notes'] = $notes;
        $row['description'] = self::latestText($notes);

        return $row;
    }

    /**
     * @param  array<int, mixed>|null  $notes
     */
    public static function normalizeNotes(mixed $notes, mixed $legacyDescription = null, ?string $fallbackAuthor = null, string $seed = ''): array
    {
        $normalized = [];
        if (is_array($notes)) {
            foreach ($notes as $note) {
                if (! is_array($note)) continue;
                $text = trim((string) ($note['text'] ?? ''));
                if ($text === '') continue;
                $id = trim((string) ($note['id'] ?? ''));
                $createdAt = self::timestamp($note['created_at'] ?? null) ?? now()->toIso8601String();
                $updatedAt = self::timestamp($note['updated_at'] ?? null) ?? $createdAt;
                $author = trim((string) ($note['author'] ?? $fallbackAuthor ?? ''));
                $normalized[] = [
                    'id' => $id !== '' ? $id : self::stableId($seed, $text, 'missing'),
                    'text' => $text,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    'author' => $author !== '' ? $author : null,
                ];
            }
        }

        if ($normalized === []) {
            $legacy = trim((string) ($legacyDescription ?? ''));
            if ($legacy !== '') {
                // زمان ثابت تا خواندن مکرر بدون ذخیره، اثر انگشت ردیف را عوض نکند.
                $createdAt = '1970-01-01T00:00:00+00:00';
                $normalized[] = [
                    'id' => self::stableId($seed, $legacy, 'legacy'),
                    'text' => $legacy,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'author' => $fallbackAuthor ? trim($fallbackAuthor) ?: null : null,
                ];
            }
        }

        usort($normalized, function (array $a, array $b): int {
            return strcmp((string) $a['created_at'], (string) $b['created_at'])
                ?: strcmp((string) $a['id'], (string) $b['id']);
        });

        return array_values($normalized);
    }

    /** @param  array<int, array{text?: string}>  $notes */
    public static function latestText(array $notes): string
    {
        if ($notes === []) return '';
        $last = $notes[array_key_last($notes)];

        return trim((string) ($last['text'] ?? ''));
    }

    public static function normalizePayloadRows(mixed $rows, ?string $fallbackAuthor = null): array
    {
        if (! is_array($rows)) return [];

        return array_map(
            fn ($row) => is_array($row) ? self::normalizeRow($row, $fallbackAuthor) : $row,
            array_values($rows)
        );
    }

    private static function timestamp(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;

        try {
            return \Carbon\Carbon::parse($text)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function stableId(string $seed, string $text, string $kind): string
    {
        $basis = ($seed !== '' ? $seed : 'row').'|'.$kind.'|'.$text;

        return 'note-'.substr(hash('sha256', $basis), 0, 24);
    }
}
