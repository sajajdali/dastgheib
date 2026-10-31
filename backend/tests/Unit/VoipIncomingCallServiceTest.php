<?php

namespace Tests\Unit;

use App\Services\VoipIncomingCallService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoipIncomingCallServiceTest extends TestCase
{
    public function test_incoming_call_updates_once_per_event_and_ignores_outbound_and_test_calls(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->softDeletes();
        });
        (require database_path('migrations/tenant/2026_11_01_000000_create_voip_call_events_tables.php'))->up();
        DB::table('patients')->insert(['id' => 7, 'first_name' => 'علی', 'last_name' => 'رضایی', 'phone' => '09121234567']);

        $calls = app(VoipIncomingCallService::class);
        $started = $this->event('call.started', 1, ['direction' => 'inbound', 'phone' => '+989121234567']);
        $calls->record($started, json_encode($started));
        $calls->record($started, json_encode($started));
        $this->assertDatabaseCount('voip_call_events', 1);
        $this->assertDatabaseHas('voip_calls', ['call_id' => 'C123', 'phone' => '09121234567', 'patient_id' => 7, 'state' => 'شروع تماس']);

        $ringing = $this->event('call.ringing', 2, ['target' => 'extension', 'operator' => 'پذیرش']);
        $calls->record($ringing, json_encode($ringing));
        $ended = $this->event('call.ended', 4, ['direction' => 'inbound', 'status' => 'answered', 'statusLabel' => 'پاسخ‌داده', 'phone' => '09121234567']);
        $calls->record($ended, json_encode($ended));
        $late = $this->event('call.answered', 3, ['operator' => 'اپراتور قدیمی']);
        $calls->record($late, json_encode($late));
        $this->assertDatabaseHas('voip_calls', ['call_id' => 'C123', 'latest_seq' => 4, 'state' => 'پاسخ‌داده', 'operator' => 'پذیرش']);

        $outbound = $this->event('call.started', 1, ['direction' => 'outbound', 'phone' => '09129999999'], 'C-out');
        $calls->record($outbound, json_encode($outbound));
        $test = $this->event('call.started', 1, ['direction' => 'inbound', 'phone' => '09129999999'], 'test');
        $test['test'] = true;
        $calls->record($test, json_encode($test));
        $this->assertDatabaseCount('voip_calls', 1);
        $this->assertDatabaseCount('voip_call_events', 5);
        $this->assertSame('علی رضایی', $calls->recent()[0]['patient_name']);
    }

    private function event(string $name, int $seq, array $data, string $callId = 'C123'): array
    {
        return [
            'event' => $name,
            'eventId' => sprintf('00000000-0000-4000-8000-%012d', crc32($name.$seq.$callId)),
            'callId' => $callId,
            'seq' => $seq,
            'occurredAt' => '2026-10-31T07:20:10.000Z',
            'data' => $data,
        ];
    }
}
