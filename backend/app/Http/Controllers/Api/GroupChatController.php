<?php

namespace App\Http\Controllers\Api;

use App\Events\GroupChatMessageChanged;
use App\Http\Controllers\Controller;
use App\Models\GroupChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class GroupChatController extends Controller
{
    private const PAGE_SIZE = 40;
    private const MAX_TEXT = 4000;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) ($data['limit'] ?? self::PAGE_SIZE);

        $query = GroupChatMessage::query()->with('user');
        if (! empty($data['after_id'])) {
            // همگام‌سازی پس از قطع اتصال: پیام‌های جدیدتر به ترتیب صعودی.
            $messages = $query->where('id', '>', $data['after_id'])->orderBy('id')->limit($limit + 1)->get();
            $hasMore = $messages->count() > $limit;
            $messages = $messages->take($limit)->values();
        } else {
            if (! empty($data['before_id'])) {
                $query->where('id', '<', $data['before_id']);
            }
            $messages = $query->orderByDesc('id')->limit($limit + 1)->get();
            $hasMore = $messages->count() > $limit;
            $messages = $messages->take($limit)->reverse()->values();
        }

        return response()->json([
            'messages' => $messages->map->toChatArray()->values(),
            'has_more' => $hasMore,
            'unread_count' => $this->unreadCount($request),
            // برای نمایش خط «پیام‌های جدید» هنگام باز کردن چت.
            'last_read_id' => (int) DB::table('group_chat_reads')->where('user_id', $request->user()->id)->value('last_read_message_id'),
            'permissions' => $this->permissions($request),
            'me' => ['id' => $request->user()->id],
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->unreadCount($request)]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate(['message_id' => ['nullable', 'integer', 'min:0']]);
        $latest = (int) GroupChatMessage::query()->max('id');
        $target = min($latest, (int) ($data['message_id'] ?? $latest));
        $userId = $request->user()->id;

        $current = (int) DB::table('group_chat_reads')->where('user_id', $userId)->value('last_read_message_id');
        if ($target > $current) {
            DB::table('group_chat_reads')->updateOrInsert(
                ['user_id' => $userId],
                ['last_read_message_id' => $target, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        return response()->json(['unread_count' => $this->unreadCount($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:text,voice,image'],
            'body' => ['nullable', 'string', 'max:'.self::MAX_TEXT, 'required_if:type,text'],
            'voice' => ['nullable', 'file', 'max:15360', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/x-wav,video/webm,video/mp4,application/octet-stream', 'required_if:type,voice'],
            'voice_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240', 'required_if:type,image'],
        ]);

        $body = isset($data['body']) ? trim($data['body']) : null;
        if ($data['type'] === GroupChatMessage::TYPE_TEXT && ($body === null || $body === '')) {
            throw ValidationException::withMessages(['body' => 'متن پیام خالی است.']);
        }

        $file = match ($data['type']) {
            GroupChatMessage::TYPE_VOICE => $request->file('voice'),
            GroupChatMessage::TYPE_IMAGE => $request->file('image'),
            default => null,
        };
        // فایل روی دیسک public همان کلینیک ذخیره می‌شود؛ سهمیهٔ فضا را میان‌افزار EnforceTenantStorageQuota کنترل می‌کند.
        $path = $file?->store('group-chat/'.($data['type'] === GroupChatMessage::TYPE_VOICE ? 'voice' : 'images').'/'.now()->format('Y-m'), 'public');

        $user = $request->user();
        $message = GroupChatMessage::query()->create([
            'user_id' => $user->id,
            'sender_name' => $user->name,
            'type' => $data['type'],
            'body' => $body !== '' ? $body : null,
            'attachment_path' => $path ?: null,
            'attachment_mime' => $file?->getMimeType(),
            'attachment_size' => $file?->getSize(),
            'voice_duration' => $data['type'] === GroupChatMessage::TYPE_VOICE ? ($data['voice_duration'] ?? null) : null,
        ]);
        $message->setRelation('user', $user);

        // پیام خودِ فرستنده خوانده‌شده حساب می‌شود.
        DB::table('group_chat_reads')->updateOrInsert(
            ['user_id' => $user->id],
            ['last_read_message_id' => $message->id, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->broadcast('created', $message);

        return response()->json(['message' => $message->toChatArray()], 201);
    }

    public function update(Request $request, GroupChatMessage $message): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('group_chat.edit'), 403, 'اجازهٔ ویرایش پیام را ندارید.');
        abort_unless((int) $message->user_id === (int) $user->id, 403, 'فقط فرستنده می‌تواند پیام را ویرایش کند.');
        abort_if($message->isDeleted(), 422, 'پیام حذف‌شده قابل ویرایش نیست.');
        abort_if($message->type === GroupChatMessage::TYPE_VOICE, 422, 'پیام صوتی متن قابل ویرایش ندارد.');

        $data = $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:'.self::MAX_TEXT],
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        if ($message->type === GroupChatMessage::TYPE_TEXT && $body === '') {
            throw ValidationException::withMessages(['body' => 'متن پیام خالی است.']);
        }

        if ($body !== (string) $message->body) {
            $history = $message->edit_history ?? [];
            $history[] = ['body' => $message->body, 'replaced_at' => now()->toIso8601String()];
            $message->update([
                'body' => $body !== '' ? $body : null,
                'edit_history' => $history,
                'edited_at' => now(),
            ]);
            $this->broadcast('updated', $message->load('user'));
        }

        return response()->json(['message' => $message->load('user')->toChatArray()]);
    }

    public function destroy(Request $request, GroupChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $isOwner = (int) $message->user_id === (int) $user->id;
        abort_unless(
            ($isOwner && $user->can('group_chat.delete')) || $user->can('group_chat.moderate'),
            403,
            'اجازهٔ حذف این پیام را ندارید.',
        );

        if (! $message->isDeleted()) {
            // محتوا و فایل برای پیگیری سوءاستفاده نگه داشته می‌شوند و فقط از خروجی پنهان می‌شوند.
            $message->update(['deleted_at' => now(), 'deleted_by_user_id' => $user->id]);
            $this->broadcast('deleted', $message->load('user'));
        }

        return response()->json(['message' => $message->load('user')->toChatArray()]);
    }

    public function attachment(GroupChatMessage $message): Response
    {
        abort_if($message->isDeleted() || ! $message->attachment_path, 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($message->attachment_path), 404);

        return response()->file($disk->path($message->attachment_path), [
            'Content-Type' => $message->attachment_mime ?: ($disk->mimeType($message->attachment_path) ?: 'application/octet-stream'),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function permissions(Request $request): array
    {
        $user = $request->user();

        return [
            'send' => $user->can('group_chat.send'),
            'edit' => $user->can('group_chat.edit'),
            'delete' => $user->can('group_chat.delete'),
            'moderate' => $user->can('group_chat.moderate'),
        ];
    }

    private function unreadCount(Request $request): int
    {
        $userId = $request->user()->id;
        $lastRead = (int) DB::table('group_chat_reads')->where('user_id', $userId)->value('last_read_message_id');

        return GroupChatMessage::query()
            ->where('id', '>', $lastRead)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '<>', $userId))
            ->count();
    }

    private function broadcast(string $action, GroupChatMessage $message): void
    {
        try {
            broadcast(new GroupChatMessageChanged((string) tenant('id'), $action, $message->toChatArray()))->toOthers();
        } catch (\Throwable $exception) {
            // قطع بودن Reverb نباید ارسال پیام را خراب کند؛ کاربران دیگر با همگام‌سازی بعدی پیام را می‌بینند.
            report($exception);
        }
    }
}
