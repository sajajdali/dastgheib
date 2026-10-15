<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    // برگرداندن همه تماس‌ها
    public function index()
    {
        return Contact::orderBy('id')->get();
    }

    // Only submitted rows are inserted/updated; omissions never mean deletion.
    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.save_version' => ['nullable', 'string'],
            'items.*.full_name' => ['nullable', 'string', 'max:255'],
            'items.*.phone' => ['nullable', 'string', 'max:30'],
            'items.*.date' => ['nullable', 'string', 'max:20'],
            'items.*.follow_up_date' => ['nullable', 'string', 'max:20'],
            'items.*.gender' => ['nullable', 'string', 'max:10'],
            'items.*.consultant' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.source' => ['nullable', 'string', 'max:255'],
            'items.*.status' => ['nullable', 'string', 'max:255'],
            'items.*.interest' => ['nullable', 'string', 'max:255'],
            'items.*.campaign_id' => ['nullable', 'integer'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer', 'distinct'],
        ]);
        $fields = ['full_name', 'phone', 'date', 'follow_up_date', 'gender', 'consultant', 'description', 'source', 'status', 'interest', 'campaign_id'];

        $ids = DB::transaction(function () use ($data, $fields) {
            $saved = [];
            foreach ($data['items'] as $item) {
                $contact = ! empty($item['id']) ? Contact::query()->lockForUpdate()->find($item['id']) : new Contact();
                if (! $contact) throw ValidationException::withMessages(['items' => 'یکی از تماس‌ها دیگر وجود ندارد. صفحه را تازه‌سازی کنید.']);
                if ($contact->exists && (empty($item['save_version']) || ! hash_equals($contact->save_version, $item['save_version']))) {
                    throw ValidationException::withMessages(['items' => 'این تماس در صفحهٔ دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.']);
                }
                $attributes = collect($item)->only($fields)->all();
                if (! $contact->exists && ! isset($attributes['full_name'])) $attributes['full_name'] = '';
                $contact->fill($attributes);
                if (! $contact->exists || $contact->isDirty()) $contact->save();
                $saved[] = $contact->id;
            }
            foreach ($data['deleted_ids'] ?? [] as $id) {
                Contact::query()->whereKey($id)->first()?->delete();
            }
            return $saved;
        });

        return response()->json(['message' => 'تماس‌ها ذخیره شدند.', 'ids' => $ids]);
    }
}
