<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupChatMessage extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_VOICE = 'voice';
    public const TYPE_IMAGE = 'image';

    public const DELETED_TEXT = 'این پیام حذف شده است';

    protected $fillable = [
        'user_id',
        'sender_name',
        'type',
        'body',
        'attachment_path',
        'attachment_mime',
        'attachment_size',
        'voice_duration',
        'edit_history',
        'edited_at',
        'deleted_at',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'attachment_size' => 'integer',
            'voice_duration' => 'integer',
            'edit_history' => 'array',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'deleted_by_user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Viewer-independent payload, shared by the API and the broadcast event.
     * A deleted message never exposes its text or attachment.
     */
    public function toChatArray(): array
    {
        $deleted = $this->isDeleted();
        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $deleted ? null : $this->body,
            'attachment_url' => ! $deleted && $this->attachment_path
                ? '/api/group-chat/messages/'.$this->id.'/attachment?v='.substr(sha1((string) $this->attachment_path), 0, 10)
                : null,
            'voice_duration' => $deleted ? null : $this->voice_duration,
            'is_deleted' => $deleted,
            'deleted_text' => $deleted ? self::DELETED_TEXT : null,
            'is_edited' => ! $deleted && $this->edited_at !== null,
            'edited_at' => $deleted ? null : $this->edited_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'sender' => [
                'id' => $this->user_id,
                'name' => $user?->name ?: ($this->sender_name ?: 'کاربر حذف‌شده'),
                'avatar_url' => $user?->avatar_url,
            ],
        ];
    }
}
