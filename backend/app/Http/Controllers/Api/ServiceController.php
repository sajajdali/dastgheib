<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;

class ServiceController extends Controller
{
    public function store(Request $request)
    {
        $service = Service::create([
            'file_number' => $request->file_number,
            'date' => $request->date,
            'service' => $request->service,
            'status' => $request->status,
            'doctor' => $request->doctor,
            'referral_code' => $request->referral_code,
            'club_score' => $request->club_score,
            'amount' => $request->amount
        ]);

        return response()->json([
            'message' => 'service saved',
            'data' => $service
        ]);
    }
}
