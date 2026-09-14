# MailMigrator — Self‑Hosted IMAP Email Migration Tool

**MailMigrator** is a self‑hosted, open‑source platform for migrating email
between any two **IMAP** mailboxes — preserving every message's original
date, headers, flags, attachments, and folder structure. It ships as both a
**command‑line engine** and a lightweight **multi‑user web app** with a jobs
dashboard, background workers, and an optional paywall.

Move mailboxes between **Hostinger, Gmail / Google Workspace, Outlook /
Microsoft 365, cPanel/Dovecot, Yahoo, Zoho** — or any IMAP server.

> Keywords: imap migration, email migration tool, mailbox transfer,
> migrate emails between servers, self-hosted email migration, imap to imap,
> gmail migration, php email migration.

## Why MailMigrator

- **True IMAP → IMAP copy** via `APPEND` — keeps the original internal date,
  read/unread and flag state, attachments, and folder hierarchy. It does not
  re‑send mail (which would reset dates), and it **never deletes** anything.
- **Idempotent & resumable** — a per‑job ledger tracks every message, so runs
  are safe to repeat and pick up exactly where they left off after an
  interruption, throttle, or crash.
- **Smart de‑duplication** — skips messages already present at the destination
  (by `Message‑ID`, with a content‑hash fallback), so re‑running never
  duplicates mail.
- **Handles large mailboxes** — streaming per‑message transfer, batching, and
  throttling designed for 50k–500k+ message accounts.
- **Runs anywhere** — the web app is plain PHP (no heavy framework), built to
  run on cheap **shared hosting** with cron; the engine runs standalone from
  the CLI.

## Features

- IMAP→IMAP migration engine (PHP 8.5) with dry‑run, date‑range, folder
  scoping, and a safe `test‑connection → dry‑run → pilot → full` workflow.
- Gmail‑aware folder mapping (Sent / Drafts / Spam / Trash → `[Gmail]/…`) and
  Dovecot `.`→`/` hierarchy translation, all config‑overridable.
- Multi‑user web admin: register/login, create & manage migration jobs, live
  progress dashboard, per‑user isolation, encrypted credential storage.
- Background processing: cron‑driven worker that claims, runs, and resumes
  jobs with pause / resume / cancel and a concurrency cap — no daemons needed.
- Optional, fully env‑configurable **paywall**: free quota (jobs and/or emails)
  then pay via **PayPal** or **Razorpay** — one‑time unlock, credit packs, or
  subscription. Turn it off and everything is unlimited & free.
- Security‑first: credentials encrypted at rest (libsodium‑grade, via
  `defuse/php-encryption`), CSRF protection, argon2id password hashing,
  signature‑verified payment webhooks, and `.htaccess` hardening.

## Tech stack

PHP 8.5 · plain PHP (no framework) · PDO (MySQL in production, SQLite for
tests) · `webklex/php-imap` · `defuse/php-encryption` · `nikic/fast-route` ·
`vlucas/phpdotenv` · Tailwind + Alpine.js · PHPUnit.

## Quick start (CLI engine)

```bash
composer install
cp config/accounts.example.php config/accounts.php   # edit source + destination
php migrate.php --test-connection    # verify both accounts + preview folder map
php migrate.php --dry-run            # report what would copy; writes nothing
php migrate.php --folder="INBOX" --limit=5   # small pilot; eyeball the result
php migrate.php                      # full migration (resumable — rerun anytime)
```

For Gmail/Workspace: enable IMAP + 2FA and use a 16‑char **app password**.

CLI options: `--config=PATH` · `--dry-run` · `--folder=NAME` ·
`--since=YYYY-MM-DD` · `--limit=N` · `--verbose`
(`--retry-failed` is planned).

## Quick start (web app)

```bash
composer install
cp .env.example .env      # set DB_*, generate APP_KEY, configure paywall (optional)
php bin/migrate.php       # create the database schema
# point your web server's document root at public/  (or use the bundled .htaccess)
```

Then register an account, create a job, and (with the worker cron running)
watch it migrate live on the dashboard. Background worker:

```
* * * * * /usr/bin/php /path/to/app/bin/worker.php >> worker.log 2>&1
```

See **[docs/deploy-shared-hosting.md](docs/deploy-shared-hosting.md)** for a
full shared‑hosting deployment guide (PHP version, MySQL, `APP_KEY`,
docroot, cron, paywall, and PayPal/Razorpay webhook setup).

## Architecture

- `src/` — the framework‑agnostic migration **engine** (`EmailMigration\`).
- `app/` — the plain‑PHP **web application** (`App\`): front controller,
  router, controllers, repositories, services, auth, billing.
- `bin/` — CLI entry points (`migrate.php` engine, `worker.php` cron worker,
  `migrate.php` schema migrator).
- `database/migrations/` — portable SQL schema.
- `public/` — web document root.

## Security

Read‑only on the source, append‑only on the destination — mail is never
deleted. Stored IMAP credentials are encrypted at rest with a key that lives
only in your `.env`. HTTPS, CSRF tokens, argon2id hashing, per‑user
authorization, and signature‑verified webhooks are built in. Review the deploy
guide before going live; you are hosting other people's live email
credentials, so protect the `.env` key and use HTTPS.

## Tests

```bash
vendor/bin/phpunit --testdox
```

## License

See [LICENSE](LICENSE).
