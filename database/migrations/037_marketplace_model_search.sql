CREATE TABLE IF NOT EXISTS marketplace_model_searches (
  id TEXT PRIMARY KEY,
  recognition_job_id TEXT NOT NULL,
  repair_case_id TEXT NOT NULL,
  status TEXT NOT NULL,
  query_json TEXT NOT NULL DEFAULT '{}',
  result_summary_json TEXT,
  created_at TEXT NOT NULL,
  completed_at TEXT
);

CREATE TABLE IF NOT EXISTS marketplace_model_candidates (
  id TEXT PRIMARY KEY,
  marketplace_search_id TEXT NOT NULL,
  source TEXT NOT NULL,
  title TEXT NOT NULL,
  url TEXT NOT NULL,
  thumbnail_url TEXT NOT NULL DEFAULT '',
  score INTEGER NOT NULL DEFAULT 0,
  license TEXT NOT NULL DEFAULT '',
  download_available INTEGER NOT NULL DEFAULT 0,
  file_type TEXT NOT NULL DEFAULT '',
  match_reason TEXT NOT NULL DEFAULT '',
  raw_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_marketplace_model_searches_recognition_job ON marketplace_model_searches (recognition_job_id);
CREATE INDEX IF NOT EXISTS idx_marketplace_model_searches_repair_case ON marketplace_model_searches (repair_case_id);
CREATE INDEX IF NOT EXISTS idx_marketplace_model_candidates_search ON marketplace_model_candidates (marketplace_search_id);
