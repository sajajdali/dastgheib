<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index()
    {
        return Ticket::query()->orderBy('date')->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Ticket::create($this->validated($request)), 201);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'required', 'string', 'max:10'],
            'status' => ['sometimes', 'required', 'in:active,done,expired'],
            'owner' => ['sometimes', 'required', 'string', 'max:100', Rule::exists('staff', 'name')],
            'owners' => ['sometimes', 'required', 'array', 'min:1'],
            'owners.*' => ['required', 'string', 'max:100', 'distinct', Rule::exists('staff', 'name')],
        ]);

        if (array_key_exists('owners', $data) || array_key_exists('owner', $data)) {
            $data = $this->withNormalizedOwners($data);
        }

        $ticket->update($data);
        return $ticket->fresh();
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $this->withNormalizedOwners($request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'string', 'max:10'],
            'owner' => ['nullable', 'required_without:owners', 'string', 'max:100', Rule::exists('staff', 'name')],
            'owners' => ['nullable', 'required_without:owner', 'array', 'min:1'],
            'owners.*' => ['required', 'string', 'max:100', 'distinct', Rule::exists('staff', 'name')],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['required', 'in:active,done,expired'],
        ]));
    }

    private function withNormalizedOwners(array $data): array
    {
        $owners = collect($data['owners'] ?? [$data['owner'] ?? null])
            ->map(fn ($owner) => trim((string) $owner))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data['owners'] = $owners;
        $data['owner'] = $owners[0] ?? null;

        return $data;
    }
}
