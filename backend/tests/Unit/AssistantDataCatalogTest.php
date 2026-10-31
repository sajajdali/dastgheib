<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantDataCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class AssistantDataCatalogTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
        Mockery::close();
        parent::tearDown();
    }

    public function test_inventory_query_returns_filtered_total_and_rejects_unlisted_fields(): void
    {
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('stock');
            $table->integer('min_stock');
            $table->integer('price');
            $table->boolean('active');
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('inventory_id'); $table->string('inventory_name');
            $table->integer('quantity'); $table->string('type'); $table->dateTime('occurred_at')->nullable();
        });
        DB::table('inventories')->insert([
            ['name'=>'سرنگ','stock'=>2,'min_stock'=>5,'price'=>100,'active'=>1],
            ['name'=>'ماسک','stock'=>20,'min_stock'=>5,'price'=>200,'active'=>1],
        ]);
        DB::table('inventory_movements')->insert(['inventory_id'=>1,'inventory_name'=>'سرنگ','quantity'=>2,'type'=>'in']);
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'inventory.view');
        $catalog = new AssistantDataCatalog;
        $result = $catalog->query($user, ['dataset'=>'inventory','filters'=>[
            ['field'=>'name','operator'=>'contains','value'=>'سر'],
        ],'limit'=>1]);

        $this->assertSame(1, $result['model']['count']);
        $this->assertSame('سرنگ', $result['model']['rows'][0]['name']);
        $this->assertSame(1, $result['model']['rows'][0]['id']);
        $this->assertArrayHasKey('prompt', $result['presentation']['row_actions'][0]);
        $details = $catalog->inspect($user, 'inventory', 1);
        $this->assertSame(1, $details['model']['relations'][0]['count']);
        $this->assertSame('inventory_movements', $details['model']['relations'][0]['dataset']);
        $this->assertNotContains('patients', $catalog->names($user));
        $this->expectException(InvalidArgumentException::class);
        $catalog->query($user, ['dataset'=>'inventory','filters'=>[
            ['field'=>'secret','operator'=>'equals','value'=>'x'],
        ],'limit'=>20]);
    }
}
