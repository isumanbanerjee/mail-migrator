# MailMigrator

Move a mailbox from one IMAP server to another without losing anything along the way. MailMigrator copies messages directly between the two accounts and keeps their original send/receive dates, read and unread state, stars and flags, attachments, and the exact folder layout. It never forwards or re-sends mail (that resets every timestamp), and it never deletes anything from the account you're moving away from.

There are two ways to run it. If you just need to move one or two accounts, use the command-line tool: point it at a source and a destination, and it does the copy. If you want to offer migrations to other people, run the web app instead — it has accounts, a job dashboard with live progress, a background worker, and an optional paywall so you can charge for it.

The whole thing is plain PHP with no framework behind it, so it runs on ordinary shared hosting with a cron job. You don't need a VPS or Docker to get started.

## Which mail providers does it work with?

Anything that speaks IMAP, which is almost everything. These are the ones people ask about most:

| Provider | Supported | Worth knowing |
| --- | --- | --- |
| Gmail / Google Workspace | Yes | Turn on IMAP and use a 16-character app password. Gmail's labels are handled as folders. |
| Outlook / Microsoft 365 | Yes | Standard IMAP; app password required if the account uses modern auth. |
| cPanel / Dovecot hosts | Yes | The common case for shared hosting and small business email. |
| Yahoo Mail | Yes | Requires a generated app password. |
| Zoho Mail | Yes | Works with an application-specific password. |
| Hostinger, Namecheap, etc. | Yes | Any cPanel-style mailbox. |
| Any other IMAP server | Yes | If you can log in with an IMAP client, MailMigrator can move it. |

## How the migration works

Copying happens over IMAP using `APPEND`, which is the one way to write a message into a mailbox while preserving its original internal date. Because of that, a two-year-old email still shows up as two years old on the other side instead of jumping to today.

Every message a job touches is written to a ledger before and after it's copied. That ledger is what makes runs safe to repeat. If the connection drops, the host kills the process, or you simply run out of time on a shared-hosting cron tick, the next run picks up exactly where the last one stopped instead of starting over.

Re-running a finished (or half-finished) job won't create duplicates. Before copying, MailMigrator checks whether the destination already has the message by its `Message-ID`, falling back to a content hash when a server has stripped or rewritten that header. Anything already there is skipped.

For large accounts, messages are streamed and copied one at a time with batching and throttling, so a 200,000-message mailbox doesn't try to load itself into memory or trip a provider's rate limits. Gmail's special folders (Sent, Drafts, Spam, Trash) are mapped to their `[Gmail]/…` equivalents, and Dovecot's dotted folder names are translated to a normal hierarchy. Both mappings are configurable if your setup is unusual.

## Quick start: command line

```bash
composer install
cp config/accounts.example.php config/accounts.php   # fill in source + destination
php migrate.php --test-connection                    # check both logins, preview the folder map
php migrate.php --dry-run                             # report what would copy; writes nothing
php migrate.php --folder="INBOX" --limit=5           # tiny pilot you can eyeball
php migrate.php                                       # the real run (safe to re-run any time)
```

The order there is the point: test the connection, do a dry run, copy a handful of messages as a pilot, then let the full migration go. By the time you run the last command you already know both accounts work and the folder mapping is right.

Options: `--config=PATH`, `--dry-run`, `--folder=NAME`, `--since=YYYY-MM-DD`, `--limit=N`, `--verbose`.

## Quick start: web app

```bash
composer install
cp .env.example .env         # set DB_*, generate APP_KEY, configure the paywall if you want one
php bin/migrate.php          # create the database tables
```

Point your web server's document root at `public/`, then register an account, create a job, and watch it run on the dashboard. Migrations are processed by a worker you trigger from cron, once a minute:

```
* * * * * /usr/bin/php /path/to/bin/worker.php >> worker.log 2>&1
```

Each tick claims one queued job, works on it for a short budget, saves progress, and exits; the next tick continues it. That's what lets long migrations finish under a shared host's execution limits. Full setup — PHP version, MySQL, `APP_KEY`, document root, cron, and payment webhooks — is in [docs/deploy-shared-hosting.md](docs/deploy-shared-hosting.md).

## Running it as a paid service

The paywall is optional and off by default. Leave it off and everything is free and unlimited, which is what you want for a personal or internal tool. Turn it on and you can give each account a free quota (a number of jobs, a number of emails, or both), then require payment past that.

Three pricing models ship, and you enable whichever ones you want to sell: a one-time unlock for permanent access, credit packs measured in emails, and a monthly subscription. Payments go through PayPal or Razorpay. Entitlements are only ever granted from a webhook whose signature has been verified against the gateway, never from the browser redirect after checkout, so a user can't fake their way past the paywall by editing a URL. Duplicate webhook deliveries for the same payment won't grant twice.

## Security

The design assumption is that you might be holding other people's live email passwords, so the defaults are cautious. The source account is only ever read from, and the destination is only ever appended to — nothing is deleted on either side.

Stored IMAP credentials are encrypted at rest with `defuse/php-encryption`, using a key that lives only in your `.env` and nowhere in the database. If someone reads your database, they still can't read the passwords. Sessions use argon2id password hashing where the PHP build supports it and fall back to bcrypt where it doesn't. CSRF tokens protect every form, users can only see and act on their own jobs, and payment webhooks are signature-verified.

Two things are on you: serve the app over HTTPS, and keep `.env` out of the web root. Losing the key in `.env` means the stored credentials can no longer be decrypted, so back it up somewhere safe.

## Architecture

```
src/                    migration engine (framework-agnostic, MailMigrator\)
app/                    the web application (App\): router, controllers,
                        repositories, services, auth, billing
bin/worker.php          cron worker that runs and resumes jobs
bin/migrate.php         schema migrator
database/migrations/    portable SQL schema
public/                 web document root and front controller
```

The engine in `src/` has no idea the web app exists — it's the same code the CLI uses, so you can pull it into your own project if all you want is the IMAP-to-IMAP copy.

## Tech stack

PHP 8.5, no framework. PDO with MySQL in production and SQLite for the test suite. IMAP handled by `webklex/php-imap`, encryption by `defuse/php-encryption`, routing by `nikic/fast-route`, environment config by `vlucas/phpdotenv`. The interface is Tailwind and Alpine.js. Tests run on PHPUnit.

## FAQ

**Does it delete anything from the old account?**
No. The source is opened read-only and the destination is append-only. Once you've confirmed the copy, deleting the old mailbox is a separate decision you make yourself.

**Will I get duplicate emails if I run it more than once?**
No. Messages already present at the destination are detected by `Message-ID` (with a content-hash fallback) and skipped, so re-running is safe.

**What happens if it crashes or times out halfway?**
It resumes. A per-message ledger records progress, and the next run continues from where it stopped rather than starting again.

**Are dates and read/unread status preserved?**
Yes. Messages are written with `APPEND`, which keeps the original internal date, and read/unread state and flags are carried over.

**How large a mailbox can it handle?**
It's built for big accounts — tens of thousands to a few hundred thousand messages — by streaming one message at a time with batching and throttling.

**Do I need a VPS?**
No. The web app runs on shared hosting with a cron job, and the CLI runs anywhere PHP does. A VPS is only worth it if your host blocks outbound IMAP.

**How do I migrate a Gmail or Google Workspace account?**
Enable IMAP in Gmail's settings and create an app password (you'll need 2-Step Verification on). Use that app password instead of the normal one.

**Is it free?**
The software is open source and free to run. The built-in paywall is only there if you want to charge other people for migrations you run for them.

## License

See [LICENSE](LICENSE).
