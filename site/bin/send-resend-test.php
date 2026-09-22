<?php

/**
 * CLI-only Resend smoke test (not a web route).
 *
 *   php bin/send-resend-test.php you@example.com
 *
 * Uses the published Resend ticket template (alias cavemen-ticket).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo 'Not found';
    exit(1);
}

require_once dirname(__DIR__) . '/api/common.php';

$to = isset($argv[1]) ? trim((string) $argv[1]) : (string) cavemen_env('RESEND_TEST_TO', '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/send-resend-test.php you@example.com\n");
    exit(1);
}

if ((string) cavemen_env('RESEND_API_KEY', '') === '') {
    fwrite(STDERR, "RESEND_API_KEY is not set in site/.env — cannot send.\n");
    exit(1);
}

$txRef = 'TEST-' . gmdate('YmdHis');
$reg = [
    'fullName' => 'Resend Test',
    'ticketCode' => 'CAVE-TEST',
    'attendanceType' => 'Performer',
    'ticketPriceNaira' => 5000,
    'email' => $to,
    'txRef' => $txRef,
    'paymentStatus' => 'paid',
];

$ok = cavemen_send_ticket_email_php($reg, cavemen_event_name(), $txRef, false);
if (!$ok) {
    fwrite(STDERR, "Resend send failed. Publish the template first:\n");
    fwrite(STDERR, "  php bin/publish-resend-ticket-template.php\n");
    exit(1);
}

echo "Sent templated ticket email to {$to} (ref {$txRef})\n";
exit(0);
