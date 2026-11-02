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
    // پیام صوتی موقتاً خاموش است؛ برای روشن کردن ASSISTANT_VOICE_ENABLED=true در .env بگذارید.
    'voice_enabled' => (bool) env('ASSISTANT_VOICE_ENABLED', false),
    'max_tool_rounds' => 4,
    'max_tool_calls' => 8,
    'max_audio_seconds' => 90,
    // فهرست‌های بزرگ‌تر از این تعداد کارت پرسش «چند مورد نشان بدهم؟» می‌گیرند و مدل فقط خلاصه را می‌بیند.
    'list_ask_threshold' => 30,
    // سقف محافظه‌کارانهٔ توکن صوتی در هر ثانیه برای رزرو هزینهٔ رونویسی، پیش از دریافت usage واقعی.
    'audio_tokens_per_second_bound' => 25,
];
