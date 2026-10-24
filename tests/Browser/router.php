<?php

// Let the built-in server serve real files; send AssetMapper and app routes to Symfony.
$public = dirname(__DIR__, 2) . '/public';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($public . $path);
if ($file !== false && str_starts_with($file, $public . '/') && is_file($file)) {
    return false;
}
require $public . '/index.php';
