<?php

require __DIR__ . '/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    cavemen_json_response(405, ['error' => 'Method not allowed']);
    exit;
}
cavemen_handle_kanti_payment_status();
