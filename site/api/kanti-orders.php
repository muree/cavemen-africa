<?php

require __DIR__ . '/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cavemen_json_response(405, ['error' => 'Method not allowed']);
    exit;
}
cavemen_handle_api_kanti_orders_post();
