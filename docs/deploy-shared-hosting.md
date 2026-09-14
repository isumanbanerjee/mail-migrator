# Deploying the web admin to shared hosting

This guide covers deploying the plain-PHP web admin (Sub-project 2) to a
typical shared-hosting cPanel-style host (Namecheap, Hostinger, SiteGround,
etc.) that offers a PHP version selector, MySQL databases, and either SSH/Git
access or a file manager.

## 1. Select PHP 8.5

In the host's control panel ("MultiPHP Manager", "Select PHP Version", or
similar), set the domain/subdomain to **PHP 8.5**. The app relies on modern
PHP (readonly properties, enums, `PDO`) — running under an older PHP version
will fail during `composer install` or at runtime. If your host only offers
"PHP 8.5 (FPM)" or "PHP 8.5 (CGI)", either works.

## 2. Create a MySQL database and user

1. In the panel's "MySQL Databases" tool, create a new database, e.g.
   `youracct_emailmigration`.
2. Create a database user with a strong, generated password.
3. Add the user to the database with **all privileges**.
4. Note the host (usually `localhost` or `127.0.0.1`), database name,
   username, and password — you'll need these for `.env`.

## 3. Build locally and upload

Composer is frequently unavailable (or too old) on shared hosts, so build the
vendor directory **locally** and upload it:

```bash
composer install --no-dev --optimize-autoloader
```

Upload the whole repository (including the freshly built `vendor/` directory)
to the host. Two common approaches:

- **Git deploy** (if the host supports it): push to a bare repo on the host
  and let a post-receive hook check out the working tree, then run the
  `composer install` above on the server if Composer is available there —
  otherwise upload `vendor/` separately via SFTP.
- **SFTP/file manager upload**: zip the repo (including `vendor/`), upload,
  and extract it in place on the server.

Either way, end up with the full repo — including `vendor/`, `app/`, `views/`,
`public/`, `database/`, `config/`, `bin/`, and this `docs/` folder — under one
directory on the host (e.g. `~/email-migration/`).

## 4. Configure `.env` and generate `APP_KEY`

Copy the example environment file and edit it:

```bash
cp .env.example .env
```

Set the database credentials from step 2:

```
APP_ENV=production
APP_KEY=
APP_URL=https://your-domain.example
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=youracct_emailmigration
DB_USERNAME=youracct_dbuser
DB_PASSWORD=your-generated-password
```

Generate a fresh `APP_KEY` (used to encrypt IMAP credentials at rest — do not
reuse a key across environments, and do not commit it):

```bash
php -r "require 'vendor/autoload.php'; echo Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();"
```

Copy the printed string into `APP_KEY=` in `.env`. You can run this command
either locally (then upload the resulting `.env`) or directly on the server
via SSH if available.

## 5. Run database migrations

The repo ships a small one-shot script that boots the app's `Migrator` and
applies any pending SQL files under `database/migrations/`:

```bash
php bin/migrate.php
```

This is idempotent — safe to re-run after future deploys; it only applies
migrations that haven't been recorded in the `migrations` table yet. If your
host doesn't offer shell/SSH access, some panels expose a "Run PHP script"
tool, or you can temporarily add a guarded route/CLI trigger — just make sure
`bin/` is not web-reachable (see step 8) so this never becomes a public
endpoint.

## 6. Point the docroot at `public/`

The web admin's front controller lives in `public/index.php`, and only files
under `public/` (including `public/assets/app.css` and
`public/assets/alpine.min.js`) are meant to be served directly.

- **Preferred:** if your host lets you set a custom document root per
  domain/subdomain, point it at the `public/` subdirectory of the uploaded
  repo (e.g. `~/email-migration/public`). This is the most robust option —
  everything outside `public/` (source code, `vendor/`, `.env`, migrations,
  tests) is then simply outside the web server's document root and cannot be
  requested at all.
- **Fallback:** if the host forces the docroot to the account/domain root
  (common on basic shared-hosting plans), upload the repo so that its root
  lands at that docroot, and rely on the committed root `.htaccess` (see step
  8) to rewrite all requests into `public/` and to `RedirectMatch 404` any
  direct request for sensitive paths.

## 7. Outbound IMAP (port 993) caveat

