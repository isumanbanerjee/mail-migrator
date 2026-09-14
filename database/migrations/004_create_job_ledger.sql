CREATE TABLE job_ledger_messages (
  id {{PK}}, job_id BIGINT NOT NULL, source_folder VARCHAR(512) NOT NULL,
  dest_folder VARCHAR(512) NOT NULL, source_uid BIGINT NOT NULL,
  message_id VARCHAR(998), dedupe_hash CHAR(64), size_bytes BIGINT,
  internal_date DATETIME, flags TEXT, status VARCHAR(16) NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0, error TEXT,
  created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
) {{ENGINE}};
CREATE UNIQUE INDEX uq_job_src ON job_ledger_messages(job_id, source_folder, source_uid);
CREATE INDEX idx_jlm_status ON job_ledger_messages(job_id, status);
CREATE TABLE job_ledger_folders (
  id {{PK}}, job_id BIGINT NOT NULL, source_folder VARCHAR(512),
  dest_folder VARCHAR(512), uidvalidity BIGINT, scanned_at DATETIME
) {{ENGINE}};
CREATE UNIQUE INDEX uq_job_folder ON job_ledger_folders(job_id, source_folder);
