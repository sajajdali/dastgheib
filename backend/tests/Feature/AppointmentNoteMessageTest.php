<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppointmentNoteMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_and_read_a_text_note(): void
    {
        $user = User::factory()->create(['name' => 'پزشک تست']);

        $response = $this->actingAs($user)->postJson('/api/appointment-notes', [
            'appointment_key' => '1405-04|1|1001|09:00',
            'message_type' => 'text',
            'message' => 'شرح معاینه بیمار',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'شرح معاینه بیمار')
            ->assertJsonPath('author.name', 'پزشک تست');

        $this->actingAs($user)
            ->getJson('/api/appointment-notes?appointment_key=1405-04%7C1%7C1001%7C09%3A00')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.author.name', 'پزشک تست');
    }

    public function test_authenticated_user_can_send_a_voice_note(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api/appointment-notes', [
            'appointment_key' => '1405-04|1|1001|09:00',
            'message_type' => 'audio',
            'audio_duration' => 12,
            'audio' => UploadedFile::fake()->create('voice.webm', 100, 'audio/webm'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('message_type', 'audio')
            ->assertJsonPath('audio_duration', 12);

        $path = basename((string) $response->json('audio_url'));
        Storage::disk('public')->assertExists('appointment-notes/audio/'.$path);
    }

    public function test_author_can_delete_own_voice_note_and_its_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $created = $this->actingAs($user)->post('/api/appointment-notes', [
            'appointment_key' => '1405-04|1|1001|09:00',
            'message_type' => 'audio',
            'audio' => UploadedFile::fake()->create('voice.webm', 100, 'audio/webm'),
        ])->assertCreated();

        $path = basename((string) $created->json('audio_url'));
        $this->actingAs($user)->deleteJson('/api/appointment-notes/'.$created->json('id'))->assertNoContent();

        $this->assertDatabaseEmpty('appointment_note_messages');
        Storage::disk('public')->assertMissing('appointment-notes/audio/'.$path);
    }

    public function test_user_cannot_delete_another_users_note(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $messageId = $this->actingAs($author)->postJson('/api/appointment-notes', [
            'appointment_key' => '1405-04|1|1001|09:00',
            'message_type' => 'text',
            'message' => 'یادداشت داخلی',
        ])->json('id');

        $this->actingAs($otherUser)->deleteJson('/api/appointment-notes/'.$messageId)->assertForbidden();
        $this->assertDatabaseHas('appointment_note_messages', ['id' => $messageId]);
    }
}
