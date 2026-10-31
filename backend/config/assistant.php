<?php

return [
    'api_key' => env('OPENAI_API_KEY', ''),
    'base_url' => 'https://api.openai.com/v1',
    'models' => [
        'gpt-6-luna' => 'سریع و کم‌هزینه',
        'gpt-6.1-sol' => 'دقیق و متعادل',
        'gpt-6.1-terra' => 'نگارش و تحلیل',
    ],
    'default_model' => env('ASSISTANT_DEFAULT_MODEL', 'gpt-6-luna'),
    'transcription_model' => 'gpt-transcribe',
    'max_tool_rounds' => 4,
    'max_tool_calls' => 8,
    'max_messages_per_day' => 80,
    'max_audio_seconds' => 90,
];
