<?php

require __DIR__ . '/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    cavemen_json_response(405, ['error' => 'Method not allowed']);
    exit;
}

$eventKey = strtolower(trim((string) ($_GET['event'] ?? 'asali')));
$isDahk = $eventKey === 'dahk';

if ($isDahk) {
    $reg = [
        'fullName' => 'Preview Guest',
        'ticketCode' => 'DAHK-PREVIEW-001',
        'attendanceType' => 'DAHK Package',
        'ticketPriceNaira' => 20000,
        'paymentStatus' => 'paid',
    ];
    $eventName = cavemen_dahk_event_name();
    $txRef = 'cavemen-dahk-preview-tx-ref';
} else {
    $reg = [
        'fullName' => 'Preview Guest',
        'ticketCode' => 'ASALI-PREVIEW-001',
        'attendanceType' => 'Audience',
        'ticketPriceNaira' => 4000,
        'paymentStatus' => 'paid',
    ];
    $eventName = cavemen_event_name();
    $txRef = 'cavemen-asali-preview-tx-ref';
}

require_once cavemen_site_root() . '/lib/AsaliTicketPdfHtml.php';
require_once cavemen_site_root() . '/lib/CavemenTicketQr.php';

$venue = cavemen_event_venue($isDahk);
$qrPng = CavemenTicketQr::pngBytes($txRef);
$html = AsaliTicketPdfHtml::build($reg, $eventName, $venue, $txRef, $qrPng, [
    'isDahk' => $isDahk,
    'eventWhen' => $isDahk ? cavemen_event_when(true) : 'Saturday 12 September 2026 · 6:00 PM',
]);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
echo $html;