The "Test Connection" feature and the migration job itself connect outbound
to the source/destination mail servers over IMAP, normally on port **993**
(implicit TLS). Some budget shared-hosting plans firewall outbound
connections on non-standard ports, or only allow a short allowlist (80/443).

Before relying on the admin in production:

1. Create a job and use **Test Connection** against both the source and
   destination mailboxes.
2. If it fails with a connection/timeout error (rather than an
   authentication error), ask the host's support to confirm outbound port
   993 (and 143 if you use STARTTLS) is open, or whether IMAP traffic must go
   through a specific egress IP that needs mail-server-side allowlisting.
3. As a workaround on hosts that block it entirely, run the CLI migration
   tool (`migrate.php` at the repo root — the standalone email migration
   engine, not `bin/migrate.php`) from a machine/VPS with unrestricted
   outbound access instead of from the shared host.

## 8. Confirm `.env` (and other sensitive paths) are not web-readable

The committed root `.htaccess` does two things:

```apache
# Forward all requests into the public/ directory
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(?!public/)(.*)$ public/$1 [L]
</IfModule>

# Deny direct web access to sensitive paths
RedirectMatch 404 ^/(app|src|config|database|tests|vendor|docs|bin|\.env|composer\.(json|lock))
```

After deploying, verify from outside (e.g. `curl -i https://your-domain.example/.env`)
that these all return **404**, not the file contents:

- `/.env`
- `/composer.json` and `/composer.lock`
- `/app/...`, `/config/...`, `/database/...`, `/vendor/...`, `/tests/...`,
  `/bin/...`, `/docs/...`

If you used the "preferred" docroot-at-`public/` approach from step 6, these
paths are already outside the document root and unreachable regardless — the
`RedirectMatch` rule is then a defense-in-depth backstop for the "fallback"
docroot-at-root setup, and for hosts where the rewrite forwarder is in
effect. Do not skip verifying this in production, since a leaked `.env` would
expose the `APP_KEY` used to decrypt stored IMAP credentials.

## 9. Background worker (cron)

To process migration jobs asynchronously, set up a cron job that invokes the
worker CLI every minute:

```
* * * * * /usr/bin/php /home/USER/app/bin/worker.php >> /home/USER/app/worker.log 2>&1
```

Adjust the PHP path to match your host's environment (e.g. `/usr/bin/php8.5`
or `/opt/alt/php85/usr/bin/php` on cPanel) and replace `/home/USER/app` with
the actual path to your deployed repository.

### How it works

Each minute, the cron invocation starts the worker:

1. The worker claims a single queued job from the database, respecting the
   `MAX_CONCURRENT_JOBS` concurrency cap to prevent too many parallel
   migrations.
2. It runs the migration for up to `WORKER_MAX_SECONDS` (default: 50 seconds),
   periodically writing progress updates to the job ledger.
3. After the time budget expires or the migration completes, the worker writes
   a final state update and exits.
4. On the next cron tick (within one minute), a new worker invocation claims
   the job and resumes from where the previous run stopped, using the ledger
   to track progress across runs.

### Stale job recovery

If a worker process crashes or is killed, its lock on the job is held. The
worker pool automatically reclaims jobs whose locks are older than
`WORKER_STALE_SECONDS` (default: 900 seconds, or 15 minutes), allowing the
next worker invocation to claim and resume the job.

### Configure worker timeout

Edit `.env` to adjust the concurrency and timeout settings:

```
MAX_CONCURRENT_JOBS=1
WORKER_STALE_SECONDS=900
WORKER_MAX_SECONDS=50
```

- `MAX_CONCURRENT_JOBS` (int): maximum number of jobs running at once. On
  shared hosting with one cron tick per minute, `1` is typical (each minute's
  worker focuses on a single job).
- `WORKER_STALE_SECONDS` (int): seconds after which a locked job is deemed
  stale and reclaimed for re-processing by the next worker.
- `WORKER_MAX_SECONDS` (int): soft time budget (in seconds) per worker
  invocation. The worker stops gracefully before the Linux kernel's
  `max_execution_time` would kill the PHP process, allowing safe progress
  updates.

Note: the CLI (`bin/worker.php`) runs outside the web server's
`max_execution_time` limit, so long-running migrations (even >30 seconds per
message) are safe as long as they stay within the `WORKER_MAX_SECONDS` budget.
