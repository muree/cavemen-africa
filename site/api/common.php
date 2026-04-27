<?php

/**
 * Shared PHP API (cPanel): MySQL (when MYSQL_* is set) or SQLite, Flutterwave, ticket email.
 * Endpoints: health, products, asali-registrations, webhooks/flutterwave
 */

if (!defined('CAVEMEN_API_BOOTSTRAP')) {
    define('CAVEMEN_API_BOOTSTRAP', true);
}

$GLOBALS['cavemen_pdo_inited'] = false;

function cavemen_site_root()
{
    return dirname(__DIR__);
}

function cavemen_load_dotenv()
{
    $path = cavemen_site_root() . '/.env';
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || isset($line[0]) && $line[0] === '#') {
            continue;
        }
        if (!preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
            continue;
        }
        $k = trim($m[1]);
        $v = trim($m[2], " \t\n\r\0\x0B\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("{$k}={$v}");
        }
    }
}

cavemen_load_dotenv();

require_once __DIR__ . '/../lib/AsaliEmailPhp.php';

function cavemen_env($key, $default = null)
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $default;
}

function cavemen_public_base_url()
{
    $set = cavemen_env('PUBLIC_SITE_URL');
    if ($set) {
        return rtrim($set, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function cavemen_event_name()
{
    return cavemen_env('ASALI_EVENT_NAME', 'Asali Poetry Sessions 9.0');
}

function cavemen_json_response($statusCode, $data)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
}

function cavemen_read_request_body($maxBytes = 65536)
{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        return '';
    }
    if (strlen($raw) > $maxBytes) {
        throw new RuntimeException('Payload too large');
    }
    return $raw;
}

function cavemen_uses_mysql()
{
    return cavemen_env('MYSQL_HOST', '') !== ''
        && cavemen_env('MYSQL_USER', '') !== ''
        && cavemen_env('MYSQL_DATABASE', '') !== '';
}

function cavemen_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (cavemen_uses_mysql()) {
        $host = cavemen_env('MYSQL_HOST');
        $port = (int) cavemen_env('MYSQL_PORT', 3306);
        $dbname = cavemen_env('MYSQL_DATABASE');
        $user = cavemen_env('MYSQL_USER');
        $password = cavemen_env('MYSQL_PASSWORD', '');
        // Percent-encode dbname so names with spaces (e.g. "My DB") work in the DSN.
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . rawurlencode($dbname) . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } else {
        $dataDir = cavemen_site_root() . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $dbPath = $dataDir . '/cavemen.db';
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    cavemen_init_db($pdo);
    return $pdo;
}

/**
 * @return 'mysql'|'sqlite'
 */
function cavemen_db_driver(PDO $pdo)
{
    $d = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    return $d === 'mysql' ? 'mysql' : 'sqlite';
}

function cavemen_init_db(PDO $pdo)
{
    if (cavemen_db_driver($pdo) === 'mysql') {
        cavemen_init_db_mysql($pdo);
    } else {
        cavemen_init_db_sqlite($pdo);
    }
}

function cavemen_init_db_mysql(PDO $pdo)
{
    $pdo->exec('
CREATE TABLE IF NOT EXISTS kanti_products (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  title VARCHAR(512) NOT NULL,
  short_description TEXT NOT NULL,
  category VARCHAR(64) NOT NULL,
  image VARCHAR(1024) NOT NULL,
  flutterwave_url VARCHAR(1024) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
    $pdo->exec('
CREATE TABLE IF NOT EXISTS asali_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  gender VARCHAR(64) NOT NULL,
  discovery VARCHAR(512) NOT NULL,
  attendance_type VARCHAR(32) NOT NULL,
  ticket_price_naira INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  payment_status VARCHAR(24) NOT NULL DEFAULT \'pending\',
  tx_ref VARCHAR(128) NULL,
  ticket_code VARCHAR(64) NULL,
  flutterwave_transaction_id VARCHAR(128) NULL,
  ticket_email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_asali_tx_ref (tx_ref),
  KEY idx_asali_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
}

function cavemen_init_db_sqlite(PDO $pdo)
{
    $pdo->exec('
  CREATE TABLE IF NOT EXISTS kanti_products (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    short_description TEXT NOT NULL,
    category TEXT NOT NULL,
    image TEXT NOT NULL,
    flutterwave_url TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS asali_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    gender TEXT NOT NULL,
    discovery TEXT NOT NULL,
    attendance_type TEXT NOT NULL,
    ticket_price_naira INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );
');

    $tables = [
        'asali_registrations' => [
            ['ticket_price_naira', "INTEGER NOT NULL DEFAULT 0"],
            ['payment_status', "TEXT NOT NULL DEFAULT 'pending'"],
            ['tx_ref', 'TEXT'],
            ['ticket_code', 'TEXT'],
            ['flutterwave_transaction_id', 'TEXT'],
            ['ticket_email_sent_at', 'TEXT'],
        ],
    ];
    foreach ($tables as $table => $columns) {
        $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ($columns as $col) {
            if (!in_array($col[0], $cols, true)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col[0]} {$col[1]}");
            }
        }
    }
}

function cavemen_seed_products(PDO $pdo)
{
    $path = cavemen_site_root() . '/data/kanti-products.json';
    if (!is_readable($path)) {
        return;
    }
    $products = json_decode(file_get_contents($path), true);
    if (!is_array($products)) {
        return;
    }
    if (cavemen_db_driver($pdo) === 'mysql') {
        $sql = 'INSERT INTO kanti_products (
      id, title, short_description, category, image, flutterwave_url, is_active, updated_at
    ) VALUES (
      :id, :title, :shortDescription, :category, :image, :flutterwaveUrl, 1, CURRENT_TIMESTAMP
    ) ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      short_description = VALUES(short_description),
      category = VALUES(category),
      image = VALUES(image),
      flutterwave_url = VALUES(flutterwave_url),
      is_active = VALUES(is_active),
      updated_at = CURRENT_TIMESTAMP';
    } else {
        $sql = 'INSERT INTO kanti_products (
      id, title, short_description, category, image, flutterwave_url, is_active, updated_at
    ) VALUES (
      :id, :title, :shortDescription, :category, :image, :flutterwaveUrl, 1, CURRENT_TIMESTAMP
    ) ON CONFLICT(id) DO UPDATE SET
      title = excluded.title,
      short_description = excluded.short_description,
      category = excluded.category,
      image = excluded.image,
      flutterwave_url = excluded.flutterwave_url,
      is_active = excluded.is_active,
      updated_at = CURRENT_TIMESTAMP';
    }
    $stmt = $pdo->prepare($sql);
    foreach ($products as $p) {
        $stmt->execute($p);
    }
}

function cavemen_ticket_types()
{
    return [
        'Performer' => ['ticketPriceNaira' => 5000],
        'Audience' => ['ticketPriceNaira' => 4000],
    ];
}

function cavemen_normalize_optional($value)
{
    $t = trim((string) $value);
    return $t === '' ? null : $t;
}

function cavemen_validate_registration($payload)
{
    if (!is_array($payload)) {
        $payload = [];
    }
    $fullName = trim((string) ($payload['fullName'] ?? ''));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $gender = trim((string) ($payload['gender'] ?? ''));
    $discovery = trim((string) ($payload['discovery'] ?? ''));
    $attendanceType = trim((string) ($payload['attendanceType'] ?? ''));
    $notes = cavemen_normalize_optional($payload['notes'] ?? '');

    if ($fullName === '' || $phone === '' || $email === '' || $gender === '' || $discovery === '' || $attendanceType === '') {
        return ['error' => 'Please complete all required fields.'];
    }
    if (strpos($email, '@') === false) {
        return ['error' => 'Please enter a valid email address.'];
    }
    $types = cavemen_ticket_types();
    if (!array_key_exists($attendanceType, $types)) {
        return ['error' => 'Please select a valid ticket type.'];
    }

    return [
        'data' => [
            'fullName' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'gender' => $gender,
            'discovery' => $discovery,
            'attendanceType' => $attendanceType,
            'ticketPriceNaira' => $types[$attendanceType]['ticketPriceNaira'],
            'notes' => $notes,
        ],
    ];
}

function cavemen_meta_to_object($meta)
{
    if ($meta === null) {
        return [];
    }
    if (is_array($meta) && isset($meta[0]) && is_array($meta[0]) && array_key_exists('metaname', $meta[0])) {
        $o = [];
        foreach ($meta as $row) {
            if (is_array($row) && isset($row['metaname'], $row['metavalue'])) {
                $o[$row['metaname']] = $row['metavalue'];
            }
        }
        return $o;
    }
    if (is_array($meta)) {
        return $meta;
    }
    return [];
}

function cavemen_fw_normalize_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strpos($digits, '234') === 0) {
        return $digits;
    }
    if (strpos($digits, '0') === 0) {
        return '234' . substr($digits, 1);
    }
    return $digits;
}

