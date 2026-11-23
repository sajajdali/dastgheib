<?php

namespace Tests\Unit;

use App\Support\CampaignFollowupNotes;
use PHPUnit\Framework\TestCase;

class CampaignFollowupNotesTest extends TestCase
{
    public function test_migrates_legacy_description_into_first_note(): void
    {
        $row = CampaignFollowupNotes::normalizeRow([
            '_localId' => 'a',
            'description' => 'تماس اول',
        ], 'سارا');

        $this->assertCount(1, $row['notes']);
        $this->assertSame('تماس اول', $row['notes'][0]['text']);
        $this->assertSame('تماس اول', $row['description']);
        $this->assertSame('سارا', $row['notes'][0]['author']);
        $this->assertNotEmpty($row['notes'][0]['id']);
        $this->assertNotEmpty($row['notes'][0]['created_at']);

        $again = CampaignFollowupNotes::normalizeRow([
            '_localId' => 'a',
            'description' => 'تماس اول',
        ]);
        $this->assertSame($row['notes'][0]['id'], $again['notes'][0]['id']);
    }

    public function test_description_tracks_latest_note(): void
    {
        $row = CampaignFollowupNotes::normalizeRow([
            'description' => 'قدیمی',
            'notes' => [
                ['id' => 'n1', 'text' => 'اول', 'created_at' => '2026-11-01T10:00:00+00:00'],
                ['id' => 'n2', 'text' => 'دوم', 'created_at' => '2026-11-02T10:00:00+00:00'],
            ],
        ]);

        $this->assertSame(['اول', 'دوم'], array_column($row['notes'], 'text'));
        $this->assertSame('دوم', $row['description']);
    }

    public function test_empty_notes_clear_description(): void
    {
        $row = CampaignFollowupNotes::normalizeRow([
            'description' => '',
            'notes' => [
                ['id' => 'n1', 'text' => '   '],
            ],
        ]);

        $this->assertSame([], $row['notes']);
        $this->assertSame('', $row['description']);
    }

    public function test_sorts_notes_by_created_at(): void
    {
        $row = CampaignFollowupNotes::normalizeRow([
            'notes' => [
                ['id' => 'later', 'text' => 'بعدی', 'created_at' => '2026-11-10T12:00:00+00:00'],
                ['id' => 'earlier', 'text' => 'قبلی', 'created_at' => '2026-11-01T12:00:00+00:00'],
            ],
        ]);

        $this->assertSame(['قبلی', 'بعدی'], array_column($row['notes'], 'text'));
        $this->assertSame('بعدی', $row['description']);
    }

    public function test_row_has_description_reads_notes_even_if_description_stale(): void
    {
        $this->assertTrue(CampaignFollowupNotes::rowHasDescription([
            'description' => '',
            'notes' => [
                ['id' => 'n1', 'text' => 'فقط در notes', 'created_at' => '2026-11-01T10:00:00+00:00'],
            ],
        ]));
        $this->assertFalse(CampaignFollowupNotes::rowHasDescription([
            'description' => '',
            'notes' => [],
        ]));
    }
}
