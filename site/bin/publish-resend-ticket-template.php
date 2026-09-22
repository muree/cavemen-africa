<?php

/**
 * Create or update the Cavemen ticket template in Resend, then publish it.
 *
 *   php bin/publish-resend-ticket-template.php
 *
 * Uses RESEND_API_KEY and optional RESEND_TICKET_TEMPLATE alias from site/.env.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/api/common.php';

$apiKey = (string) cavemen_env('RESEND_API_KEY', '');
if ($apiKey === '') {
    fwrite(STDERR, "RESEND_API_KEY is not set in site/.env\n");
    exit(1);
}

$alias = AsaliEmailPhp::ticketTemplateAlias();
$from = (string) cavemen_env('RESEND_FROM', 'Cavemen Africa <info@cavemen.africa>');
$body = [
    'name' => 'Cavemen ticket',
    'alias' => $alias,
    'from' => $from,
    'subject' => 'Your {{{TIER_LABEL}}} ticket — {{{EVENT_NAME}}}',
    'html' => AsaliEmailPhp::ticketTemplateHtml(),
    'text' => AsaliEmailPhp::ticketTemplateText(),
    'variables' => AsaliEmailPhp::ticketTemplateVariableDefs(),
];

$listed = resend_json('GET', 'https://api.resend.com/templates', $apiKey);
$existingId = null;
if (!empty($listed['ok']) && is_array($listed['data']['data'] ?? null)) {
    foreach ($listed['data']['data'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['alias'] ?? '') === $alias || (string) ($row['id'] ?? '') === $alias) {
            $existingId = (string) $row['id'];
            break;
        }
    }
}

if ($existingId) {
    $saved = resend_json('PATCH', 'https://api.resend.com/templates/' . rawurlencode($existingId), $apiKey, $body);
    $id = $existingId;
    $action = 'updated';
} else {
    $saved = resend_json('POST', 'https://api.resend.com/templates', $apiKey, $body);
    $id = (string) ($saved['data']['id'] ?? '');
    $action = 'created';
}

if (empty($saved['ok']) || $id === '') {
    fwrite(STDERR, "Could not {$action} template: " . resend_err($saved) . "\n");
    exit(1);
}

$published = resend_json('POST', 'https://api.resend.com/templates/' . rawurlencode($id) . '/publish', $apiKey, []);
if (empty($published['ok'])) {
    fwrite(STDERR, "Template {$action} ({$id}) but publish failed: " . resend_err($published) . "\n");
    exit(1);
}

echo "Resend ticket template {$action} and published.\n";
echo "Alias: {$alias}\n";
echo "Id: {$id}\n";
echo "Set RESEND_TICKET_TEMPLATE={$alias} in site/.env (already the default).\n";
exit(0);

/**
 * @param array<string,mixed>|null $body
 * @return array{ok:bool,data?:array,http?:int,error?:string,raw?:string}
 */
function resend_json($method, $url, $apiKey, $body = null)
{
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null && $method !== 'GET') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $out = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false) {
        return ['ok' => false, 'error' => 'curl failed', 'http' => $code];
    }
    $j = json_decode((string) $out, true);

    return [
        'ok' => $code >= 200 && $code < 300,
        'data' => is_array($j) ? $j : [],
        'http' => $code,
        'raw' => is_array($j) ? '' : (string) $out,
    ];
}

/**
 * @param array{data?:array,http?:int,error?:string,raw?:string} $res
 */
function resend_err(array $res)
{
    $data = $res['data'] ?? [];
    $msg = is_array($data) ? (string) ($data['message'] ?? $data['name'] ?? '') : '';
    if ($msg === '') {
        $msg = (string) ($res['error'] ?? $res['raw'] ?? 'unknown error');
    }

    return 'HTTP ' . (int) ($res['http'] ?? 0) . ' ' . $msg;
}