function cavemen_generate_ticket_code($registrationId)
{
    $suffix = strtoupper(bin2hex(random_bytes(4)));
    return "ASALI9-{$registrationId}-{$suffix}";
}

/**
 * @return array{ok: bool, data?: array, error?: string}
 */
function cavemen_http_post_json($url, $headers, $body, $bearer = null)
{
    $ch = curl_init($url);
    $h = $headers;
    if ($bearer) {
        $h[] = 'Authorization: Bearer ' . $bearer;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false) {
        return ['ok' => false, 'error' => 'curl failed'];
    }
    $j = json_decode($out, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'invalid json', 'raw' => $out];
    }
    return ['ok' => $code >= 200 && $code < 300, 'data' => $j, 'http' => $code];
}

/**
 * @return string|null
 */
function cavemen_flutterwave_init_payment($txRef, $amountNaira, $customer, $redirectUrl, $registrationId, $attendanceType)
{
    $secret = cavemen_env('FLUTTERWAVE_SECRET_KEY');
    if ($secret === null || $secret === '') {
        return null;
    }
    $publicBase = cavemen_public_base_url();
    $event = cavemen_event_name();
    $payload = [
        'tx_ref' => $txRef,
        'amount' => (string) $amountNaira,
        'currency' => 'NGN',
        'redirect_url' => $redirectUrl,
        'payment_options' => 'card,account,ussd,banktransfer,mobilemoney',
        'customer' => [
            'email' => $customer['email'],
            'phonenumber' => cavemen_fw_normalize_phone($customer['phone']),
            'name' => $customer['name'],
        ],
        'customizations' => [
            'title' => $event,
            'description' => 'Ticket payment — Cavemen Africa',
            'logo' => $publicBase . '/assets/cavemen-logo.png',
        ],
        'meta' => [
            ['metaname' => 'registration_id', 'metavalue' => (string) $registrationId],
            ['metaname' => 'attendance_type', 'metavalue' => $attendanceType],
        ],
    ];
    $res = cavemen_http_post_json(
        'https://api.flutterwave.com/v3/payments',
        ['Content-Type: application/json'],
        json_encode($payload),
        $secret
    );
    if (!($res['ok'] ?? false)) {
        $msg = is_array($res['data'] ?? null) && isset($res['data']['message'])
            ? $res['data']['message']
            : 'Flutterwave payment could not be started.';
        throw new RuntimeException($msg);
    }
    $j = $res['data'] ?? [];
    if (($j['status'] ?? '') !== 'success' || empty($j['data']['link'])) {
        $msg = $j['message'] ?? 'Flutterwave payment could not be started.';
        throw new RuntimeException($msg);
    }
    return $j['data']['link'];
}

