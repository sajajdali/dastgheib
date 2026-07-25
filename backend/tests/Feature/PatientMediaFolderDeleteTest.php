<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\InventorySection;
use App\Models\PatientMedia;
use App\Models\PatientMediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientMediaFolderDeleteTest extends TestCase
{
    use RefreshDatabase;

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
        ])->assertCreated()->assertJsonPath('name', 'جراحی');

        $this->assertDatabaseHas('patient_media_folders', [
            'id' => $legacyFolder->id,
            'folder_type' => 'date',
        ]);
        $this->assertDatabaseCount('patient_media_folders', 5);
    }
}
