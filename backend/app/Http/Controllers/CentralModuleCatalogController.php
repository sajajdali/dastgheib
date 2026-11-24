<?php

namespace App\Http\Controllers;

use App\Services\ModuleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralModuleCatalogController extends Controller
{
    public function __construct(private readonly ModuleCatalogService $catalog)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'modules' => $this->catalog->listForCentral(),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.id' => ['required', 'string', 'max:120'],
            'modules.*.name' => ['required', 'string', 'max:255'],
            'modules.*.price' => ['sometimes', 'integer', 'min:0'],
            'modules.*.enabled' => ['sometimes', 'boolean'],
            'modules.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'modules.*.periods' => ['required', 'array', 'min:1'],
            'modules.*.periods.*.key' => ['required', 'string', 'max:40'],
            'modules.*.periods.*.label' => ['sometimes', 'string', 'max:80'],
            'modules.*.periods.*.price' => ['required', 'integer', 'min:0'],
        ]);

        $modules = $this->catalog->syncFromCentral($data['modules']);

        return response()->json([
            'message' => 'قیمت امکانات در دیتابیس مرکزی ذخیره شد.',
            'modules' => $modules,
        ]);
    }
}
