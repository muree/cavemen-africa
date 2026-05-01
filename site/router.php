<?php

/**
 * Router for PHP’s built-in server (.htaccess is ignored by `php -S`).
 *
 * From the `site/` directory:
 *   php -S localhost:8080 router.php
 *
 * Then open http://localhost:8080/dahk-seasons/register/
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$norm = $path !== '/' ? rtrim($path, '/') : '/';

$map = [
    '/api/health' => __DIR__ . '/api/health.php',
    '/api/health.php' => __DIR__ . '/api/health.php',
    '/api/products' => __DIR__ . '/api/products.php',
    '/api/products.php' => __DIR__ . '/api/products.php',
    '/api/asali-registrations' => __DIR__ . '/api/asali-registrations.php',
    '/api/asali-registrations.php' => __DIR__ . '/api/asali-registrations.php',
    '/api/asali-payment-status' => __DIR__ . '/api/asali-payment-status.php',
    '/api/asali-payment-status.php' => __DIR__ . '/api/asali-payment-status.php',
    '/api/asali-ticket.pdf' => __DIR__ . '/api/asali-ticket-pdf.php',
    '/api/asali-ticket-pdf.php' => __DIR__ . '/api/asali-ticket-pdf.php',
    '/api/dahk-registrations' => __DIR__ . '/api/dahk-registrations.php',
    '/api/dahk-registrations.php' => __DIR__ . '/api/dahk-registrations.php',
    '/api/dahk-payment-status' => __DIR__ . '/api/dahk-payment-status.php',
    '/api/dahk-payment-status.php' => __DIR__ . '/api/dahk-payment-status.php',
    '/api/dahk-ticket.pdf' => __DIR__ . '/api/dahk-ticket-pdf.php',
    '/api/dahk-ticket-pdf.php' => __DIR__ . '/api/dahk-ticket-pdf.php',
    '/api/webhooks/flutterwave' => __DIR__ . '/api/webhooks/flutterwave.php',
];

if (isset($map[$norm])) {
    require $map[$norm];
    return true;
}

return false;
