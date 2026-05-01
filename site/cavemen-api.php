<?php

/**
 * API entry point that does NOT use the /api/ path — some hosts or edge layers
 * intercept /api/* and return errors (e.g. "API route not found") before PHP runs.
 *
 * Usage (same methods and bodies as the scripts in api/):
 *   POST /cavemen-api.php?route=dahk-registrations          (JSON body)
 *   POST /cavemen-api.php?route=asali-registrations       (JSON body)
 *   GET  /cavemen-api.php?route=dahk-payment-status&tx_ref=…
 *   GET  /cavemen-api.php?route=products                  (&category= optional)
 *   GET  /cavemen-api.php?route=health
 *   POST /cavemen-api.php?route=flutterwave-webhook       (Flutterwave raw body)
 *
 * PDF tickets:
 *   GET /cavemen-api.php?route=dahk-ticket-pdf&tx_ref=…
 *   GET /cavemen-api.php?route=asali-ticket-pdf&tx_ref=…
 */
declare(strict_types=1);

$raw = $_GET['route'] ?? '';
$route = is_string($raw) ? preg_replace('/[^a-z0-9-]/', '', strtolower($raw)) : '';

$map = [
    'health' => __DIR__ . '/api/health.php',
    'products' => __DIR__ . '/api/products.php',
    'asali-registrations' => __DIR__ . '/api/asali-registrations.php',
    'asali-payment-status' => __DIR__ . '/api/asali-payment-status.php',
    'asali-ticket-pdf' => __DIR__ . '/api/asali-ticket-pdf.php',
    'dahk-registrations' => __DIR__ . '/api/dahk-registrations.php',
    'dahk-payment-status' => __DIR__ . '/api/dahk-payment-status.php',
    'dahk-ticket-pdf' => __DIR__ . '/api/dahk-ticket-pdf.php',
    'flutterwave-webhook' => __DIR__ . '/api/webhooks/flutterwave.php',
];

if ($route === '' || !isset($map[$route])) {
    require_once __DIR__ . '/api/common.php';
    cavemen_json_response(404, [
        'error' => 'Unknown route. Pass ?route=… (e.g. dahk-registrations, health).',
    ]);
    exit;
}

require $map[$route];
exit;
