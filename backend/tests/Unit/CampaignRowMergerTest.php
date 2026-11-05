<?php

namespace Tests\Unit;

use App\Services\CampaignRowMerger;
use PHPUnit\Framework\TestCase;

class CampaignRowMergerTest extends TestCase
{
    private function row(string $id, array $fields = []): array
    {
        return ['_localId' => $id, 'fullName' => '', 'status' => null, 'consultant' => null, ...$fields];
    }

    public function test_keeps_rows_added_by_another_user(): void
    {
        $base = [$this->row('a')];
        $theirs = [$this->row('a'), $this->row('b', ['fullName' => 'other'])];
        $mine = [$this->row('a', ['status' => 'پاسخ داد'])];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame([], $result['conflicts']);
        $this->assertSame(['a', 'b'], array_column($result['rows'], '_localId'));
        $this->assertSame('پاسخ داد', $result['rows'][0]['status']);
    }

    public function test_merges_different_fields_of_the_same_row(): void
    {
        $base = [$this->row('a')];
        $theirs = [$this->row('a', ['consultant' => 'حنانه'])];
        $mine = [$this->row('a', ['status' => 'پاسخ نداد'])];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame([], $result['conflicts']);
        $this->assertSame('حنانه', $result['rows'][0]['consultant']);
        $this->assertSame('پاسخ نداد', $result['rows'][0]['status']);
    }

    public function test_reports_conflict_on_the_same_field(): void
    {
        $base = [$this->row('a')];
        $theirs = [$this->row('a', ['status' => 'اشتباه'])];
        $mine = [$this->row('a', ['status' => 'پاسخ داد'])];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame([['row_id' => 'a', 'fields' => ['status']]], $result['conflicts']);
    }

    public function test_deletes_only_rows_nobody_else_changed(): void
    {
        $base = [$this->row('a'), $this->row('b')];
        $theirs = [$this->row('a'), $this->row('b', ['status' => 'پیگیری'])];
        $mine = [];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame(['b'], array_column($result['rows'], '_localId'));
    }

    public function test_row_deleted_by_another_user_stays_deleted_when_unchanged_here(): void
    {
        $base = [$this->row('a'), $this->row('b')];
        $theirs = [$this->row('a')];
        $mine = [$this->row('a'), $this->row('b', ['fullName' => ''])];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame(['a'], array_column($result['rows'], '_localId'));
    }

    public function test_empty_string_and_null_are_equal(): void
    {
        $base = [$this->row('a', ['status' => null])];
        $theirs = [$this->row('a', ['status' => 'اشتباه'])];
        $mine = [$this->row('a', ['status' => ''])];

        $result = (new CampaignRowMerger)->merge($base, $theirs, $mine);

        $this->assertSame([], $result['conflicts']);
        $this->assertSame('اشتباه', $result['rows'][0]['status']);
    }
}
