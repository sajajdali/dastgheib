<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CentralServiceTicketController extends Controller
{
    private function table()
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('central_service_tickets');
    }

    public function tenantIndex(): JsonResponse
    {
        $tenantId = tenant('id');

        return response()->json([
            'tickets' => $this->table()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($ticket) => $this->ticketData($ticket))
                ->values(),
        ]);
    }

    public function tenantStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $path = null;
        $name = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = $file->getClientOriginalName();
            $path = $file->store('central-service-tickets', 'public');
        }

        $now = now();
        $id = $this->table()->insertGetId([
            'tenant_id' => tenant('id'),
            'tenant_name' => tenant()?->name ?? tenant('id'),
            'user_name' => $request->user()?->name,
            'subject' => $data['subject'],
            'question' => $data['question'],
            'status' => 'open',
            'attachment_name' => $name,
            'attachment_path' => $path,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ticket = $this->table()->where('id', $id)->first();

        return response()->json([
            'message' => 'تیکت ثبت شد.',
            'ticket' => $this->ticketData($ticket),
        ], 201);
    }

    public function centralIndex(): JsonResponse
    {
        return response()->json([
            'tickets' => $this->table()
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($ticket) => $this->ticketData($ticket))
                ->values(),
        ]);
    }

    public function centralUpdate(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(['open', 'answered', 'closed'])],
            'answer_attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = [
            'status' => $data['status'],
            'updated_at' => now(),
        ];

        if (array_key_exists('answer', $data)) {
            $payload['answer'] = $data['answer'];
            if (trim((string) $data['answer']) !== '' || $request->hasFile('answer_attachment')) {
                $payload['answered_by'] = $request->user('central')?->name;
                $payload['answered_at'] = now();
                if ($data['status'] === 'open') {
                    $payload['status'] = 'answered';
                }
            }
        }

        if ($request->hasFile('answer_attachment')) {
            $file = $request->file('answer_attachment');
            $payload['answer_attachment_name'] = $file->getClientOriginalName();
            $payload['answer_attachment_path'] = $file->store('central-service-ticket-answers', 'public');
        }

        $this->table()->where('id', $ticket)->update($payload);
        $next = $this->table()->where('id', $ticket)->first();

        return response()->json([
            'message' => 'تیکت به‌روزرسانی شد.',
            'ticket' => $this->ticketData($next),
        ]);
    }

    private function ticketData($ticket): array
    {
        return [
            'id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'tenant_name' => $ticket->tenant_name,
            'user_name' => $ticket->user_name,
            'subject' => $ticket->subject,
            'question' => $ticket->question,
            'status' => $ticket->status,
            'answer' => $ticket->answer,
            'answered_by' => $ticket->answered_by,
            'answered_at' => $ticket->answered_at,
            'answer_attachment_name' => $ticket->answer_attachment_name ?? null,
            'answer_attachment_url' => ($ticket->answer_attachment_path ?? null) ? Storage::disk('public')->url($ticket->answer_attachment_path) : null,
            'attachment_name' => $ticket->attachment_name,
            'attachment_url' => $ticket->attachment_path ? Storage::disk('public')->url($ticket->attachment_path) : null,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }
}
