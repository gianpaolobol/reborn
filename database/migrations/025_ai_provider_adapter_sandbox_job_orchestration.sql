CREATE TABLE IF NOT EXISTS platform_ai_provider_adapters (
    id TEXT PRIMARY KEY,
    adapter_key TEXT NOT NULL UNIQUE,
    provider_key TEXT NOT NULL,
    name TEXT NOT NULL,
    capability TEXT NOT NULL,
    mode TEXT NOT NULL DEFAULT 'mock',
    status TEXT NOT NULL DEFAULT 'sandbox',
    requires_secret INTEGER NOT NULL DEFAULT 0,
    secret_status TEXT NOT NULL DEFAULT 'not_required',
    daily_budget_cents INTEGER NOT NULL DEFAULT 0,
    cost_per_job_cents INTEGER NOT NULL DEFAULT 0,
    concurrency_limit INTEGER NOT NULL DEFAULT 1,
    timeout_seconds INTEGER NOT NULL DEFAULT 60,
    retry_limit INTEGER NOT NULL DEFAULT 2,
    last_health_status TEXT,
    last_checked_at TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS platform_ai_orchestration_jobs (
    id TEXT PRIMARY KEY,
    job_code TEXT NOT NULL UNIQUE,
    adapter_key TEXT NOT NULL,
    pipeline_run_id TEXT,
    job_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    priority INTEGER NOT NULL DEFAULT 50,
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 2,
    input_summary TEXT NOT NULL,
    provider_request_ref TEXT,
    provider_response_ref TEXT,
    estimated_cost_cents INTEGER NOT NULL DEFAULT 0,
    actual_cost_cents INTEGER NOT NULL DEFAULT 0,
    error_message TEXT,
    scheduled_at TEXT,
    started_at TEXT,
    finished_at TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_orchestration_jobs_status ON platform_ai_orchestration_jobs(status);
CREATE INDEX IF NOT EXISTS idx_platform_ai_orchestration_jobs_adapter ON platform_ai_orchestration_jobs(adapter_key);

CREATE TABLE IF NOT EXISTS platform_ai_job_events (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL,
    event_type TEXT NOT NULL,
    message TEXT NOT NULL,
    payload_json TEXT,
    actor_user_id TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_job_events_job ON platform_ai_job_events(job_id, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_artifact_stubs (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL,
    artifact_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'placeholder',
    storage_ref TEXT,
    review_required INTEGER NOT NULL DEFAULT 1,
    checksum TEXT,
    metadata_json TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_artifact_stubs_job ON platform_ai_artifact_stubs(job_id);

CREATE TABLE IF NOT EXISTS platform_ai_provider_cost_ledger (
    id TEXT PRIMARY KEY,
    adapter_key TEXT NOT NULL,
    job_id TEXT,
    amount_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    direction TEXT NOT NULL DEFAULT 'reserved',
    description TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_platform_ai_provider_cost_ledger_adapter ON platform_ai_provider_cost_ledger(adapter_key, created_at);

CREATE TABLE IF NOT EXISTS platform_ai_provider_sandbox_audit_log (
    id TEXT PRIMARY KEY,
    action TEXT NOT NULL,
    subject_type TEXT,
    subject_id TEXT,
    message TEXT NOT NULL,
    metadata_json TEXT,
    actor_user_id TEXT,
    created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO platform_ai_provider_adapters (id, adapter_key, provider_key, name, capability, mode, status, requires_secret, secret_status, daily_budget_cents, cost_per_job_cents, concurrency_limit, timeout_seconds, retry_limit, notes, created_at, updated_at)
VALUES
('adapter-mock-meshy', 'mock_meshy_image_to_3d', 'meshy', 'Meshy Image-to-3D Sandbox Adapter', 'image_to_3d_model', 'mock', 'sandbox', 1, 'missing', 1200, 180, 1, 120, 2, 'Sandbox adapter for future Meshy API calls. No external request is made in Step 31.', datetime('now'), datetime('now')),
('adapter-mock-trellis', 'mock_trellis_local_3d', 'trellis', 'Trellis Local GPU Sandbox Adapter', 'local_3d_generation', 'mock', 'sandbox', 0, 'not_required', 0, 0, 1, 900, 1, 'Sandbox adapter for a future local Trellis worker. It is disabled from real execution until a worker machine is configured.', datetime('now'), datetime('now')),
('adapter-mock-rodin', 'mock_rodin_mesh_refine', 'rodin', 'Rodin Mesh Refinement Sandbox Adapter', 'mesh_refinement', 'mock', 'sandbox', 1, 'missing', 1500, 220, 1, 180, 2, 'Sandbox adapter for future Rodin-style mesh refinement workflows.', datetime('now'), datetime('now')),
('adapter-mock-diagnosis', 'mock_repair_diagnosis_llm', 'reborn_internal', 'Repair Diagnosis LLM Sandbox Adapter', 'repair_diagnosis', 'mock', 'sandbox', 0, 'not_required', 500, 35, 2, 45, 2, 'Mock adapter for diagnosis explainability and repair-path support.', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_orchestration_jobs (id, job_code, adapter_key, pipeline_run_id, job_type, status, priority, attempts, max_attempts, input_summary, estimated_cost_cents, actual_cost_cents, scheduled_at, created_at, updated_at)
VALUES
('job-ai-sandbox-demo-001', 'AI-JOB-DEMO-001', 'mock_meshy_image_to_3d', NULL, 'image_to_3d_model', 'queued', 40, 0, 2, 'Demo sandbox job: generate a controlled pilot 3D concept from repair photos. No external provider is called.', 180, 0, datetime('now'), datetime('now'), datetime('now'));

INSERT OR IGNORE INTO platform_ai_job_events (id, job_id, event_type, message, payload_json, created_at)
VALUES
('event-ai-sandbox-demo-001', 'job-ai-sandbox-demo-001', 'job_queued', 'Demo AI sandbox job queued by Step 31 migration.', '{"source":"migration_025","external_calls":false}', datetime('now'));
