<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentNoteMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppointmentNoteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['appointment_key' => ['required', 'string', 'max:190']]);
        $isSecretary = $this->isSecretary($request);
        if ($isSecretary) {
            AppointmentNoteMessage::query()
                ->where('appointment_key', $data['appointment_key'])
                ->where('requires_secretary_attention', true)
                ->whereNull('secretary_seen_at')
                ->update(['secretary_seen_at' => now()]);
        }
        $messages = AppointmentNoteMessage::with('user:id,name,profile_photo_path,profile_thumbnail_path')
            ->where('appointment_key', $data['appointment_key'])->oldest('id')->get();
        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->resource($message)),
            'has_unread_doctor_note' => $messages->contains(fn ($message) => $message->requires_secretary_attention && ! $message->secretary_seen_at),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'appointment_key' => ['required', 'string', 'max:190'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'message_type' => ['required', 'in:text,audio,image'],
            'message' => ['nullable', 'string', 'max:20000', 'required_if:message_type,text'],
            'audio' => ['nullable', 'file', 'max:15360', 'required_if:message_type,audio'],
            'audio_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:15360', 'required_if:message_type,image'],
        ]);

        $audioPath = $request->hasFile('audio')
            ? $request->file('audio')->store('appointment-notes/audio', 'public')
            : null;
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('appointment-notes/images', 'public')
            : null;

        unset($data['audio'], $data['image']);

        $message = AppointmentNoteMessage::create([
            ...$data,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'audio_path' => $audioPath,
            'image_path' => $imagePath,
            'user_id' => $request->user()?->id,
            'requires_secretary_attention' => $this->isDoctor($request),
        ]);

        return response()->json($this->resource($message->load('user')), 201);
    }

    public function destroy(Request $request, AppointmentNoteMessage $message)
    {
        abort_unless((int) $message->user_id === (int) $request->user()->id, 403, 'فقط فرستنده پیام می‌تواند آن را حذف کند.');

        if ($message->audio_path) {
            Storage::disk('public')->delete($message->audio_path);
        }
        if ($message->image_path) {
            Storage::disk('public')->delete($message->image_path);
        }

        $message->delete();

        return response()->noContent();
    }

    private function resource(AppointmentNoteMessage $message): array
    {
        return [
            'id' => $message->id,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'audio_url' => $message->audio_path ? Storage::disk('public')->url($message->audio_path) : null,
            'audio_duration' => $message->audio_duration,
            'image_url' => $message->image_path ? Storage::disk('public')->url($message->image_path) : null,
            'requires_secretary_attention' => $message->requires_secretary_attention,
            'secretary_seen_at' => $message->secretary_seen_at,
            'created_at' => $message->created_at,
            'can_delete' => (int) $message->user_id === (int) request()->user()?->id,
            'author' => $message->user ? [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'avatar_url' => $message->user->avatar_url,
            ] : ['id' => null, 'name' => 'کاربر حذف‌شده', 'avatar_url' => null],
        ];
    }

    private function isDoctor(Request $request): bool
    {
        return $request->user()?->roles()->where(function ($query) {
            $query->where('name', 'like', '%پزشک%')->orWhere('name', 'like', '%doctor%');
        })->exists() ?? false;
    }

    private function isSecretary(Request $request): bool
    {
        return $request->user()?->roles()->where(function ($query) {
            $query->where('name', 'like', '%منشی%')->orWhere('name', 'like', '%secretary%');
        })->exists() ?? false;
    }
}
