<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::query()
            ->with('campaign:id,name')
            ->latest('occurred_on')
            ->latest('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['attachment_path'] = $this->storeAttachment($request, null);

        $expense = Expense::create($data);

        return response()->json($expense->fresh('campaign'), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);

        if ($request->boolean('clear_attachment')) {
            $expense->deleteAttachmentFile();
            $data['attachment_path'] = null;
        } elseif ($path = $this->storeAttachment($request, $expense->attachment_path)) {
            $data['attachment_path'] = $path;
        }

        $expense->update($data);

        return $expense->fresh('campaign');
    }

    public function destroy(Expense $expense)
    {
        $expense->deleteAttachmentFile();
        $expense->delete();

        return response()->noContent();
    }

    public function attachment(Expense $expense): Response
    {
        abort_unless($expense->attachment_path && Storage::disk('public')->exists($expense->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($expense->attachment_path), [
            'Content-Type' => Storage::disk('public')->mimeType($expense->attachment_path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'occurred_on' => ['required', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'category' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:expense,income,receivable,payable'],
            'payment_status' => ['required', 'in:paid,unpaid,pending'],
            'party' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'account_type' => ['nullable', 'string', 'max:40'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'note' => ['nullable', 'string'],
            'attachment_data' => ['nullable', 'string'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'clear_attachment' => ['sometimes', 'boolean'],
        ]);

        unset($data['attachment_data'], $data['attachment_name'], $data['clear_attachment']);

        return $data;
    }

    private function storeAttachment(Request $request, ?string $previousPath): ?string
    {
        $dataUrl = trim((string) $request->input('attachment_data', ''));
        if ($dataUrl === '') {
            return null;
        }

        if (! preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $dataUrl, $matches)) {
            abort(422, 'فرمت عکس هزینه نامعتبر است.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            abort(422, 'خواندن عکس هزینه ممکن نشد.');
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            abort(422, 'حجم عکس باید کمتر از ۵ مگابایت باشد.');
        }

        $extension = match (strtolower($matches[1])) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };

        $path = 'expenses/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        if ($previousPath && $previousPath !== $path && Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }
}