/**
 * @return bool
 */
function cavemen_flutterwave_verify_transaction($transactionId)
{
    $secret = cavemen_env('FLUTTERWAVE_SECRET_KEY');
    if ($secret === null || $secret === '') {
        return false;
    }
    $url = 'https://api.flutterwave.com/v3/transactions/' . rawurlencode((string) $transactionId) . '/verify';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false) {
        return false;
    }
    $j = json_decode($out, true);
    return $code >= 200 && $code < 300
        && is_array($j)
        && ($j['status'] ?? '') === 'success'
        && ($j['data']['status'] ?? '') === 'successful';
}

/**
 * @return ?array<string,mixed>
 */
function cavemen_fetch_registration_by_id(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM asali_registrations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return cavemen_registration_row($row);
}

/**
 * @return ?array<string,mixed>
 */
function cavemen_fetch_registration_by_txref(PDO $pdo, $txRef)
{
    $stmt = $pdo->prepare('SELECT * FROM asali_registrations WHERE tx_ref = :tx');
    $stmt->execute([':tx' => $txRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return cavemen_registration_row($row);
}

function cavemen_registration_row($row)
{
    return [
        'id' => (int) $row['id'],
        'fullName' => $row['full_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'gender' => $row['gender'],
        'discovery' => $row['discovery'],
        'attendanceType' => $row['attendance_type'],
        'ticketPriceNaira' => (int) $row['ticket_price_naira'],
        'notes' => $row['notes'],
        'paymentStatus' => $row['payment_status'] ?? 'pending',
        'txRef' => $row['tx_ref'] ?? null,
        'ticketCode' => $row['ticket_code'] ?? null,
        'flutterwaveTransactionId' => $row['flutterwave_transaction_id'] ?? null,
        'ticketEmailSentAt' => $row['ticket_email_sent_at'] ?? null,
    ];
}

function cavemen_get_payment_links()
{
    $path = cavemen_site_root() . '/data/asali-payment-links.json';
    if (!is_readable($path)) {
        return ['Performer' => '', 'Audience' => ''];
    }
    $j = json_decode(file_get_contents($path), true);
    return is_array($j) ? $j : ['Performer' => '', 'Audience' => ''];
}

function cavemen_send_ticket_email_php($registration)
{
    $event = cavemen_event_name();
    $html = AsaliEmailPhp::buildTicketEmailHtml(
        $registration['fullName'],
        $registration['ticketCode'],
        $registration['attendanceType'],
        $registration['ticketPriceNaira'],
        $event
    );
    $text = AsaliEmailPhp::buildTicketEmailText(
        $registration['fullName'],
        $registration['ticketCode'],
        $registration['attendanceType'],
        $registration['ticketPriceNaira'],
        $event
    );
    $subject = 'Your ticket — ' . $event;
    return AsaliEmailPhp::sendWithPhpMailer($registration['email'], $subject, $html, $text);
}

function cavemen_handle_api_health()
{
    $hasFw = (string) cavemen_env('FLUTTERWAVE_SECRET_KEY', '') !== '';
    $hasSmtp = (string) cavemen_env('SMTP_HOST', '') !== ''
        && (string) cavemen_env('SMTP_USER', '') !== ''
        && (string) cavemen_env('SMTP_PASS', '') !== '';
    $dbName = cavemen_uses_mysql() ? 'mysql' : 'sqlite';
    cavemen_json_response(200, [
        'ok' => true,
        'database' => $dbName,
        'service' => 'cavemen-africa',
        'php' => true,
        'flutterwaveApi' => $hasFw,
        'smtp' => $hasSmtp,
    ]);
}

function cavemen_handle_api_products()
{
    $cat = null;
    if (isset($_GET['category'])) {
        $c = trim((string) $_GET['category']);
        if ($c !== '' && $c !== 'all') {
            $cat = $c;
        }
    }
    $pdo = cavemen_pdo();
    cavemen_seed_products($pdo);
    $orderBy = cavemen_db_driver($pdo) === 'sqlite' ? 'ORDER BY title COLLATE NOCASE' : 'ORDER BY title';
    if ($cat === null) {
        $stmt = $pdo->query('
        SELECT
          id,
          title,
          short_description AS shortDescription,
          category,
          image,
          flutterwave_url AS flutterwaveUrl
        FROM kanti_products
        WHERE is_active = 1
        ' . $orderBy . '
    ');
    } else {
        $stmt = $pdo->prepare('
        SELECT
          id,
          title,
          short_description AS shortDescription,
          category,
          image,
          flutterwave_url AS flutterwaveUrl
        FROM kanti_products
        WHERE is_active = 1 AND category = :cat
        ' . $orderBy . '
    ');
        $stmt->execute([':cat' => $cat]);
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    cavemen_json_response(200, ['products' => $products]);
}

function cavemen_handle_api_asali_registrations_post()
{
    $pdo = cavemen_pdo();
    $raw = '';
    try {
        $raw = cavemen_read_request_body(65536);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Payload too large') {
            cavemen_json_response(413, ['error' => 'Request body too large.']);
            return;
        }
        throw $e;
    }
    $payload = json_decode($raw ?: '[]', true);
    if (!is_array($payload)) {
        cavemen_json_response(400, ['error' => 'Invalid JSON request body.']);
        return;
    }
    $validation = cavemen_validate_registration($payload);
    if (isset($validation['error'])) {
        cavemen_json_response(400, ['error' => $validation['error']]);
        return;
    }
    $data = $validation['data'];

    $ins = $pdo->prepare('INSERT INTO asali_registrations (
    full_name, phone, email, gender, discovery, attendance_type, ticket_price_naira, notes, payment_status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
    $ins->execute([
        $data['fullName'],
        $data['phone'],
        $data['email'],
        $data['gender'],
        $data['discovery'],
        $data['attendanceType'],
        $data['ticketPriceNaira'],
        $data['notes'],
    ]);
    $registrationId = (int) $pdo->lastInsertId();
    $txRef = 'ASALI-' . $registrationId . '-' . (int) (microtime(true) * 1000);
    $upd = $pdo->prepare('UPDATE asali_registrations SET tx_ref = :tx WHERE id = :id');
    $upd->execute([':tx' => $txRef, ':id' => $registrationId]);

    $thankYou = cavemen_public_base_url() . '/asali/register/thank-you/?tx_ref=' . rawurlencode($txRef);
    $paymentUrl = null;
    if ((string) cavemen_env('FLUTTERWAVE_SECRET_KEY', '') !== '') {
        try {
            $paymentUrl = cavemen_flutterwave_init_payment(
                $txRef,
                $data['ticketPriceNaira'],
                [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'name' => $data['fullName'],
                ],
                $thankYou,
                $registrationId,
                $data['attendanceType']
            );
        } catch (Throwable $e) {
            error_log('[cavemen] Flutterwave init failed: ' . $e->getMessage());
            $del = $pdo->prepare('DELETE FROM asali_registrations WHERE id = ?');
            $del->execute([$registrationId]);
            cavemen_json_response(502, [
                'error' => 'Payment could not be started. Please try again in a moment or contact info@cavemen.africa.',
            ]);
            return;
        }
    } else {
        $links = cavemen_get_payment_links();
        $t = $data['attendanceType'];
        $paymentUrl = isset($links[$t]) && trim($links[$t]) !== '' ? trim($links[$t]) : null;
        if ($paymentUrl === null) {
            $del = $pdo->prepare('DELETE FROM asali_registrations WHERE id = ?');
            $del->execute([$registrationId]);
            cavemen_json_response(503, [
                'error' => "Payment link not configured yet for {$t} tickets.",
            ]);
            return;
        }
    }
    $flow = (string) cavemen_env('FLUTTERWAVE_SECRET_KEY', '') !== '' ? 'flutterwave_api' : 'payment_link';
    cavemen_json_response(201, [
        'ok' => true,
        'registrationId' => $registrationId,
        'message' => 'Registration received successfully.',
        'paymentUrl' => $paymentUrl,
        'ticketPriceNaira' => $data['ticketPriceNaira'],
        'paymentFlow' => $flow,
    ]);
}

function cavemen_handle_api_flutterwave_webhook()
{
    $expected = cavemen_env('FLUTTERWAVE_SECRET_HASH');
    if ($expected === null || $expected === '') {
        cavemen_json_response(503, ['error' => 'Webhook not configured (FLUTTERWAVE_SECRET_HASH).']);
        return;
    }
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $verif = '';
    if (is_array($headers)) {
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === 'verif-hash') {
                $verif = (string) $v;
                break;
            }
        }
    }
    if ($verif === '' && !empty($_SERVER['HTTP_VERIF_HASH'])) {
        $verif = (string) $_SERVER['HTTP_VERIF_HASH'];
    }
    if ($verif !== $expected) {
        cavemen_json_response(401, ['error' => 'Invalid webhook signature.']);
        return;
    }
    $raw = '';
    try {
        $raw = cavemen_read_request_body(2 * 1024 * 1024);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Payload too large') {
            cavemen_json_response(413, ['error' => 'Payload too large.']);
            return;
        }
        throw $e;
    }
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        cavemen_json_response(400, ['error' => 'Invalid JSON.']);
        return;
    }
    if (($body['event'] ?? '') !== 'charge.completed') {
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    $d = $body['data'] ?? null;
    if (!is_array($d) || ($d['status'] ?? '') !== 'successful') {
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    $transactionId = $d['id'] ?? null;
    $txRef = $d['tx_ref'] ?? null;
    if ($transactionId === null || $transactionId === '' || $txRef === null || $txRef === '') {
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    if (!cavemen_flutterwave_verify_transaction($transactionId)) {
        error_log('[cavemen] Flutterwave verify failed for ' . (string) $transactionId);
        cavemen_json_response(400, ['error' => 'Verification failed.']);
        return;
    }
    $meta = cavemen_meta_to_object($d['meta'] ?? null);
    $idFromMeta = null;
    if (isset($meta['registration_id'])) {
        $idFromMeta = (int) $meta['registration_id'];
    }
    $metaIdValid = $idFromMeta !== null && $idFromMeta > 0;

    $pdo = cavemen_pdo();
    $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    if (!$reg && $metaIdValid) {
        $byId = cavemen_fetch_registration_by_id($pdo, $idFromMeta);
        if ($byId && ($byId['txRef'] ?? null) === $txRef) {
            $reg = $byId;
        }
    }
    if (!$reg) {
        error_log('[cavemen] Webhook: registration not found ' . (string) $txRef);
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    if ($reg['paymentStatus'] === 'paid' && !empty($reg['ticketEmailSentAt'])) {
        cavemen_json_response(200, ['received' => true, 'duplicate' => true]);
        return;
    }
    $ticketCode = $reg['ticketCode'] ?: cavemen_generate_ticket_code($reg['id']);
    if ($reg['paymentStatus'] !== 'paid') {
        $st = $pdo->prepare('UPDATE asali_registrations SET
      payment_status = \'paid\',
      flutterwave_transaction_id = :tid,
      ticket_code = :code
     WHERE id = :id');
        $st->execute([
            ':tid' => (string) $transactionId,
            ':code' => $ticketCode,
            ':id' => $reg['id'],
        ]);
    } elseif (empty($reg['ticketCode'])) {
        $st = $pdo->prepare('UPDATE asali_registrations SET ticket_code = :code WHERE id = :id');
        $st->execute([':code' => $ticketCode, ':id' => $reg['id']]);
    }
    $reg = cavemen_fetch_registration_by_id($pdo, $reg['id']);
    $reg['ticketCode'] = $ticketCode;
    if (empty($reg['ticketEmailSentAt'])) {
        try {
            $sent = cavemen_send_ticket_email_php($reg);
            if ($sent) {
                $m = $pdo->prepare('UPDATE asali_registrations SET ticket_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?');
                $m->execute([$reg['id']]);
            }
        } catch (Throwable $e) {
            error_log('[cavemen] ticket email: ' . $e->getMessage());
        }
    }
    cavemen_json_response(200, ['received' => true]);
}

function cavemen_handle_asali_payment_status()
{
    $txRef = '';
    if (isset($_GET['tx_ref'])) {
        $txRef = trim((string) $_GET['tx_ref']);
    }
    if ($txRef === '' && isset($_GET['txRef'])) {
        $txRef = trim((string) $_GET['txRef']);
    }
    if ($txRef === '') {
        cavemen_json_response(400, ['error' => 'tx_ref is required.']);
        return;
    }
    $pdo = cavemen_pdo();
    $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    if (!$reg) {
        cavemen_json_response(404, ['error' => 'Registration not found.']);
        return;
    }
    $paid = $reg['paymentStatus'] === 'paid';
    $hasCode = !empty($reg['ticketCode']);
    $ticketReady = $paid && $hasCode;
    $pdf = $ticketReady
        ? '/api/asali-ticket.pdf?tx_ref=' . rawurlencode($txRef)
        : null;
    cavemen_json_response(200, [
        'status' => $paid ? 'paid' : 'pending',
        'eventName' => cavemen_event_name(),
        'fullName' => $reg['fullName'],
        'attendanceType' => $reg['attendanceType'],
        'ticketPriceNaira' => $reg['ticketPriceNaira'],
        'ticketCode' => $reg['ticketCode'] ?: null,
        'ticketReady' => $ticketReady,
        'pdfUrl' => $pdf,
    ]);
}

function cavemen_output_asali_ticket_pdf()
{
    $txRef = '';
    if (isset($_GET['tx_ref'])) {
        $txRef = trim((string) $_GET['tx_ref']);
    }
    if ($txRef === '' && isset($_GET['txRef'])) {
        $txRef = trim((string) $_GET['txRef']);
    }
    if ($txRef === '') {
        cavemen_json_response(400, ['error' => 'tx_ref is required.']);
        return;
    }
    $pdo = cavemen_pdo();
    $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    if (!$reg || $reg['paymentStatus'] !== 'paid' || empty($reg['ticketCode'])) {
        cavemen_json_response(404, [
            'error' => 'Ticket not available yet. If you just paid, wait a few seconds and try again, or use the link in your email.',
        ]);
        return;
    }
    $autoload = cavemen_site_root() . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        cavemen_json_response(500, [
            'error' => 'PDF is not set up. Run: composer install in the site/ folder (requires dompdf).',
        ]);
        return;
    }
    require_once $autoload;
    require_once cavemen_site_root() . '/lib/AsaliTicketPdfHtml.php';
    $html = AsaliTicketPdfHtml::build(
        $reg,
        cavemen_event_name(),
        cavemen_env('ASALI_VENUE_LINE', 'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria'),
        $txRef
    );
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="cavemen-asali-ticket.pdf"');
    header('Cache-Control: no-store');
    echo $dompdf->output();
    exit;
}
