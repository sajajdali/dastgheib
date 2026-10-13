<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SatisfactionDocumentationController extends Controller
{
    public function index(): View
    {
        return view('satisfaction-swagger');
    }

    public function spec(): JsonResponse
    {
        $spec = json_decode(file_get_contents(resource_path('openapi/satisfaction-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $spec['servers'] = [['url' => '/']];

        return response()->json($spec, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
