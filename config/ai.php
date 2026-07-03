<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'photo_recognition' => [
        // Step 50+ provider values: auto, ocrspace, groq, openrouter, gemini, openai, awsrekognition, clarifai, roboflow.
        'provider' => Env::get('AI_PHOTO_RECOGNITION_PROVIDER', 'auto'),
        'provider_order' => Env::csv('AI_VISION_PROVIDER_ORDER', ['ocrspace', 'gemini', 'groq']),
        'enabled' => Env::bool('AI_PHOTO_RECOGNITION_ENABLED', true),

        // Recognition Quality Gate.
        // Cheap/generic providers can return a preliminary result, but final
        // recognition requires a high confidence score or verification by a
        // stronger provider such as Gemini/OpenAI/specialized custom vision.
        'quality_gate' => [
            'enabled' => Env::bool('AI_RECOGNITION_QUALITY_GATE_ENABLED', true),
            'minimum_final_score' => (int) Env::get('AI_RECOGNITION_MIN_FINAL_SCORE', 82),
            'minimum_preliminary_score' => (int) Env::get('AI_RECOGNITION_MIN_PRELIMINARY_SCORE', 58),
            'provider_confidence_threshold' => (float) Env::get('AI_RECOGNITION_PROVIDER_CONFIDENCE_THRESHOLD', 0.82),
            'ignore_low_trust_self_confidence' => Env::bool('AI_RECOGNITION_IGNORE_LOW_TRUST_SELF_CONFIDENCE', true),
            'low_trust_max_score' => (int) Env::get('AI_RECOGNITION_LOW_TRUST_MAX_SCORE', 79),
            'require_trusted_verifier_for_final' => Env::bool('AI_RECOGNITION_REQUIRE_TRUSTED_VERIFIER_FOR_FINAL', true),
            'low_trust_providers' => Env::csv('AI_RECOGNITION_LOW_TRUST_PROVIDERS', ['ocrspace', 'groq', 'openrouter']),
            'verifier_providers' => Env::csv('AI_RECOGNITION_VERIFIER_PROVIDERS', ['gemini']),
            'trusted_final_providers' => Env::csv('AI_RECOGNITION_TRUSTED_FINAL_PROVIDERS', ['gemini']),
            'verify_when_code_visible' => Env::bool('AI_RECOGNITION_VERIFY_WHEN_CODE_VISIBLE', true),
            'verify_when_generic_part' => Env::bool('AI_RECOGNITION_VERIFY_WHEN_GENERIC_PART', true),
            'verify_when_missing_function' => Env::bool('AI_RECOGNITION_VERIFY_WHEN_MISSING_FUNCTION', true),
            'accept_preliminary_when_verifier_unavailable' => Env::bool('AI_RECOGNITION_ACCEPT_PRELIMINARY_WHEN_VERIFIER_UNAVAILABLE', true),
        ],

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
            'enabled' => Env::bool('OPENROUTER_ENABLED', false),
            'api_key' => Env::get('OPENROUTER_API_KEY', ''),
            'base_url' => Env::get('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model' => Env::get('OPENROUTER_VISION_MODEL', 'openrouter/free'),
            'timeout_seconds' => (int) Env::get('OPENROUTER_TIMEOUT_SECONDS', 90),
            'max_output_tokens' => (int) Env::get('OPENROUTER_MAX_OUTPUT_TOKENS', 2500),
            'temperature' => (float) Env::get('OPENROUTER_TEMPERATURE', 0.1),
        ],

        // Step 48.1 — Gemini-only Vision provider.
        'gemini' => [
            'enabled' => Env::bool('GEMINI_ENABLED', true),
            'api_key' => Env::get('GEMINI_API_KEY', ''),
            'base_url' => Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => Env::get('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
            'timeout_seconds' => (int) Env::get('GEMINI_TIMEOUT_SECONDS', 90),
            'max_output_tokens' => (int) Env::get('GEMINI_MAX_OUTPUT_TOKENS', 4096),
            'temperature' => (float) Env::get('GEMINI_TEMPERATURE', 0.1),
        ],

        // Specialized/trainable computer vision providers.
        // These providers are intended for parts that generic vision models miss.
        // Train the model in the provider console, then configure the model id/ARN/version here via .env.
        'awsrekognition' => [
            'enabled' => Env::bool('AWS_REKOGNITION_ENABLED', false),
            'access_key_id' => Env::get('AWS_REKOGNITION_ACCESS_KEY_ID', ''),
            'secret_access_key' => Env::get('AWS_REKOGNITION_SECRET_ACCESS_KEY', ''),
            'session_token' => Env::get('AWS_REKOGNITION_SESSION_TOKEN', ''),
            'region' => Env::get('AWS_REKOGNITION_REGION', 'eu-west-1'),
            'project_version_arn' => Env::get('AWS_REKOGNITION_PROJECT_VERSION_ARN', ''),
            'base_url' => Env::get('AWS_REKOGNITION_BASE_URL', ''),
            'timeout_seconds' => (int) Env::get('AWS_REKOGNITION_TIMEOUT_SECONDS', 45),
            'max_results' => (int) Env::get('AWS_REKOGNITION_MAX_RESULTS', 10),
            'min_confidence' => (float) Env::get('AWS_REKOGNITION_MIN_CONFIDENCE', 55),
            'possible_brands' => Env::get('AWS_REKOGNITION_POSSIBLE_BRANDS', ''),
            'appliance_context' => Env::get('AWS_REKOGNITION_APPLIANCE_CONTEXT', 'Da confermare.'),
        ],
        'clarifai' => [
            'enabled' => Env::bool('CLARIFAI_ENABLED', false),
            'api_key' => Env::get('CLARIFAI_API_KEY', ''),
            'base_url' => Env::get('CLARIFAI_BASE_URL', 'https://api.clarifai.com'),
            'user_id' => Env::get('CLARIFAI_USER_ID', ''),
            'app_id' => Env::get('CLARIFAI_APP_ID', ''),
            'model_id' => Env::get('CLARIFAI_MODEL_ID', ''),
            'model_version_id' => Env::get('CLARIFAI_MODEL_VERSION_ID', ''),
            'timeout_seconds' => (int) Env::get('CLARIFAI_TIMEOUT_SECONDS', 45),
            'min_confidence' => (float) Env::get('CLARIFAI_MIN_CONFIDENCE', 0.5),
            'possible_brands' => Env::get('CLARIFAI_POSSIBLE_BRANDS', ''),
            'appliance_context' => Env::get('CLARIFAI_APPLIANCE_CONTEXT', 'Da confermare.'),
        ],
        'roboflow' => [
            'enabled' => Env::bool('ROBOFLOW_ENABLED', false),
            'mode' => Env::get('ROBOFLOW_MODE', ''),
            'api_key' => Env::get('ROBOFLOW_API_KEY', ''),
            'base_url' => Env::get('ROBOFLOW_BASE_URL', 'https://detect.roboflow.com'),
            'model_id' => Env::get('ROBOFLOW_MODEL_ID', ''),
            'serverless_url' => Env::get('ROBOFLOW_SERVERLESS_URL', 'https://serverless.roboflow.com'),
            'workspace' => Env::get('ROBOFLOW_WORKSPACE', ''),
            'workflow_id' => Env::get('ROBOFLOW_WORKFLOW_ID', ''),
            'workflow_classes' => Env::get('ROBOFLOW_WORKFLOW_CLASSES', 'strap buckle, ladder lock buckle, bicycle bottom bracket support, washing machine knob, plastic clip, hinge, bracket, connector, handle, screw, knob, unknown part'),
            'timeout_seconds' => (int) Env::get('ROBOFLOW_TIMEOUT_SECONDS', 45),
            'confidence' => (int) Env::get('ROBOFLOW_CONFIDENCE', 40),
            'overlap' => (int) Env::get('ROBOFLOW_OVERLAP', 30),
            'min_confidence' => (float) Env::get('ROBOFLOW_MIN_CONFIDENCE', 0.5),
            'payload_mode' => Env::get('ROBOFLOW_PAYLOAD_MODE', 'base64_form'),
            'possible_brands' => Env::get('ROBOFLOW_POSSIBLE_BRANDS', ''),
            'appliance_context' => Env::get('ROBOFLOW_APPLIANCE_CONTEXT', 'Da confermare.'),
        ],
    ],
];
