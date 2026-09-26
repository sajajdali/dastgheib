<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    private const GENERAL_FOLLOWUP_MARKER = '__system_general_followups__';

    public function index()
    {
        return Campaign::query()->orderByDesc('active')->orderBy('name')->get();
    }

    public function general()
    {
        $campaign = Campaign::query()->firstOrCreate(
            ['note' => self::GENERAL_FOLLOWUP_MARKER],
            [
                'name' => 'پیگیری کلی',
                'budget' => 0,
                'active' => true,
                'ui_payload' => [
                    'title' => 'پیگیری کلی',
                    'source' => '',
                    'sourceName' => '',
                    'date' => '',
                    'cost' => '',
                    'description' => '',
                    'banners' => [],
                    'campaignStatus' => 'active',
                    'pinned' => false,
                    'isGeneralFollowup' => true,
                    'rows' => [],
                ],
            ]
        );

        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        if (!($payload['isGeneralFollowup'] ?? false)) {
            $campaign->update(['ui_payload' => array_merge($payload, [
                'title' => 'پیگیری کلی',
                'isGeneralFollowup' => true,
            ])]);
        }

        return $campaign->fresh();
    }

    public function store(Request $request)
    {
        return response()->json(Campaign::create($this->data($request)), 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->update($this->data($request));

        return $campaign->fresh();
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->noContent();
    }

    private function data(Request $request)
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel_id' => ['nullable', 'exists:channels,id'],
            'starts_on' => ['nullable', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'ends_on' => ['nullable', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'active' => ['boolean'],
            'ui_payload' => ['nullable', 'array'],
        ]);
    }
}
