<?php

/**
 * Serve `site/` when `php -S` is started from the repository root
 * (so `/styles/site.css` and `/` resolve the same way as production).
 *
 *   php -S localhost:8080 router.php
 */
declare(strict_types=1);

$site = __DIR__ . '/site';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($uri === '/site' || strpos($uri, '/site/') === 0) {
    $uri = substr($uri, 5) ?: '/';
    $qs = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = $uri . ($qs ? '?' . $qs : '');
}

$handled = require $site . '/router.php';
if ($handled === true) {
    return true;
}

$rel = $uri === '/' ? '' : $uri;
$candidate = $site . $rel;
if (is_dir($candidate)) {
    $candidate = rtrim($candidate, '/') . '/index.html';
}

$realSite = realpath($site);
$real = is_file($candidate) ? realpath($candidate) : false;
if ($realSite === false || $real === false || strpos($real, $realSite) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    return true;
}

if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) === 'php') {
    require $real;
    return true;
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$types = [
    'html' => 'text/html; charset=UTF-8',
    'css' => 'text/css; charset=UTF-8',
    'js' => 'text/javascript; charset=UTF-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'json' => 'application/json',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain; charset=UTF-8',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
readfile($real);
return true;
