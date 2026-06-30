<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'photo_recognition' => [
        'provider' => Env::get('AI_PHOTO_RECOGNITION_PROVIDER', 'openai'),
        'enabled' => Env::bool('AI_PHOTO_RECOGNITION_ENABLED', true),
        'api_key' => Env::get('OPENAI_API_KEY', ''),
        'base_url' => Env::get('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => Env::get('OPENAI_VISION_MODEL', 'gpt-5.5'),
        'timeout_seconds' => (int) Env::get('OPENAI_TIMEOUT_SECONDS', 90),
        'max_images' => (int) Env::get('OPENAI_VISION_MAX_IMAGES', 8),
        'max_image_bytes' => (int) Env::get('OPENAI_VISION_MAX_IMAGE_BYTES', 20971520),
        'image_detail' => Env::get('OPENAI_VISION_DETAIL', 'original'),
        'web_search_enabled' => Env::bool('OPENAI_VISION_WEB_SEARCH_ENABLED', true),
        'reasoning_effort' => Env::get('OPENAI_REASONING_EFFORT', 'high'),
        'max_output_tokens' => (int) Env::get('OPENAI_VISION_MAX_OUTPUT_TOKENS', 4500),
    ],
];
