<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\InventorySection;
use App\Models\PatientMedia;
use App\Models\PatientMediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientMediaFolderDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\EnsureTenantSubscriptionActive::class,
            \App\Http\Middleware\EnforceTenantStorageQuota::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('file_number')->unique();
            $table->string('gender');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id');
            $table->string('type');
            $table->decimal('amount');
            $table->string('source_type')->nullable();
            $table->foreignId('reversed_transaction_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('debt')->default(0);
        });
        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('patient_media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id');
            $table->foreignId('parent_id')->nullable()->constrained('patient_media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('folder_type')->nullable();
            $table->string('folder_date')->nullable();
            $table->foreignId('inventory_section_id')->nullable();
            $table->json('services')->nullable();
            $table->text('upload_description')->nullable();
            $table->boolean('upload_usage_consent')->default(false);
            $table->boolean('upload_is_featured')->default(false);
            $table->json('photo_processing_settings')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('patient_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id');
            $table->foreignId('folder_id')->nullable();
            $table->string('file_name');
            $table->string('path');
            $table->integer('size')->default(0);
            $table->boolean('usage_consent')->default(false);
            $table->json('services')->nullable();
            $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_deleting_a_folder_removes_all_descendants_media_and_stored_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '09120000000',
            'file_number' => 'delete-folder-test',
            'gender' => 'female',
        ]);

        $root = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'name' => 'Root',
            'folder_type' => 'date',
        ]);
        $child = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'folder_type' => 'service',
        ]);
        $grandchild = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild',
            'folder_type' => 'before_photo',
        ]);
        $unrelated = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'name' => 'Keep',
            'folder_type' => 'date',
        ]);

        $deletedPaths = [
            'patients/test/root.jpg' => $root->id,
            'patients/test/child.jpg' => $child->id,
            'patients/test/grandchild.jpg' => $grandchild->id,
        ];

        foreach ($deletedPaths as $path => $folderId) {
            Storage::disk('public')->put($path, 'image');
            PatientMedia::create([
                'patient_id' => $patient->id,
                'folder_id' => $folderId,
                'file_name' => basename($path),
                'path' => $path,
                'size' => 5,
            ]);
        }

        Storage::disk('public')->put('patients/test/keep.jpg', 'image');
        $keptMedia = PatientMedia::create([
            'patient_id' => $patient->id,
            'folder_id' => $unrelated->id,
            'file_name' => 'keep.jpg',
            'path' => 'patients/test/keep.jpg',
            'size' => 5,
        ]);

        $this->deleteJson("/api/patients/{$patient->id}/media/folders/{$root->id}")
            ->assertOk()
            ->assertJsonPath('deleted_folders', 3)
            ->assertJsonPath('deleted_files', 3);

        foreach ([$root, $child, $grandchild] as $folder) {
            $this->assertDatabaseMissing('patient_media_folders', ['id' => $folder->id]);
        }
        foreach (array_keys($deletedPaths) as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('patient_media_folders', ['id' => $unrelated->id]);
        $this->assertDatabaseHas('patient_media', ['id' => $keptMedia->id]);
        Storage::disk('public')->assertExists('patients/test/keep.jpg');
    }

    public function test_root_date_folders_are_returned_from_newest_to_oldest_with_persian_digits_supported(): void
    {
        $this->actingAs(User::factory()->create());

        $patient = Patient::create([
            'first_name' => 'Date',
            'last_name' => 'Order',
            'phone' => '09120000001',
            'file_number' => 'date-order-test',
            'gender' => 'female',
        ]);

        foreach (['۱۴۰۴-۰۱-۱۰', '1403-12-29', '۱۴۰۴-۰۲-۰۱'] as $date) {
            PatientMediaFolder::create([
                'patient_id' => $patient->id,
                'name' => $date,
                'folder_type' => 'date',
                'folder_date' => $date,
            ]);
        }

        $this->getJson("/api/patients/{$patient->id}/media")
            ->assertOk()
            ->assertJsonPath('folders.0.name', '۱۴۰۴-۰۲-۰۱')
            ->assertJsonPath('folders.1.name', '۱۴۰۴-۰۱-۱۰')
            ->assertJsonPath('folders.2.name', '1403-12-29');
    }

    public function test_a_legacy_root_folder_can_be_upgraded_when_creating_a_service_folder(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = Patient::create([
            'first_name' => 'Legacy', 'last_name' => 'Folder',
            'phone' => '09120000002', 'file_number' => 'legacy-folder-test', 'gender' => 'female',
        ]);
        $legacyFolder = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'name' => 'عکس قبل و بعد',
        ]);
        $section = InventorySection::create(['name' => 'جراحی']);

        $this->postJson("/api/patients/{$patient->id}/media/folders", [
            'type' => 'service',
            'section_id' => $section->id,
            'parent_id' => $legacyFolder->id,
            'services' => [['name' => 'ژل لب']],
        ])->assertCreated()->assertJsonPath('name', 'جراحی');

        $this->assertDatabaseHas('patient_media_folders', [
            'id' => $legacyFolder->id,
            'folder_type' => 'date',
        ]);
        $this->assertDatabaseCount('patient_media_folders', 5);
    }

    public function test_service_folder_keeps_selected_areas_before_any_photo_is_uploaded(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = Patient::create([
            'first_name' => 'Area', 'last_name' => 'Persistence',
            'phone' => '09120000003', 'file_number' => 'area-persistence-test', 'gender' => 'female',
        ]);
        $dateFolder = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'name' => '1405-08-08',
            'folder_type' => 'date',
            'folder_date' => '1405-08-08',
        ]);
        $section = InventorySection::create(['name' => 'تزریق']);

        $response = $this->postJson("/api/patients/{$patient->id}/media/folders", [
            'type' => 'service',
            'section_id' => $section->id,
            'parent_id' => $dateFolder->id,
            'services' => [['name' => 'ژل لب']],
            'description' => 'تنظیمات اولیه فولدر',
            'usage_consent' => false,
            'is_featured' => true,
        ])->assertCreated();

        $folderId = $response->json('id');
        $beforePhotoFolderId = $response->json('children.0.id');

        $this->getJson("/api/patients/{$patient->id}/media?folder_id={$beforePhotoFolderId}")
            ->assertOk()
            ->assertJsonPath('shared_upload_metadata.service_folder_id', $folderId)
            ->assertJsonPath('shared_upload_metadata.services.0.name', 'ژل لب')
            ->assertJsonPath('shared_upload_metadata.description', 'تنظیمات اولیه فولدر')
            ->assertJsonPath('shared_upload_metadata.usage_consent', false)
            ->assertJsonPath('shared_upload_metadata.is_featured', true);
    }
}
