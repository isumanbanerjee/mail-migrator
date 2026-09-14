<?php
return [
    'source' => [
        'host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl',
        'username' => 'me@example.com', 'password' => 'pw',
    ],
    'destination' => [
        'host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl',
        'username' => 'me@gmail.com', 'password' => 'apppw',
    ],
    'options' => ['batch_size' => 50, 'throttle_ms' => 0],
];
