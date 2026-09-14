CREATE TABLE payments (
  id {{PK}}, user_id BIGINT NOT NULL, gateway VARCHAR(16) NOT NULL,
  product VARCHAR(16) NOT NULL, gateway_ref VARCHAR(255),
  amount VARCHAR(32) NOT NULL, currency VARCHAR(8) NOT NULL,
  status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
) {{ENGINE}};
CREATE INDEX idx_payments_user ON payments(user_id);
CREATE UNIQUE INDEX uq_payments_ref ON payments(gateway, gateway_ref);
CREATE TABLE entitlements (
  user_id BIGINT PRIMARY KEY, unlimited INTEGER NOT NULL DEFAULT 0,
  credits INTEGER NOT NULL DEFAULT 0, subscription_until DATETIME NULL,
  updated_at DATETIME NOT NULL
) {{ENGINE}};
