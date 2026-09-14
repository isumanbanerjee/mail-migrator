<?php
// Copy to config/accounts.php (git-ignored) and fill in.
return [
    'source' => [
        'host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl',
        'username' => 'you@yourdomain.com', 'password' => 'source-password',
        'validate_cert' => true,
    ],
    'destination' => [
        'host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl',
        'username' => 'you@workspace.com', 'password' => 'gmail-app-password',
        'validate_cert' => true,
    ],
    'options' => [
        'batch_size' => 200,
        'throttle_ms' => 300,
        'folder_map' => [], // optional overrides, e.g. 'INBOX.Archive' => 'Archive'
        'since' => null,    // e.g. '2020-01-01'
        'limit' => null,    // cap messages copied per run
    ],
];
