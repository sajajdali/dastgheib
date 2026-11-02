<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantListShaper;
use App\Services\Assistant\AssistantToolExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class AssistantListShaperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('doctors');
        Mockery::close();
        parent::tearDown();
    }

    public function test_large_list_asks_user_and_sends_only_a_sample_to_the_model(): void
    {
        DB::table('doctors')->insert(array_map(fn (int $n) => ['name' => sprintf('پزشک %03d', $n)], range(1, 120)));
        $user = $this->user(['appointments.view']);
        $result = app(AssistantToolExecutor::class)->execute($user, 'find_doctors', ['name' => '', 'limit' => 20]);

        $shaped = app(AssistantListShaper::class)->shape('find_doctors', ['name' => '', 'limit' => 20], $result);

        $this->assertTrue($shaped['presentation']['ask']);
        $this->assertSame(120, $shaped['presentation']['list']['total']);
        $this->assertSame(['name' => ''], $shaped['presentation']['list']['arguments']);
        $this->assertCount(20, $shaped['presentation']['rows']);
        $this->assertCount(5, $shaped['model']['doctors']);
        $this->assertTrue($shaped['model']['rows_in_card']);
    }

    public function test_small_list_is_shown_directly_with_full_rows_for_the_model(): void
    {
        DB::table('doctors')->insert(array_map(fn (int $n) => ['name' => 'پزشک '.$n], range(1, 8)));
        $user = $this->user(['appointments.view']);
        $result = app(AssistantToolExecutor::class)->execute($user, 'find_doctors', ['name' => '']);

        $shaped = app(AssistantListShaper::class)->shape('find_doctors', ['name' => ''], $result);

        $this->assertArrayNotHasKey('ask', $shaped['presentation']);
        $this->assertCount(8, $shaped['model']['doctors']);
        $this->assertArrayNotHasKey('rows_in_card', $shaped['model']);
    }

    public function test_pages_are_fetched_without_ai_and_respect_permissions(): void
    {
        DB::table('doctors')->insert(array_map(fn (int $n) => ['name' => sprintf('پزشک %03d', $n)], range(1, 120)));
        $lists = app(AssistantListShaper::class);

        $page = $lists->page($this->user(['appointments.view']), 'find_doctors', ['name' => ''], 100, 50);
        $this->assertCount(20, $page['rows']);
        $this->assertSame('پزشک 101', $page['rows'][0]['name']);
        $this->assertSame(120, $page['total']);
        $this->assertSame(120, $page['next_offset']);

        $this->expectException(InvalidArgumentException::class);
        $lists->page($this->user([]), 'find_doctors', ['name' => ''], 0, 20);
    }

    public function test_only_list_tools_can_be_paged(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AssistantListShaper::class)->page($this->user(['appointments.view']), 'count_doctors', [], 0, 20);
    }

    private function user(array $permissions): User
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, $permissions, true));

        return $user;
    }
}
