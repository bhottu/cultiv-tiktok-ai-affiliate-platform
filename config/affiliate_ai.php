<?php

return [
    'brand' => [
        'name' => 'Plant Coaction',
        'app_name' => 'Plant Coaction Affiliate AI',
        'tiktok_handle' => '@plant.coaction',
        'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
        'locale' => env('APP_LOCALE', 'id'),
        'operational_mode' => 'semi_automatic',
    ],

    'ai' => [
        'default_provider' => env('AI_DEFAULT_PROVIDER', 'openrouter'),
        'fallback_enabled' => (bool) env('AI_FALLBACK_ENABLED', false),
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY', ''),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'default_model' => env('OPENROUTER_DEFAULT_MODEL', ''),
            'fallback_model' => env('OPENROUTER_FALLBACK_MODEL', ''),
            'timeout' => (int) env('OPENROUTER_TIMEOUT', 30),
        ],
        'google_gemini' => [
            'api_key' => env('GEMINI_API_KEY', ''),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', ''),
            'timeout' => (int) env('GEMINI_TIMEOUT', 30),
            'max_retries' => (int) env('GEMINI_MAX_RETRIES', 1),
        ],
    ],

    'tts' => [
        'default_provider' => env('TTS_DEFAULT_PROVIDER', 'google_gemini_tts'),
        'supported_providers' => ['google_gemini_tts', 'google_cloud_tts', 'azure_speech', 'elevenlabs'],
        'google_gemini_tts' => [
            'default_model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
            'default_voice' => env('GEMINI_TTS_VOICE', 'Kore'),
            'language_code' => 'id-ID',
            'timeout' => (int) env('GEMINI_TTS_TIMEOUT', 45),
            'max_retries' => (int) env('GEMINI_TTS_MAX_RETRIES', 1),
            'max_input_chars' => 3500,
        ],
        'google_cloud_tts' => [
            'api_key' => env('GOOGLE_TTS_API_KEY', ''),
            'endpoint' => env('GOOGLE_TTS_ENDPOINT', 'https://texttospeech.googleapis.com/v1'),
            'language_code' => env('GOOGLE_TTS_LANGUAGE_CODE', 'id-ID'),
            'voice_name' => env('GOOGLE_TTS_VOICE_NAME', ''),
            'speaking_rate' => (float) env('GOOGLE_TTS_SPEAKING_RATE', 1.0),
            'pitch' => (float) env('GOOGLE_TTS_PITCH', 0.0),
            'volume_gain_db' => (float) env('GOOGLE_TTS_VOLUME_GAIN_DB', 0.0),
        ],
    ],

    'video' => [
        'renderer' => 'remotion',
        'remotion_project_path' => env('REMOTION_PROJECT_PATH', 'remotion'),
        'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
    ],

    'tiktok' => [
        'enabled_in_phase_2' => true,
        'oauth_platform' => env('TIKTOK_OAUTH_PLATFORM', 'web'),
        'client_key' => env('TIKTOK_CLIENT_KEY', ''),
        'client_secret' => env('TIKTOK_CLIENT_SECRET', ''),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/dashboard/tiktok/callback'),
        'scopes' => env('TIKTOK_OAUTH_SCOPES', 'user.info.basic'),
        'auth_base_url' => env('TIKTOK_AUTH_BASE_URL', 'https://www.tiktok.com/v2/auth/authorize/'),
        'api_base_url' => env('TIKTOK_API_BASE_URL', 'https://open.tiktokapis.com/v2'),
        'environment' => env('TIKTOK_ENVIRONMENT', 'sandbox'),
        'shop_affiliate' => [
            'app_key' => env('TIKTOK_SHOP_APP_KEY', ''),
            'app_secret' => env('TIKTOK_SHOP_APP_SECRET', ''),
            'access_token' => env('TIKTOK_SHOP_ACCESS_TOKEN', ''),
            'api_base_url' => env('TIKTOK_SHOP_API_BASE_URL', 'https://open-api.tiktokglobalshop.com'),
            'region' => env('TIKTOK_SHOP_REGION', 'ID'),
        ],
    ],
];
