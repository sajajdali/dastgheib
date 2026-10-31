<?php

namespace Tests\Unit;

use App\Models\PatientMediaFolder;
use App\Services\PatientPhotoSettings;
use Tests\TestCase;

class PatientPhotoSettingsTest extends TestCase
{
    public function test_photo_folder_inherits_the_snapshot_saved_on_its_service_folder(): void
    {
        $snapshot = ['max_side' => 1800, 'quality' => 92, 'target_kb' => 768];
        $serviceFolder = new PatientMediaFolder([
            'folder_type' => 'service',
            'photo_processing_settings' => $snapshot,
        ]);
        $photoFolder = new PatientMediaFolder(['folder_type' => 'before_photo']);
        $photoFolder->setRelation('parent', $serviceFolder);

        $this->assertSame($snapshot, app(PatientPhotoSettings::class)->forFolder($photoFolder));
    }
}
