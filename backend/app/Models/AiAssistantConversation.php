<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAssistantConversation extends Model
{
    protected $fillable = ['user_id', 'title', 'model'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiAssistantMessage::class, 'conversation_id');
    }
}
