<?php

// Each browser run owns a separate SQLite file, shared with Panther's web server.
$root = dirname(__DIR__);
$directory = $root . '/var/browser-tests/' . bin2hex(random_bytes(8));
mkdir($directory, 0777, true);
foreach ([
    'APP_ENV' => 'test',
    'APP_DEBUG' => '1',
    'DATABASE_URL' => 'sqlite:///' . $directory . '/panther.sqlite',
    'PANTHER_TEST_DATABASE' => $directory . '/panther.sqlite',
    'MAILER_DSN' => 'null://null',
    'MESSENGER_TRANSPORT_DSN' => 'in-memory://',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_SERVER[$name] = $_ENV[$name] = $value;
}

require __DIR__ . '/bootstrap.php';
