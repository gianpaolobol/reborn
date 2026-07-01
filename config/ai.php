<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'photo_recognition' => [
        // Step 50 provider values: auto, ocrspace, groq, openrouter, gemini, openai.
        'provider' => Env::get('AI_PHOTO_RECOGNITION_PROVIDER', 'auto'),
        'provider_order' => Env::csv('AI_VISION_PROVIDER_ORDER', ['ocrspace', 'groq', 'openrouter']),
        'enabled' => Env::bool('AI_PHOTO_RECOGNITION_ENABLED', true),

        // Step 47 / OpenAI Vision provider.
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


        // Step 50 — Cloud free multi-provider recognition.
        'ocrspace' => [
            'enabled' => Env::bool('OCRSPACE_ENABLED', true),
            'api_key' => Env::get('OCRSPACE_API_KEY', ''),
            'base_url' => Env::get('OCRSPACE_BASE_URL', 'https://api.ocr.space/parse/image'),
            'language' => Env::get('OCRSPACE_LANGUAGE', 'eng'),
            'ocr_engine' => (int) Env::get('OCRSPACE_ENGINE', 2),
            'timeout_seconds' => (int) Env::get('OCRSPACE_TIMEOUT_SECONDS', 45),
        ],
        'groq' => [
            'enabled' => Env::bool('GROQ_ENABLED', true),
            'api_key' => Env::get('GROQ_API_KEY', ''),
            'base_url' => Env::get('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => Env::get('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
            'timeout_seconds' => (int) Env::get('GROQ_TIMEOUT_SECONDS', 90),
            'max_output_tokens' => (int) Env::get('GROQ_MAX_OUTPUT_TOKENS', 2500),
            'temperature' => (float) Env::get('GROQ_TEMPERATURE', 0.1),
        ],
        'openrouter' => [
            'enabled' => Env::bool('OPENROUTER_ENABLED', true),
            'api_key' => Env::get('OPENROUTER_API_KEY', ''),
            'base_url' => Env::get('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model' => Env::get('OPENROUTER_VISION_MODEL', 'openrouter/free'),
            'timeout_seconds' => (int) Env::get('OPENROUTER_TIMEOUT_SECONDS', 90),
            'max_output_tokens' => (int) Env::get('OPENROUTER_MAX_OUTPUT_TOKENS', 2500),
            'temperature' => (float) Env::get('OPENROUTER_TEMPERATURE', 0.1),
        ],

        // Step 48.1 — Gemini-only Vision provider.
        'gemini' => [
            'api_key' => Env::get('GEMINI_API_KEY', ''),
            'base_url' => Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => Env::get('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
            'timeout_seconds' => (int) Env::get('GEMINI_TIMEOUT_SECONDS', 90),
            'max_output_tokens' => (int) Env::get('GEMINI_MAX_OUTPUT_TOKENS', 4096),
            'temperature' => (float) Env::get('GEMINI_TEMPERATURE', 0.1),
        ],
    ],
];
