<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'presentation'];

    protected $casts = ['presentation' => 'array'];
}
