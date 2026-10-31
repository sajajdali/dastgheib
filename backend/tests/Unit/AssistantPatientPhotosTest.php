<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class AssistantPatientPhotosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('patients', function (Blueprint $table): void {
            $table->id(); $table->string('first_name'); $table->string('last_name');
            $table->string('file_number'); $table->softDeletes();
        });
        Schema::create('patient_media_folders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('folder_date')->nullable(); $table->string('name');
        });
        Schema::create('patient_media', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->unsignedBigInteger('folder_id')->nullable();
            $table->string('media_type'); $table->string('comparison_stage')->nullable();
            $table->string('photo_angle_label')->nullable(); $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('patient_media');
        Schema::dropIfExists('patient_media_folders');
        Schema::dropIfExists('patients');
        Mockery::close();
        parent::tearDown();
    }

    public function test_ambiguous_name_prompts_for_patient_then_lists_only_after_photos(): void
    {
        DB::table('patients')->insert([
            ['id'=>1,'first_name'=>'مریم','last_name'=>'موسوی','file_number'=>'101'],
            ['id'=>2,'first_name'=>'سارا','last_name'=>'موسوی','file_number'=>'102'],
        ]);
        DB::table('patient_media_folders')->insert(['id'=>5,'patient_id'=>1,'name'=>'1405-08-08','folder_date'=>'1405-08-08']);
        DB::table('patient_media')->insert([
            ['id'=>11,'patient_id'=>1,'folder_id'=>5,'media_type'=>'image','comparison_stage'=>'after','photo_angle_label'=>'تمام‌رخ','created_at'=>now(),'updated_at'=>now()],
            ['id'=>12,'patient_id'=>1,'folder_id'=>5,'media_type'=>'image','comparison_stage'=>'before','photo_angle_label'=>'تمام‌رخ','created_at'=>now(),'updated_at'=>now()],
        ]);
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['patients.view','photos.view'], true));
        $executor = app(AssistantToolExecutor::class);

        $ambiguous = $executor->execute($user, 'find_patient_photos', ['patient'=>'موسوی','file_number'=>null,'stage'=>'after']);
        $this->assertTrue($ambiguous['model']['ambiguous']);
        $this->assertCount(2, $ambiguous['presentation']['row_actions']);

        $selected = $executor->execute($user, 'find_patient_photos', ['patient'=>'','file_number'=>'101','stage'=>'after']);
        $this->assertSame(1, $selected['model']['count']);
        $this->assertSame('1405-08-08', $selected['model']['rows'][0]['date']);
        $this->assertSame(11, $selected['presentation']['row_actions'][0]['media_id']);

        $opened = $executor->execute($user, 'open_patient_result', ['file_number'=>'101','media_id'=>11]);
        $this->assertSame('patient_photo', $opened['presentation']['page']);
    }
}
