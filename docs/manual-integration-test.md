# Manual Integration Test

Prereqs: a throwaway source IMAP mailbox with a few messages, and a
throwaway destination IMAP mailbox (or a Gmail account with IMAP + app
password). Fill `config/accounts.php`.

1. Test connection + folder map:
   `php migrate.php --test-connection`
   Expect: both accounts connect; a printed source→destination folder map.

2. Dry run:
   `php migrate.php --dry-run`
   Expect: per-folder "would copy" counts; nothing written to destination.

3. Single-folder pilot:
   `php migrate.php --folder="INBOX" --limit=5`
   Then open the destination mailbox and verify for the 5 messages:
   - original **date** preserved (not today's date)
   - attachments intact
   - correct destination folder/label
   - read/unread (\Seen) state preserved

4. Idempotency:
   Run step 3 again. Expect: 0 copied, 5 skipped.

5. Full run:
   `php migrate.php`
   Expect: remaining messages copied; final summary printed.
