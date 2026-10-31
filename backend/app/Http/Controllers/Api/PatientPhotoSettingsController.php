<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PatientPhotoSettings;
use Illuminate\Http\Request;

class PatientPhotoSettingsController extends Controller
{
    public function show(PatientPhotoSettings $settings)
    {
        return response()->json($settings->current());
    }

    public function update(Request $request, PatientPhotoSettings $settings)
    {
        $data = $request->validate([
            'max_side' => ['required', 'integer', 'between:600,6000'],
            'quality' => ['required', 'integer', 'between:80,100'],
            'target_kb' => ['required', 'integer', 'between:100,10240'],
        ]);

        return response()->json($settings->save($data));
    }
}
