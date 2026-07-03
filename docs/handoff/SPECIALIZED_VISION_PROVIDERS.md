# Re-born - Specialized Trainable Vision Providers

This module adds optional backend-only adapters for trainable/specialized computer vision providers:

- Amazon Rekognition Custom Labels (`awsrekognition`)
- Clarifai custom visual models (`clarifai`)
- Roboflow Hosted Inference (`roboflow`)

These providers are meant for objects that generic multimodal models may miss. Train the model in the provider platform first, then configure the model identifier/ARN/version in `.env`.

## Provider order examples

Use the specialized providers before generic models when you have trained classes for the target domain:

```env
AI_VISION_PROVIDER_ORDER=awsrekognition,clarifai,roboflow,ocrspace,groq,openrouter,gemini
```

For a single provider test:

```env
AI_VISION_PROVIDER_ORDER=roboflow
ROBOFLOW_ENABLED=true
```

## Security

All keys stay in `.env`. The browser never calls AWS, Clarifai or Roboflow directly.

Flow:

```text
Browser -> Re-born PHP backend -> specialized provider -> normalized JSON -> minimal UI
```

## Acceptance

A specialized provider is considered successful only when it returns at least one class/detection above its configured confidence threshold. Re-born then normalizes the custom class into the existing recognition schema used by the marketplace finder.

## Test

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke-specialized-vision-providers.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\debug-ai-photo-recognition-live.ps1 -BaseUrl http://127.0.0.1:8080 -ImagePath "C:\Users\LUISA\Downloads\foto.jpg"
```
