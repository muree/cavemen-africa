<?php

/**
 * Upload site image assets to Cloudinary (CLI only).
 *
 *   cd site && php bin/upload-cloudinary-assets.php
 *
 * Requires in site/.env:
 *   CLOUDINARY_CLOUD_NAME=kqhxqms3
 *   CLOUDINARY_API_KEY=...
 *   CLOUDINARY_API_SECRET=...
 *
 * Or a single CLOUDINARY_URL=cloudinary://KEY:SECRET@CLOUD_NAME
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo 'Not found';
    exit(1);
}

require_once dirname(__DIR__) . '/api/common.php';

function cavemen_cloudinary_credentials()
{
    $url = (string) cavemen_env('CLOUDINARY_URL', '');
    if ($url !== '' && preg_match('#cloudinary://([^:]+):([^@]+)@([^/]+)#', $url, $m)) {
        return ['key' => $m[1], 'secret' => $m[2], 'cloud' => $m[3]];
    }
    $cloud = (string) cavemen_env('CLOUDINARY_CLOUD_NAME', 'kqhxqms3');
    $key = (string) cavemen_env('CLOUDINARY_API_KEY', '');
    $secret = (string) cavemen_env('CLOUDINARY_API_SECRET', '');
    return ['key' => $key, 'secret' => $secret, 'cloud' => $cloud];
}

function cavemen_cloudinary_sign(array $params, $secret)
{
    unset($params['file'], $params['resource_type'], $params['api_key']);
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $pairs[] = $k . '=' . $v;
    }
    return sha1(implode('&', $pairs) . $secret);
}

function cavemen_cloudinary_upload_file($path, $publicId, array $creds)
{
    $timestamp = (string) time();
    $toSign = [
        'overwrite' => 'true',
        'public_id' => $publicId,
        'timestamp' => $timestamp,
        'unique_filename' => 'false',
    ];
    $signature = cavemen_cloudinary_sign($toSign, $creds['secret']);
    $post = $toSign;
    $post['api_key'] = $creds['key'];
    $post['signature'] = $signature;
    $post['file'] = new CURLFile($path);

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . rawurlencode($creds['cloud']) . '/image/upload');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = is_string($out) ? json_decode($out, true) : null;
    if ($code < 200 || $code >= 300 || !is_array($j) || empty($j['secure_url'])) {
        $msg = is_array($j) && isset($j['error']['message']) ? $j['error']['message'] : 'HTTP ' . $code;
        if (strpos($msg, 'missing permissions') !== false) {
            $msg .= ' Use an unrestricted API key (Settings → API Keys) with Assets/upload create, not a read-only key.';
        }
        throw new RuntimeException($msg);
    }
    return $j;
}

$creds = cavemen_cloudinary_credentials();
if ($creds['key'] === '' || $creds['secret'] === '' || $creds['cloud'] === '') {
    fwrite(STDERR, "Add CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET to site/.env (or CLOUDINARY_URL).\n");
    exit(1);
}

$root = cavemen_site_root();
$outFile = $root . '/data/cloudinary-assets.json';
$existing = [];
if (is_readable($outFile)) {
    $prev = json_decode((string) file_get_contents($outFile), true);
    if (is_array($prev) && isset($prev['assets']) && is_array($prev['assets'])) {
        $existing = $prev['assets'];
    }
}

$files = [
    $root . '/assets/Logo-no-bg.PNG' => 'cavemen-africa/assets/logo-no-bg',
    $root . '/assets/asali-registration-banner.png' => 'cavemen-africa/assets/asali-registration-banner',
    $root . '/assets/asaliunimaid.jpeg' => 'cavemen-africa/assets/asali-unimaid',
    $root . '/assets/cavemen-favicon.png' => 'cavemen-africa/assets/cavemen-favicon',
    $root . '/assets/cavemen-icon.svg' => 'cavemen-africa/assets/cavemen-icon',
    $root . '/assets/cavemen-logo.png' => 'cavemen-africa/assets/cavemen-logo',
    $root . '/assets/cavemen-pattern.png' => 'cavemen-africa/assets/cavemen-pattern',
    $root . '/assets/dahk-registration-banner.png' => 'cavemen-africa/assets/dahk-registration-banner',
    $root . '/assets/tatsuniyaimg.jpeg' => 'cavemen-africa/assets/tatsuniya',
    $root . '/kanti/assets/images/ashes.jpeg' => 'cavemen-africa/kanti/ashes',
    $root . '/kanti/assets/images/author.jpeg' => 'cavemen-africa/kanti/author',
    $root . '/kanti/assets/images/bloom.jpeg' => 'cavemen-africa/kanti/bloom',
    $root . '/kanti/assets/images/book.jpeg' => 'cavemen-africa/kanti/book',
    $root . '/kanti/assets/images/dry.jpeg' => 'cavemen-africa/kanti/dry',
    $root . '/kanti/assets/images/epiphany.jpeg' => 'cavemen-africa/kanti/epiphany',
    $root . '/kanti/assets/images/hero.jpeg' => 'cavemen-africa/kanti/hero',
    $root . '/kanti/assets/images/roots.jpeg' => 'cavemen-africa/kanti/roots',
    $root . '/kanti/assets/images/thorns.jpeg' => 'cavemen-africa/kanti/thorns',
    $root . '/kanti/assets/images/vial.jpeg' => 'cavemen-africa/kanti/vial',
    $root . '/kanti/assets/images/wilt.jpeg' => 'cavemen-africa/kanti/wilt',
];

$map = [
    'cloud' => $creds['cloud'],
    'uploadedAt' => gmdate('c'),
    'assets' => $existing,
];

$ok = 0;
$fail = 0;
foreach ($files as $path => $publicId) {
    $rel = str_replace($root . '/', '', $path);
    if (!is_file($path)) {
        fwrite(STDERR, "skip missing {$rel}\n");
        continue;
    }
    try {
        $uploaded = cavemen_cloudinary_upload_file($path, $publicId, $creds);
        $map['assets'][$rel] = [
            'publicId' => $uploaded['public_id'],
            'secureUrl' => $uploaded['secure_url'],
            'bytes' => isset($uploaded['bytes']) ? (int) $uploaded['bytes'] : filesize($path),
        ];
        echo "ok  {$rel} -> {$uploaded['secure_url']}\n";
        $ok++;
    } catch (Throwable $e) {
        fwrite(STDERR, "fail {$rel}: " . $e->getMessage() . "\n");
        $fail++;
    }
}

$json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($outFile, $json . "\n") === false) {
    fwrite(STDERR, "Could not write {$outFile}\n");
    exit(1);
}

echo "Wrote {$outFile} ({$ok} uploaded, {$fail} failed)\n";
exit($fail > 0 ? 1 : 0);
