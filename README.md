# email-migration

Resumable IMAP-to-IMAP email migration engine (PHP 8.5).

Copies messages from a source IMAP account to a destination IMAP account,
preserving original date, headers, flags, attachments, and folder structure.
Idempotent and resumable via a SQLite ledger — safe to run repeatedly.

## Install

```bash
composer install
cp config/accounts.example.php config/accounts.php   # then edit credentials
```

For Gmail/Workspace destinations: enable IMAP + 2FA and generate an app
password; use it as the destination `password`.

## Usage (safe, escalating ladder)

```bash
php migrate.php --test-connection          # verify both accounts + preview folder map
php migrate.php --dry-run                   # report what would copy; writes nothing
php migrate.php --folder="INBOX" --limit=5  # small pilot; then eyeball the destination
php migrate.php                             # full migration (resumable)
```

Interrupted? Just run it again — it skips everything already copied.

## Options

`--config=PATH` `--dry-run` `--folder=NAME` `--since=YYYY-MM-DD`
`--limit=N` `--retry-failed` (planned — not yet implemented; currently a no-op) `--verbose`

## Safety

Read-only on source, append-only on destination. Never deletes mail.

## Tests

```bash
vendor/bin/phpunit --testdox
```
