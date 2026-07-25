<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

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
        $ticket->update($request->validate([
            'date' => ['sometimes', 'required', 'string', 'max:10'],
            'status' => ['sometimes', 'required', 'in:active,done,expired'],
        ]));
        return $ticket->fresh();
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'string', 'max:10'],
            'owner' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['required', 'in:active,done,expired'],
        ]);
    }
}
