CREATE TABLE login_throttle (
  id {{PK}},
  throttle_key VARCHAR(255) NOT NULL UNIQUE,
  attempts INTEGER NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL
) {{ENGINE}};
