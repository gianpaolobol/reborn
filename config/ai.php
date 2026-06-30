<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'photo_recognition' => [
        'provider' => Env::get('AI_PHOTO_RECOGNITION_PROVIDER', 'openai'),
        'enabled' => Env::bool('AI_PHOTO_RECOGNITION_ENABLED', true),
        'api_key' => Env::get('OPENAI_API_KEY', ''),
        'base_url' => Env::get('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => Env::get('OPENAI_VISION_MODEL', 'gpt-5.4-mini'),
        'timeout_seconds' => (int) Env::get('OPENAI_TIMEOUT_SECONDS', 30),
        'max_images' => (int) Env::get('OPENAI_VISION_MAX_IMAGES', 3),
        'max_image_bytes' => (int) Env::get('OPENAI_VISION_MAX_IMAGE_BYTES', 5242880),
    ],
];
