<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class AssistantDoctorCountTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('doctors');
        Mockery::close();
        parent::tearDown();
    }

    public function test_exact_doctor_count_does_not_depend_on_list_limit(): void
    {
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('doctors')->insert(array_map(fn (int $n) => ['name'=>'پزشک '.$n], range(1, 55)));
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'appointments.view');

        $executor = app(AssistantToolExecutor::class);
        $count = $executor->execute($user, 'count_doctors', []);
        $list = $executor->execute($user, 'find_doctors', ['name'=>'']);

        $this->assertSame(55, $count['model']['count']);
        $this->assertSame(55, $count['presentation']['value']);
        $this->assertSame(55, $list['model']['count']);
        $this->assertCount(50, $list['model']['doctors']);
        $this->assertTrue($list['model']['truncated']);
    }
}
