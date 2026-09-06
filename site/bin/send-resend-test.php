<?php

/**
 * CLI-only Resend smoke test (not a web route).
 *
 *   php bin/send-resend-test.php you@example.com
 *
 * Requires RESEND_API_KEY in site/.env. Unverified accounts can send from
 * beth.t@example.com to the address on the Resend account.
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
    'attendanceType' => 'General',
    'ticketPriceNaira' => 5000,
    'email' => $to,
    'txRef' => $txRef,
    'paymentStatus' => 'paid',
];
$event = cavemen_event_name();
$pass = cavemen_ticket_pass_context($reg, $txRef, false, []);
$qrPng = CavemenTicketQr::pngBytes($pass['txRef']);
$details = [
    'venue' => $pass['venue'],
    'when' => $pass['when'],
    'txRef' => $pass['txRef'],
    'flierUrl' => $pass['flierUrl'],
    'amountLabel' => $pass['amountLabel'],
    'qrCid' => 'cavemen-gate-qr',
    'hasQr' => $qrPng !== null,
];
$html = AsaliEmailPhp::buildTicketEmailHtml(
    $pass['fullName'],
    $reg['ticketCode'],
    $reg['attendanceType'],
    $reg['ticketPriceNaira'],
    $event,
    $details
);
$text = AsaliEmailPhp::buildTicketEmailText(
    $pass['fullName'],
    $reg['ticketCode'],
    $reg['attendanceType'],
    $reg['ticketPriceNaira'],
    $event,
    $details
);
$qrAttach = $qrPng !== null
    ? ['content' => $qrPng, 'filename' => 'gate-pass.png', 'cid' => 'cavemen-gate-qr']
    : null;

$ok = AsaliEmailPhp::sendWithResend(
    $to,
    'Cavemen test — gate pass (Resend)',
    $html,
    $text,
    null,
    $qrAttach
);
if (!$ok) {
    fwrite(STDERR, "Resend send failed. Check PHP error log for [cavemen] Resend.\n");
    exit(1);
}

echo "Sent test ticket email to {$to} (ref {$txRef})\n";
exit(0);
