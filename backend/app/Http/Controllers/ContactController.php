<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    // برگرداندن همه تماس‌ها
    public function index()
    {
        return Contact::orderBy('id')->get();
    }

    // ذخیره کل جدول
    public function store(Request $request)
    {
        $items = $request->items ?? [];

        // ابتدا کل جدول پاک می‌شود
        Contact::query()->get()->each->delete();

        // درج مجدد همه آیتم‌ها
        foreach ($items as $item) {
            Contact::create([
                'full_name'       => $item['full_name'] ?? '',
                'phone'           => $item['phone'] ?? '',
                'date'            => $item['date'] ?? '',
                'follow_up_date'  => $item['follow_up_date'] ?? '',
                'gender'          => $item['gender'] ?? '',
                'consultant'      => $item['consultant'] ?? '',
                'description'     => $item['description'] ?? '',
                'source'          => $item['source'] ?? '',
                'status'          => $item['status'] ?? '',
                'interest'        => $item['interest'] ?? '',
            ]);
        }

        return response()->json(['message' => 'saved']);
    }
}
