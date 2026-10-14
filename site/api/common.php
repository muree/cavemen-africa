<?php

/**
 * Shared PHP API (cPanel): MySQL (when MYSQL_* is set) or SQLite, Flutterwave, ticket email.
 * Endpoints: health, products, asali-registrations, asali-campus-registrations, dahk-registrations, webhooks/flutterwave
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
require_once __DIR__ . '/../lib/CavemenCheckoutEmail.php';
require_once __DIR__ . '/../lib/CavemenTicketQr.php';
require_once __DIR__ . '/../lib/CavemenTicketTier.php';
require_once __DIR__ . '/../lib/CavemenShareCard.php';

function cavemen_env($key, $default = null)
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $default;
}

function cavemen_flutterwave_secret_key()
{
    return (string) cavemen_env('FLUTTERWAVE_SECRET_KEY', '');
}

function cavemen_flutterwave_public_key()
{
    return (string) cavemen_env('FLUTTERWAVE_PUBLIC_KEY', '');
}

function cavemen_flutterwave_encryption_key()
{
    return (string) cavemen_env('FLUTTERWAVE_ENCRYPTION_KEY', '');
}

function cavemen_flutterwave_client_id()
{
    $v = (string) cavemen_env('FLUTTERWAVE_CLIENT_ID', '');
    if ($v !== '') {
        return $v;
    }
    return (string) cavemen_env('FLW_CLIENT_ID', '');
}

function cavemen_flutterwave_client_secret()
{
    $v = (string) cavemen_env('FLUTTERWAVE_CLIENT_SECRET', '');
    if ($v !== '') {
        return $v;
    }
    return (string) cavemen_env('FLW_CLIENT_SECRET', '');
}

function cavemen_flutterwave_uses_v4()
{
    return cavemen_flutterwave_client_id() !== '' && cavemen_flutterwave_client_secret() !== '';
}

function cavemen_flutterwave_base_url()
{
    $override = (string) cavemen_env('FLUTTERWAVE_BASE_URL', '');
    if ($override !== '') {
        return rtrim($override, '/');
    }
    $env = strtolower((string) cavemen_env('FLUTTERWAVE_ENVIRONMENT', 'live'));
    if ($env === 'sandbox' || $env === 'test') {
        return 'https://developersandbox-api.flutterwave.com';
    }
    return 'https://f4bexperience.flutterwave.com';
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

function cavemen_cloudinary_asset_url($relativePath, $fallback = null)
{
    static $map = null;
    if ($map === null) {
        $file = cavemen_site_root() . '/data/cloudinary-assets.json';
        $json = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        $map = is_array($json) && isset($json['assets']) && is_array($json['assets']) ? $json['assets'] : [];
    }
    if (isset($map[$relativePath]['secureUrl']) && $map[$relativePath]['secureUrl'] !== '') {
        return (string) $map[$relativePath]['secureUrl'];
    }
    if ($fallback !== null && $fallback !== '') {
        return (string) $fallback;
    }
    return cavemen_public_base_url() . '/' . ltrim((string) $relativePath, '/');
}

/**
 * Ticket PDF URL for browsers. Uses cavemen-api.php (not /api/*) so it works when a host or CDN blocks /api/.
 */
function cavemen_public_ticket_pdf_url($txRef, $isDahk)
{
    $route = $isDahk ? 'dahk-ticket-pdf' : 'asali-ticket-pdf';
    return '/cavemen-api.php?route=' . $route . '&tx_ref=' . rawurlencode((string) $txRef);
}

function cavemen_event_name()
{
    return cavemen_env('ASALI_EVENT_NAME', 'Asali Poetry Sessions Season II 1.0');
}

function cavemen_tatsuniya_event_name()
{
    return cavemen_env('TATSUNIYA_EVENT_NAME', 'Tatsuniya Creative Workshop');
}

function cavemen_tatsuniya_fee_naira()
{
    return (int) cavemen_env('TATSUNIYA_FEE_NAIRA', '15000');
}

function cavemen_dahk_event_name()
{
    return cavemen_env('DAHK_SEASONS_EVENT_NAME', 'DAHK: The Experience');
}

function cavemen_dahk_venue_line()
{
    return cavemen_env(
        'DAHK_SEASONS_VENUE_LINE',
        'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria'
    );
}

function cavemen_asali_venue_line()
{
    return cavemen_env(
        'ASALI_VENUE_LINE',
        'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria'
    );
}

function cavemen_event_venue($isDahk)
{
    return $isDahk ? cavemen_dahk_venue_line() : cavemen_asali_venue_line();
}

function cavemen_event_when($isDahk)
{
    if ($isDahk) {
        return cavemen_env('DAHK_SEASONS_EVENT_WHEN', 'Sunday 10 May 2026 · 3:00 PM – 6:00 PM');
    }

    return cavemen_env('ASALI_EVENT_WHEN', 'Session date announced on Cavemen channels');
}

function cavemen_event_flier_url($isDahk)
{
    $key = $isDahk ? 'DAHK_EVENT_FLIER_URL' : 'ASALI_EVENT_FLIER_URL';
    $set = cavemen_env($key, '');
    if ($set) {
        return $set;
    }
    if ($isDahk) {
        return cavemen_cloudinary_asset_url('assets/dahk-registration-banner.png');
    }

    return cavemen_cloudinary_asset_url('assets/asali-registration-banner.png', '');
}

function cavemen_format_ngn($amount)
{
    return '₦' . number_format((float) $amount, 0, '.', ',');
}

function cavemen_dahk_ticket_types()
{
    return [
        'DAHK Package' => ['ticketPriceNaira' => 20000],
    ];
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
    $pdo->exec('
CREATE TABLE IF NOT EXISTS dahk_seasons_registrations (
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
  KEY idx_dahk_tx_ref (tx_ref),
  KEY idx_dahk_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
    $pdo->exec('
CREATE TABLE IF NOT EXISTS tatsuniya_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  child_name VARCHAR(255) NOT NULL,
  child_age TINYINT UNSIGNED NOT NULL,
  child_school VARCHAR(255) NULL,
  parent_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  discovery VARCHAR(512) NOT NULL,
  medical TEXT NULL,
  notes TEXT NULL,
  ticket_price_naira INT UNSIGNED NOT NULL DEFAULT 0,
  payment_status VARCHAR(24) NOT NULL DEFAULT \'pending\',
  tx_ref VARCHAR(128) NULL,
  ticket_code VARCHAR(64) NULL,
  flutterwave_transaction_id VARCHAR(128) NULL,
  ticket_email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tats_tx_ref (tx_ref),
  KEY idx_tats_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
    $pdo->exec('
CREATE TABLE IF NOT EXISTS kanti_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  address VARCHAR(512) NOT NULL,
  city VARCHAR(128) NOT NULL,
  state VARCHAR(128) NOT NULL,
  product_type VARCHAR(64) NOT NULL,
  selected_scent VARCHAR(64) NULL,
  product_title VARCHAR(512) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price_naira INT UNSIGNED NOT NULL DEFAULT 0,
  total_naira INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  payment_status VARCHAR(24) NOT NULL DEFAULT \'pending\',
  tx_ref VARCHAR(128) NULL,
  flutterwave_transaction_id VARCHAR(128) NULL,
  confirmation_email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kanti_tx_ref (tx_ref),
  KEY idx_kanti_email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
    $pdo->exec('
CREATE TABLE IF NOT EXISTS asali_campus_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  gender VARCHAR(64) NOT NULL,
  discovery VARCHAR(512) NOT NULL,
  notes TEXT NULL,
  campus VARCHAR(128) NOT NULL DEFAULT \'unimaid\',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_asali_campus_email (email(191))
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

  CREATE TABLE IF NOT EXISTS dahk_seasons_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    gender TEXT NOT NULL,
    discovery TEXT NOT NULL,
    attendance_type TEXT NOT NULL,
    ticket_price_naira INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    payment_status TEXT NOT NULL DEFAULT \'pending\',
    tx_ref TEXT,
    ticket_code TEXT,
    flutterwave_transaction_id TEXT,
    ticket_email_sent_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS tatsuniya_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_name TEXT NOT NULL,
    child_age INTEGER NOT NULL,
    child_school TEXT,
    parent_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    discovery TEXT NOT NULL,
    medical TEXT,
    notes TEXT,
    ticket_price_naira INTEGER NOT NULL DEFAULT 0,
    payment_status TEXT NOT NULL DEFAULT \'pending\',
    tx_ref TEXT,
    ticket_code TEXT,
    flutterwave_transaction_id TEXT,
    ticket_email_sent_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS kanti_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    city TEXT NOT NULL,
    state TEXT NOT NULL,
    product_type TEXT NOT NULL,
    selected_scent TEXT,
    product_title TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price_naira INTEGER NOT NULL DEFAULT 0,
    total_naira INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    payment_status TEXT NOT NULL DEFAULT \'pending\',
    tx_ref TEXT,
    flutterwave_transaction_id TEXT,
    confirmation_email_sent_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS asali_campus_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    gender TEXT NOT NULL,
    discovery TEXT NOT NULL,
    notes TEXT,
    campus TEXT NOT NULL DEFAULT \'unimaid\',
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
        'dahk_seasons_registrations' => [
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

function cavemen_validate_asali_campus_registration($payload)
{
    if (!is_array($payload)) {
        $payload = [];
    }
    $fullName = trim((string) ($payload['fullName'] ?? ''));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $gender = trim((string) ($payload['gender'] ?? ''));
    $discovery = trim((string) ($payload['discovery'] ?? ''));
    $notes = cavemen_normalize_optional($payload['notes'] ?? '');

    if ($fullName === '' || $phone === '' || $email === '' || $gender === '' || $discovery === '') {
        return ['error' => 'Please complete all required fields.'];
    }
    if (strpos($email, '@') === false) {
        return ['error' => 'Please enter a valid email address.'];
    }

    return [
        'data' => [
            'fullName' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'gender' => $gender,
            'discovery' => $discovery,
            'notes' => $notes,
            'campus' => 'unimaid',
        ],
    ];
}

function cavemen_validate_dahk_registration($payload)
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
    $types = cavemen_dahk_ticket_types();
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

function cavemen_validate_tatsuniya_registration($payload)
{
    if (!is_array($payload)) {
        $payload = [];
    }
    $childName = trim((string) ($payload['childName'] ?? ''));
    $childAgeRaw = trim((string) ($payload['childAge'] ?? ''));
    $childSchool = cavemen_normalize_optional($payload['childSchool'] ?? '');
    $parentName = trim((string) ($payload['parentName'] ?? ''));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $discovery = trim((string) ($payload['discovery'] ?? ''));
    $medical = cavemen_normalize_optional($payload['medical'] ?? '');
    $notes = cavemen_normalize_optional($payload['notes'] ?? '');
    $age = (int) $childAgeRaw;

    if ($childName === '' || $childAgeRaw === '' || $parentName === '' || $phone === '' || $email === '' || $discovery === '') {
        return ['error' => 'Please complete all required fields.'];
    }
    if (strpos($email, '@') === false) {
        return ['error' => 'Please enter a valid parent or guardian email address.'];
    }
    if ($age < 8 || $age > 15) {
        return ['error' => 'Tatsuniya is open to children between 8 and 15 years old.'];
    }

    return [
        'data' => [
            'childName' => $childName,
            'childAge' => $age,
            'childSchool' => $childSchool,
            'parentName' => $parentName,
            'phone' => $phone,
            'email' => $email,
            'discovery' => $discovery,
            'medical' => $medical,
            'notes' => $notes,
            'ticketPriceNaira' => cavemen_tatsuniya_fee_naira(),
        ],
    ];
}

function cavemen_nigeria_states()
{
    return [
        'Abia',
        'Adamawa',
        'Akwa Ibom',
        'Anambra',
        'Bauchi',
        'Bayelsa',
        'Benue',
        'Borno',
        'Cross River',
        'Delta',
        'Ebonyi',
        'Edo',
        'Ekiti',
        'Enugu',
        'Gombe',
        'Imo',
        'Jigawa',
        'Kaduna',
        'Kano',
        'Katsina',
        'Kebbi',
        'Kogi',
        'Kwara',
        'Lagos',
        'Nasarawa',
        'Niger',
        'Ogun',
        'Ondo',
        'Osun',
        'Oyo',
        'Plateau',
        'Rivers',
        'Sokoto',
        'Taraba',
        'Yobe',
        'Zamfara',
        'F.C.T.',
    ];
}

function cavemen_validate_kanti_order($payload)
{
    if (!is_array($payload)) {
        $payload = [];
    }
    $fullName = trim((string) ($payload['fullName'] ?? ''));
    $phone = trim((string) ($payload['phone'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $address = trim((string) ($payload['address'] ?? ''));
    $city = trim((string) ($payload['city'] ?? ''));
    $state = trim((string) ($payload['state'] ?? ''));
    $productType = trim((string) ($payload['productType'] ?? $payload['product_type'] ?? ''));
    $scentId = strtolower(trim((string) ($payload['selectedScent'] ?? $payload['selected_scent'] ?? '')));
    $notes = cavemen_normalize_optional($payload['notes'] ?? '');
    $quantity = (int) ($payload['quantity'] ?? 1);

    if ($fullName === '' || $phone === '' || $email === '' || $address === '' || $city === '' || $state === '' || $productType === '') {
        return ['error' => 'Please complete all required fields.'];
    }
    if (!in_array($state, cavemen_nigeria_states(), true)) {
        return ['error' => 'Please select a valid Nigerian state or F.C.T.'];
    }
    if (strpos($email, '@') === false) {
        return ['error' => 'Please enter a valid email address.'];
    }
    if ($quantity < 1 || $quantity > 25) {
        return ['error' => 'Quantity must be between 1 and 25.'];
    }
    $catalog = cavemen_kanti_catalog();
    if (!isset($catalog[$productType])) {
        return ['error' => 'Please select a valid Kanti product.'];
    }
    $item = $catalog[$productType];
    $scents = cavemen_kanti_scents();
    $selectedScent = null;
    $title = (string) $item['title'];
    $detail = (string) $item['detail'];
    if (!empty($item['needsScent'])) {
        if (!isset($scents[$scentId])) {
            return ['error' => 'Please select a valid scent.'];
        }
        $selectedScent = $scentId;
        $title = 'DAHK Vial: ' . $scents[$scentId][0];
        $detail = $scents[$scentId][1] . ' • 20ml dropper format';
    }
    $unit = (int) $item['unitPriceNaira'];
    if ($unit < 1) {
        return ['error' => 'This item is not available for checkout yet.'];
    }

    return [
        'data' => [
            'fullName' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'productType' => $productType,
            'selectedScent' => $selectedScent,
            'productTitle' => $title,
            'productDetail' => $detail,
            'quantity' => $quantity,
            'unitPriceNaira' => $unit,
            'totalNaira' => $unit * $quantity,
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

function cavemen_generate_dahk_ticket_code($registrationId)
{
    $suffix = strtoupper(bin2hex(random_bytes(4)));
    return "DAHKS-{$registrationId}-{$suffix}";
}

/**
 * @return 'asali'|'dahk'|'tatsuniya'|'kanti'
 */
function cavemen_checkout_kind($txRef)
{
    $ref = (string) $txRef;
    if (strpos($ref, 'DAHK-') === 0) {
        return 'dahk';
    }
    if (strpos($ref, 'TATS-') === 0) {
        return 'tatsuniya';
    }
    if (strpos($ref, 'KANTI-') === 0) {
        return 'kanti';
    }

    return 'asali';
}

/**
 * @return 'asali_registrations'|'dahk_seasons_registrations'
 */
function cavemen_registration_table_for_txref($txRef)
{
    $kind = cavemen_checkout_kind($txRef);
    if ($kind === 'dahk') {
        return 'dahk_seasons_registrations';
    }
    if ($kind === 'asali') {
        return 'asali_registrations';
    }
    throw new InvalidArgumentException('Not a ticket registration reference');
}

/**
 * @return ?array<string,mixed>
 */
function cavemen_fetch_registration_row_from_table(PDO $pdo, $table, $txRef)
{
    $allowed = ['asali_registrations', 'dahk_seasons_registrations'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid registration table');
    }
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE tx_ref = :tx");
    $stmt->execute([':tx' => $txRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return cavemen_registration_row($row);
}

/**
 * @return array{ok: bool, data?: array, error?: string}
 */
function cavemen_http_post_json($url, $headers, $body, $bearer = null, $timeout = 60)
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
        CURLOPT_TIMEOUT => (int) $timeout,
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
 * @return array{ok: bool, data?: array, error?: string, http?: int}
 */
function cavemen_http_get_json($url, $headers, $bearer = null, $timeout = 60)
{
    $ch = curl_init($url);
    $h = $headers;
    if ($bearer) {
        $h[] = 'Authorization: Bearer ' . $bearer;
    }
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) $timeout,
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

function cavemen_flutterwave_trace_id()
{
    return 'cavemen-' . bin2hex(random_bytes(8));
}

function cavemen_flutterwave_access_token()
{
    static $cached = '';
    static $expiresAt = 0;
    if ($cached !== '' && time() < $expiresAt) {
        return $cached;
    }
    $id = cavemen_flutterwave_client_id();
    $secret = cavemen_flutterwave_client_secret();
    if ($id === '' || $secret === '') {
        throw new RuntimeException('Flutterwave Client ID and Client Secret are required.');
    }
    $ch = curl_init('https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $id,
            'client_secret' => $secret,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = is_string($out) ? json_decode($out, true) : null;
    if ($code < 200 || $code >= 300 || !is_array($j) || empty($j['access_token'])) {
        throw new RuntimeException('Flutterwave could not issue an access token. Check FLUTTERWAVE_CLIENT_ID and FLUTTERWAVE_CLIENT_SECRET.');
    }
    $cached = (string) $j['access_token'];
    $ttl = isset($j['expires_in']) ? (int) $j['expires_in'] : 600;
    $expiresAt = time() + max(60, $ttl - 60);
    return $cached;
}

function cavemen_flutterwave_v4_headers($idempotencyKey)
{
    $headers = [
        'Content-Type: application/json',
        'X-Trace-Id: ' . cavemen_flutterwave_trace_id(),
    ];
    $key = (string) $idempotencyKey;
    if (strlen($key) < 12) {
        $key = str_pad($key, 12, '0');
    }
    if (strlen($key) > 255) {
        $key = substr($key, 0, 255);
    }
    $headers[] = 'X-Idempotency-Key: ' . $key;
    return $headers;
}

function cavemen_flutterwave_split_name($fullName)
{
    $full = trim((string) $fullName);
    if ($full === '') {
        return ['first' => 'Customer', 'last' => 'Cavemen'];
    }
    $parts = preg_split('/\s+/', $full);
    if (!is_array($parts) || $parts === []) {
        return ['first' => $full, 'last' => $full];
    }
    $first = (string) array_shift($parts);
    $last = $parts !== [] ? implode(' ', $parts) : $first;
    return ['first' => $first, 'last' => $last];
}

function cavemen_flutterwave_v4_phone($phone)
{
    $digits = cavemen_fw_normalize_phone($phone);
    $country = '234';
    $number = $digits;
    if (strpos($digits, '234') === 0 && strlen($digits) > 3) {
        $number = substr($digits, 3);
    }
    return ['country_code' => $country, 'number' => $number];
}

function cavemen_flutterwave_v4_find_or_create_customer(array $customer, $idempotencyKey)
{
    $token = cavemen_flutterwave_access_token();
    $base = cavemen_flutterwave_base_url();
    $name = cavemen_flutterwave_split_name(isset($customer['name']) ? $customer['name'] : '');
    $payload = [
        'email' => $customer['email'],
        'name' => $name,
        'phone' => cavemen_flutterwave_v4_phone(isset($customer['phone']) ? $customer['phone'] : ''),
    ];
    $res = cavemen_http_post_json(
        $base . '/customers',
        cavemen_flutterwave_v4_headers($idempotencyKey . '-cus'),
        json_encode($payload),
        $token
    );
    if (($res['ok'] ?? false) && !empty($res['data']['data']['id'])) {
        return (string) $res['data']['data']['id'];
    }
    $http = isset($res['http']) ? (int) $res['http'] : 0;
    if ($http === 409) {
        $lookup = cavemen_http_get_json(
            $base . '/customers?email=' . rawurlencode((string) $customer['email']),
            ['X-Trace-Id: ' . cavemen_flutterwave_trace_id()],
            $token
        );
        $rows = [];
        if (isset($lookup['data']['data']) && is_array($lookup['data']['data'])) {
            $rows = $lookup['data']['data'];
        }
        if (isset($rows['id'])) {
            return (string) $rows['id'];
        }
        if (isset($rows[0]['id'])) {
            return (string) $rows[0]['id'];
        }
    }
    $msg = is_array($res['data'] ?? null) && isset($res['data']['message'])
        ? $res['data']['message']
        : 'Flutterwave customer could not be created.';
    throw new RuntimeException($msg);
}

function cavemen_flutterwave_init_payment_v4($txRef, $amountNaira, $customer, $redirectUrl, $registrationId, $attendanceType, array $flow = [])
{
    $token = cavemen_flutterwave_access_token();
    $customerId = cavemen_flutterwave_v4_find_or_create_customer($customer, (string) $txRef);
    $metaEvent = isset($flow['metaEvent']) ? (string) $flow['metaEvent'] : 'asali';
    $payload = [
        'amount' => (int) $amountNaira,
        'currency' => 'NGN',
        'customer_id' => $customerId,
        'redirect_url' => $redirectUrl,
        'reference' => (string) $txRef,
        'session_duration' => 60,
        'max_retry_attempts' => 3,
        'meta' => cavemen_flutterwave_meta($metaEvent, $registrationId, $attendanceType, $customer),
    ];
    $res = cavemen_http_post_json(
        cavemen_flutterwave_base_url() . '/checkout/sessions',
        cavemen_flutterwave_v4_headers((string) $txRef),
        json_encode($payload),
        $token
    );
    if (!($res['ok'] ?? false)) {
        $msg = is_array($res['data'] ?? null) && isset($res['data']['message'])
            ? $res['data']['message']
            : 'Flutterwave checkout could not be started.';
        throw new RuntimeException($msg);
    }
    $j = $res['data'] ?? [];
    $link = '';
    if (!empty($j['data']['checkout_url'])) {
        $link = (string) $j['data']['checkout_url'];
    } elseif (!empty($j['data']['link'])) {
        $link = (string) $j['data']['link'];
    }
    if ($link === '') {
        throw new RuntimeException($j['message'] ?? 'Flutterwave did not return a checkout URL.');
    }
    return $link;
}

/**
 * @return string|null
 */
function cavemen_flutterwave_init_payment($txRef, $amountNaira, $customer, $redirectUrl, $registrationId, $attendanceType, array $flow = [])
{
    if (cavemen_flutterwave_uses_v4()) {
        return cavemen_flutterwave_init_payment_v4(
            $txRef,
            $amountNaira,
            $customer,
            $redirectUrl,
            $registrationId,
            $attendanceType,
            $flow
        );
    }
    $secret = cavemen_flutterwave_secret_key();
    if ($secret === '') {
        return null;
    }
    $publicBase = cavemen_public_base_url();
    $eventTitle = isset($flow['eventTitle']) ? (string) $flow['eventTitle'] : cavemen_event_name();
    $metaEvent = isset($flow['metaEvent']) ? (string) $flow['metaEvent'] : 'asali';
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
            'title' => $eventTitle,
            'description' => isset($flow['description']) ? (string) $flow['description'] : 'Payment — Cavemen Africa',
            'logo' => cavemen_cloudinary_asset_url('assets/cavemen-icon.svg', $publicBase . '/assets/cavemen-icon.svg'),
        ],
        'meta' => cavemen_flutterwave_meta($metaEvent, $registrationId, $attendanceType, $customer),
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
    $id = (string) $transactionId;
    if ($id === '') {
        return false;
    }
    if (cavemen_flutterwave_uses_v4()) {
        $token = cavemen_flutterwave_access_token();
        $res = cavemen_http_get_json(
            cavemen_flutterwave_base_url() . '/charges/' . rawurlencode($id),
            ['X-Trace-Id: ' . cavemen_flutterwave_trace_id()],
            $token
        );
        if (!($res['ok'] ?? false)) {
            return false;
        }
        $status = isset($res['data']['data']['status']) ? (string) $res['data']['data']['status'] : '';
        return $status === 'succeeded' || $status === 'successful';
    }
    $secret = cavemen_flutterwave_secret_key();
    if ($secret === '') {
        return false;
    }
    $url = 'https://api.flutterwave.com/v3/transactions/' . rawurlencode($id) . '/verify';
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
 * @param 'asali_registrations'|'dahk_seasons_registrations' $table
 *
 * @return ?array<string,mixed>
 */
function cavemen_fetch_registration_by_id(PDO $pdo, $id, $table = 'asali_registrations')
{
    $allowed = ['asali_registrations', 'dahk_seasons_registrations'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid registration table');
    }
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
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
    $table = cavemen_registration_table_for_txref($txRef);

    return cavemen_fetch_registration_row_from_table($pdo, $table, $txRef);
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

function cavemen_flutterwave_meta($metaEvent, $registrationId, $attendanceType, array $customer)
{
    $meta = [
        ['metaname' => 'registration_id', 'metavalue' => (string) $registrationId],
        ['metaname' => 'attendance_type', 'metavalue' => (string) $attendanceType],
        ['metaname' => 'cavemen_event', 'metavalue' => (string) $metaEvent],
        ['metaname' => 'customer_full_name', 'metavalue' => (string) ($customer['name'] ?? '')],
        ['metaname' => 'customer_email', 'metavalue' => (string) ($customer['email'] ?? '')],
    ];
    if ($metaEvent === 'asali' || $metaEvent === 'dahk') {
        $isDahk = $metaEvent === 'dahk';
        $meta[] = ['metaname' => 'event_datetime', 'metavalue' => cavemen_event_when($isDahk)];
        $meta[] = ['metaname' => 'event_venue', 'metavalue' => cavemen_event_venue($isDahk)];
        $meta[] = ['metaname' => 'event_flier', 'metavalue' => cavemen_event_flier_url($isDahk)];
    } elseif ($metaEvent === 'tatsuniya') {
        $meta[] = ['metaname' => 'event_datetime', 'metavalue' => cavemen_env('TATSUNIYA_EVENT_WHEN', 'Dates confirmed after registration')];
        $meta[] = ['metaname' => 'event_venue', 'metavalue' => cavemen_asali_venue_line()];
    }
    return $meta;
}

function cavemen_with_payment_ref($url, $txRef, $email = '')
{
    $url = trim((string) $url);
    if ($url === '') {
        return $url;
    }
    $query = [];
    $parts = parse_url($url);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['tx_ref'] = (string) $txRef;
    if ($email !== '') {
        $query['customer_email'] = $email;
    }
    $rebuilt = '';
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= isset($parts['path']) ? $parts['path'] : '';
    } else {
        $rebuilt = strtok($url, '?');
    }
    return $rebuilt . '?' . http_build_query($query);
}

function cavemen_get_dahk_payment_links()
{
    $path = cavemen_site_root() . '/data/dahk-payment-links.json';
    if (!is_readable($path)) {
        return ['DAHK Package' => ''];
    }
    $j = json_decode(file_get_contents($path), true);
    return is_array($j) ? $j : ['DAHK Package' => ''];
}

function cavemen_get_tatsuniya_payment_links()
{
    $path = cavemen_site_root() . '/data/tatsuniya-payment-links.json';
    if (!is_readable($path)) {
        return ['Workshop' => ''];
    }
    $j = json_decode(file_get_contents($path), true);
    return is_array($j) ? $j : ['Workshop' => ''];
}

function cavemen_get_kanti_payment_links()
{
    $path = cavemen_site_root() . '/data/kanti-payment-links.json';
    if (!is_readable($path)) {
        return [];
    }
    $j = json_decode(file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

function cavemen_kanti_scents()
{
    return [
        'bloom' => ['Bloom', 'Floral, sweet jasmine, fresh green notes'],
        'wilt' => ['Wilt', 'Slight floral and bergamot'],
        'dry' => ['Dry', 'Oud notes and slight sandal'],
        'thorns' => ['Thorns', 'Stronger intense rich oud'],
        'ashes' => ['Ashes', 'Sandalwood notes, dry cedar'],
        'roots' => ['Roots', 'Earthy notes and fresh citrusy scents'],
        'epiphany' => ['Epiphany', 'Chocolate notes, rich arabian notes'],
    ];
}

/**
 * Server-side Kanti catalogue. Unit prices are never taken from the browser.
 *
 * @return array<string,array<string,mixed>>
 */
function cavemen_kanti_catalog()
{
    return [
        'book' => [
            'title' => 'Diary of a Heartbreak Kid (DAHK)',
            'detail' => 'By Salamatu Adamu • Hardcover Anthology',
            'unitPriceNaira' => 20000,
            'needsScent' => false,
        ],
        'single-vial' => [
            'title' => 'DAHK Vial',
            'detail' => '20ml dropper format',
            'unitPriceNaira' => 3000,
            'needsScent' => true,
        ],
        'full-vials-set' => [
            'title' => 'DAHK Seasons Vials — Full Set',
            'detail' => '7 curated oil blends (Bloom to Epiphany) with custom set box',
            'unitPriceNaira' => 21000,
            'needsScent' => false,
        ],
    ];
}

/**
 * @return array{content: string, filename: string}|null
 */
function cavemen_build_ticket_pdf_attachment(array $reg, $txRef, $isDahk)
{
    $autoload = cavemen_site_root() . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        return null;
    }
    require_once $autoload;
    require_once cavemen_site_root() . '/lib/AsaliTicketPdfHtml.php';
    require_once cavemen_site_root() . '/lib/CavemenTicketQr.php';
    $eventName = $isDahk ? cavemen_dahk_event_name() : cavemen_event_name();
    $venue = cavemen_event_venue($isDahk);
    $filename = $isDahk ? 'cavemen-dahk-seasons-ticket.pdf' : 'cavemen-asali-ticket.pdf';
    $html = AsaliTicketPdfHtml::build(
        $reg,
        $eventName,
        $venue,
        (string) $txRef,
        CavemenTicketQr::pngBytes((string) $txRef),
        [
            'isDahk' => $isDahk,
            'eventWhen' => cavemen_event_when($isDahk),
        ]
    );
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    return ['content' => $dompdf->output(), 'filename' => $filename];
}

function cavemen_send_ticket_email_php($registration, $eventOverride = null, $txRef = null, $isDahk = null, array $charge = [])
{
    $txForPdf = $txRef !== null && (string) $txRef !== '' ? (string) $txRef : (string) ($registration['txRef'] ?? '');
    if ($isDahk === null && $txForPdf !== '') {
        $isDahk = strpos($txForPdf, 'DAHK-') === 0;
    }
    $isDahk = (bool) $isDahk;
    $event = $eventOverride !== null && $eventOverride !== ''
        ? (string) $eventOverride
        : ($isDahk ? cavemen_dahk_event_name() : cavemen_event_name());
    $pass = cavemen_ticket_pass_context($registration, $txForPdf, $isDahk, $charge);
    $qrPng = CavemenTicketQr::pngBytes($pass['txRef']);
    $tier = CavemenTicketTier::forType($registration['attendanceType'] ?? '', $isDahk);
    $details = [
        'venue' => $pass['venue'],
        'when' => $pass['when'],
        'txRef' => $pass['txRef'],
        'flierUrl' => $pass['flierUrl'],
        'amountLabel' => $pass['amountLabel'],
        'qrCid' => 'cavemen-gate-qr',
        'hasQr' => $qrPng !== null,
        'tier' => $tier,
        'isDahk' => $isDahk,
    ];

    $shareCards = cavemen_build_share_cards($registration, $pass, $event, $isDahk);
    $details['shareCid'] = 'cavemen-share-card';
    $details['hasShare'] = $shareCards !== [];
    $html = AsaliEmailPhp::buildTicketEmailHtml(
        $pass['fullName'],
        $registration['ticketCode'],
        $registration['attendanceType'],
        $registration['ticketPriceNaira'],
        $event,
        $details
    );
    $text = AsaliEmailPhp::buildTicketEmailText(
        $pass['fullName'],
        $registration['ticketCode'],
        $registration['attendanceType'],
        $registration['ticketPriceNaira'],
        $event,
        $details
    );
    $subject = $tier['key'] === 'standard'
        ? 'Your ticket — ' . $event
        : 'Your ' . $tier['label'] . ' ticket — ' . $event;
    $pdfAttach = null;
    if ($txForPdf !== '' && !empty($registration['ticketCode']) && ($registration['paymentStatus'] ?? '') === 'paid') {
        try {
            $pdfAttach = cavemen_build_ticket_pdf_attachment($registration, $txForPdf, $isDahk);
        } catch (Throwable $e) {
            error_log('[cavemen] ticket PDF for email: ' . $e->getMessage());
        }
    }
    $qrAttach = $qrPng !== null
        ? ['content' => $qrPng, 'filename' => 'gate-pass.png', 'cid' => 'cavemen-gate-qr']
        : null;

    $template = [
        'id' => AsaliEmailPhp::ticketTemplateAlias(),
        'variables' => AsaliEmailPhp::ticketTemplateValues(
            $pass['fullName'],
            $registration['ticketCode'],
            $registration['attendanceType'],
            $registration['ticketPriceNaira'],
            $event,
            $details
        ),
    ];

    $hasResend = (string) cavemen_env('RESEND_API_KEY', '') !== '';
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $tpl = $attempt === 0 ? $template : null;
        if ($hasResend && AsaliEmailPhp::sendWithResend($registration['email'], $subject, $html, $text, $pdfAttach, $qrAttach, $shareCards, $tpl)) {
            return true;
        }
        if ($attempt < 2) {
            usleep(250000 * ($attempt + 1));
        }
    }

    return AsaliEmailPhp::sendWithPhpMailer($registration['email'], $subject, $html, $text, $pdfAttach, $qrAttach, $shareCards);
}

/**
 * Shareable "I'm going" PNGs: a square card for feeds and a tall one for stories.
 *
 * @param array<string,mixed> $registration
 * @param array<string,mixed> $pass
 * @return array<int,array{content:string,filename:string,cid?:string}>
 */
function cavemen_build_share_cards(array $registration, array $pass, $event, $isDahk)
{
    $ctx = [
        'name' => $pass['fullName'],
        'attendanceType' => $registration['attendanceType'] ?? '',
        'isDahk' => $isDahk,
        'eventName' => $event,
        'seriesLabel' => $isDahk ? 'DAHK · The Experience' : 'Asali · Poetry Sessions',
        'when' => $pass['when'],
        'venue' => cavemen_share_venue_line($pass['venue']),
    ];

    $cards = [];
    try {
        $square = CavemenShareCard::pngBytes($ctx, 'square');
        if ($square !== null) {
            $cards[] = ['content' => $square, 'filename' => 'cavemen-share-card.png', 'cid' => 'cavemen-share-card'];
        }
        $story = CavemenShareCard::pngBytes($ctx, 'story');
        if ($story !== null) {
            $cards[] = ['content' => $story, 'filename' => 'cavemen-share-story.png'];
        }
    } catch (Throwable $e) {
        error_log('[cavemen] share card: ' . $e->getMessage());
    }

    return $cards;
}

/**
 * The full postal address is too long for a card; keep the venue and the city.
 */
function cavemen_share_venue_line($venue)
{
    $parts = array_values(array_filter(array_map('trim', explode(',', (string) $venue))));
    if (count($parts) <= 2) {
        return 'Cavemen Africa · ' . implode(', ', $parts);
    }

    return 'Cavemen Africa · ' . $parts[count($parts) - 3] . ', ' . $parts[count($parts) - 2];
}

/**
 * @param array<string,mixed> $registration
 * @param array<string,mixed> $charge Flutterwave charge data (optional)
 * @return array{fullName:string,email:string,txRef:string,amountLabel:string,when:string,venue:string,flierUrl:string}
 */
function cavemen_ticket_pass_context(array $registration, $txRef, $isDahk, array $charge = [])
{
    $meta = cavemen_meta_to_object($charge['meta'] ?? null);
    $customer = is_array($charge['customer'] ?? null) ? $charge['customer'] : [];
    $fullName = (string) ($registration['fullName'] ?? '');
    if ($fullName === '') {
        $fullName = (string) ($meta['customer_full_name'] ?? $customer['name'] ?? '');
    }
    $email = (string) ($registration['email'] ?? '');
    if ($email === '') {
        $email = (string) ($meta['customer_email'] ?? $customer['email'] ?? '');
    }
    $ref = (string) $txRef;
    if ($ref === '') {
        $ref = (string) ($charge['tx_ref'] ?? $registration['txRef'] ?? $registration['ticketCode'] ?? '');
    }
    $amount = isset($charge['amount']) ? (float) $charge['amount'] : (float) ($registration['ticketPriceNaira'] ?? 0);
    $when = (string) ($meta['event_datetime'] ?? '');
    if ($when === '') {
        $when = cavemen_event_when($isDahk);
    }
    $venue = (string) ($meta['event_venue'] ?? '');
    if ($venue === '') {
        $venue = cavemen_event_venue($isDahk);
    }
    $flier = (string) ($meta['event_flier'] ?? '');
    if ($flier === '') {
        $flier = cavemen_event_flier_url($isDahk);
    }

    return [
        'fullName' => $fullName,
        'email' => $email,
        'txRef' => $ref,
        'amountLabel' => cavemen_format_ngn($amount),
        'when' => $when,
        'venue' => $venue,
        'flierUrl' => $flier,
    ];
}

function cavemen_handle_api_health()
{
    $hasFw = cavemen_flutterwave_uses_v4() || cavemen_flutterwave_secret_key() !== '';
    $hasFwPublic = cavemen_flutterwave_public_key() !== '';
    $hasFwEncryption = cavemen_flutterwave_encryption_key() !== '';
    $hasFwClient = cavemen_flutterwave_client_id() !== '';
    $hasSmtp = (string) cavemen_env('SMTP_HOST', '') !== ''
        && (string) cavemen_env('SMTP_USER', '') !== ''
        && (string) cavemen_env('SMTP_PASS', '') !== '';
    $dbName = cavemen_uses_mysql() ? 'mysql' : 'sqlite';
    $dbConnected = false;
    try {
        $pdo = cavemen_pdo();
        $pdo->query('SELECT 1');
        $dbConnected = true;
    } catch (Throwable $e) {
        error_log('[cavemen] health: database unreachable: ' . $e->getMessage());
    }
    $hasResend = (string) cavemen_env('RESEND_API_KEY', '') !== '';
    cavemen_json_response(200, [
        'ok' => true,
        'database' => $dbName,
        'databaseConnected' => $dbConnected,
        'service' => 'cavemen-africa',
        'php' => true,
        'flutterwaveApi' => $hasFw,
        'flutterwaveV4' => cavemen_flutterwave_uses_v4(),
        'flutterwaveClient' => $hasFwClient,
        'flutterwavePublic' => $hasFwPublic,
        'flutterwaveEncryption' => $hasFwEncryption,
        'smtp' => $hasSmtp,
        'resend' => $hasResend,
    ]);
}

function cavemen_handle_api_products()
{
    try {
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
    } catch (Throwable $e) {
        error_log('[cavemen] products API: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Catalog temporarily unavailable.',
            'products' => [],
        ]);
    }
}

function cavemen_handle_api_asali_registrations_post()
{
    try {
        $pdo = cavemen_pdo();
        $raw = '';
        try {
            $raw = cavemen_read_request_body(65536);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Payload too large') {
                cavemen_json_response(413, ['error' => 'Request body too large.']);
                return;
            }
            error_log('[cavemen] Asali registration request body: ' . $e->getMessage());
            cavemen_json_response(400, ['error' => 'Could not read the registration request. Please try again.']);
            return;
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
        $links = cavemen_get_payment_links();
        $t = $data['attendanceType'];
        $static = isset($links[$t]) ? trim((string) $links[$t]) : '';
        $started = cavemen_begin_flutterwave_payment(
            $txRef,
            $data['ticketPriceNaira'],
            [
                'email' => $data['email'],
                'phone' => $data['phone'],
                'name' => $data['fullName'],
            ],
            $thankYou,
            $registrationId,
            $data['attendanceType'],
            ['eventTitle' => cavemen_event_name(), 'metaEvent' => 'asali'],
            $static,
            function () use ($pdo, $registrationId) {
                $del = $pdo->prepare('DELETE FROM asali_registrations WHERE id = ?');
                $del->execute([$registrationId]);
            }
        );
        if (isset($started['error'])) {
            cavemen_json_response($started['status'], ['error' => $started['error']]);
            return;
        }
        $paymentUrl = $started['paymentUrl'];
        $flow = $started['flow'];
        cavemen_json_response(201, [
            'ok' => true,
            'registrationId' => $registrationId,
            'message' => 'Registration received successfully.',
            'paymentUrl' => $paymentUrl,
            'ticketPriceNaira' => $data['ticketPriceNaira'],
            'paymentFlow' => $flow,
        ]);
    } catch (PDOException $e) {
        error_log('[cavemen] Asali registration PDO error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'Registration could not be saved. The database may be misconfigured (check MYSQL_* in .env and cPanel MySQL user privileges).',
        ]);
    } catch (Throwable $e) {
        error_log('[cavemen] Asali registration error: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Registration could not be completed. Please try again in a moment or contact info@cavemen.africa.',
        ]);
    }
}

function cavemen_handle_api_asali_campus_registrations_post()
{
    try {
        $pdo = cavemen_pdo();
        try {
            $raw = cavemen_read_request_body(65536);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Payload too large') {
                cavemen_json_response(413, ['error' => 'Request body too large.']);
                return;
            }
            error_log('[cavemen] Asali campus registration request body: ' . $e->getMessage());
            cavemen_json_response(400, ['error' => 'Could not read the registration request. Please try again.']);
            return;
        }
        $payload = json_decode($raw ?: '[]', true);
        if (!is_array($payload)) {
            cavemen_json_response(400, ['error' => 'Invalid JSON request body.']);
            return;
        }
        $validation = cavemen_validate_asali_campus_registration($payload);
        if (isset($validation['error'])) {
            cavemen_json_response(400, ['error' => $validation['error']]);
            return;
        }
        $data = $validation['data'];

        $ins = $pdo->prepare('INSERT INTO asali_campus_registrations (
    full_name, phone, email, gender, discovery, notes, campus
  ) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $data['fullName'],
            $data['phone'],
            $data['email'],
            $data['gender'],
            $data['discovery'],
            $data['notes'],
            $data['campus'],
        ]);
        $registrationId = (int) $pdo->lastInsertId();
        cavemen_json_response(201, [
            'ok' => true,
            'registrationId' => $registrationId,
            'message' => 'Registration received successfully.',
        ]);
    } catch (PDOException $e) {
        error_log('[cavemen] Asali campus registration PDO error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'Registration could not be saved. The database may be misconfigured (check MYSQL_* in .env and cPanel MySQL user privileges).',
        ]);
    } catch (Throwable $e) {
        error_log('[cavemen] Asali campus registration error: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Registration could not be completed. Please try again in a moment or contact info@cavemen.africa.',
        ]);
    }
}

function cavemen_handle_api_dahk_registrations_post()
{
    try {
        $pdo = cavemen_pdo();
        $raw = '';
        try {
            $raw = cavemen_read_request_body(65536);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Payload too large') {
                cavemen_json_response(413, ['error' => 'Request body too large.']);
                return;
            }
            error_log('[cavemen] DAHK registration request body: ' . $e->getMessage());
            cavemen_json_response(400, ['error' => 'Could not read the registration request. Please try again.']);
            return;
        }
        $payload = json_decode($raw ?: '[]', true);
        if (!is_array($payload)) {
            cavemen_json_response(400, ['error' => 'Invalid JSON request body.']);
            return;
        }
        $validation = cavemen_validate_dahk_registration($payload);
        if (isset($validation['error'])) {
            cavemen_json_response(400, ['error' => $validation['error']]);
            return;
        }
        $data = $validation['data'];

        $ins = $pdo->prepare('INSERT INTO dahk_seasons_registrations (
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
        $txRef = 'DAHK-' . $registrationId . '-' . (int) (microtime(true) * 1000);
        $upd = $pdo->prepare('UPDATE dahk_seasons_registrations SET tx_ref = :tx WHERE id = :id');
        $upd->execute([':tx' => $txRef, ':id' => $registrationId]);

        $thankYou = cavemen_public_base_url() . '/dahk-seasons/register/thank-you/?tx_ref=' . rawurlencode($txRef);
        $links = cavemen_get_dahk_payment_links();
        $t = $data['attendanceType'];
        $static = isset($links[$t]) ? trim((string) $links[$t]) : '';
        $started = cavemen_begin_flutterwave_payment(
            $txRef,
            $data['ticketPriceNaira'],
            [
                'email' => $data['email'],
                'phone' => $data['phone'],
                'name' => $data['fullName'],
            ],
            $thankYou,
            $registrationId,
            $data['attendanceType'],
            ['eventTitle' => cavemen_dahk_event_name(), 'metaEvent' => 'dahk'],
            $static,
            function () use ($pdo, $registrationId) {
                $del = $pdo->prepare('DELETE FROM dahk_seasons_registrations WHERE id = ?');
                $del->execute([$registrationId]);
            }
        );
        if (isset($started['error'])) {
            cavemen_json_response($started['status'], ['error' => $started['error']]);
            return;
        }
        $paymentUrl = $started['paymentUrl'];
        $flow = $started['flow'];
        cavemen_json_response(201, [
            'ok' => true,
            'registrationId' => $registrationId,
            'message' => 'Registration received successfully.',
            'paymentUrl' => $paymentUrl,
            'ticketPriceNaira' => $data['ticketPriceNaira'],
            'paymentFlow' => $flow,
        ]);
    } catch (PDOException $e) {
        error_log('[cavemen] DAHK registration PDO error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'Registration could not be saved. The database may be misconfigured (check MYSQL_* in .env and cPanel MySQL user privileges).',
        ]);
    } catch (Throwable $e) {
        error_log('[cavemen] DAHK registration error: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Registration could not be completed. Please try again in a moment or contact info@cavemen.africa.',
        ]);
    }
}

function cavemen_handle_api_tatsuniya_registrations_post()
{
    try {
        $pdo = cavemen_pdo();
        try {
            $raw = cavemen_read_request_body(65536);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Payload too large') {
                cavemen_json_response(413, ['error' => 'Request body too large.']);
                return;
            }
            cavemen_json_response(400, ['error' => 'Could not read the registration request. Please try again.']);
            return;
        }
        $payload = json_decode($raw ?: '[]', true);
        if (!is_array($payload)) {
            cavemen_json_response(400, ['error' => 'Invalid JSON request body.']);
            return;
        }
        $validation = cavemen_validate_tatsuniya_registration($payload);
        if (isset($validation['error'])) {
            cavemen_json_response(400, ['error' => $validation['error']]);
            return;
        }
        $data = $validation['data'];

        $ins = $pdo->prepare('INSERT INTO tatsuniya_registrations (
    child_name, child_age, child_school, parent_name, phone, email, discovery, medical, notes, ticket_price_naira, payment_status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
        $ins->execute([
            $data['childName'],
            $data['childAge'],
            $data['childSchool'],
            $data['parentName'],
            $data['phone'],
            $data['email'],
            $data['discovery'],
            $data['medical'],
            $data['notes'],
            $data['ticketPriceNaira'],
        ]);
        $registrationId = (int) $pdo->lastInsertId();
        $txRef = 'TATS-' . $registrationId . '-' . (int) (microtime(true) * 1000);
        $upd = $pdo->prepare('UPDATE tatsuniya_registrations SET tx_ref = :tx WHERE id = :id');
        $upd->execute([':tx' => $txRef, ':id' => $registrationId]);

        $thankYou = cavemen_public_base_url() . '/tatsuniya/register/thank-you/?tx_ref=' . rawurlencode($txRef);
        $links = cavemen_get_tatsuniya_payment_links();
        $static = isset($links['Workshop']) ? trim((string) $links['Workshop']) : '';
        $started = cavemen_begin_flutterwave_payment(
            $txRef,
            $data['ticketPriceNaira'],
            [
                'email' => $data['email'],
                'phone' => $data['phone'],
                'name' => $data['parentName'],
            ],
            $thankYou,
            $registrationId,
            'Workshop',
            [
                'eventTitle' => cavemen_tatsuniya_event_name(),
                'metaEvent' => 'tatsuniya',
                'description' => 'Tatsuniya workshop fee — Cavemen Africa',
            ],
            $static,
            function () use ($pdo, $registrationId) {
                $del = $pdo->prepare('DELETE FROM tatsuniya_registrations WHERE id = ?');
                $del->execute([$registrationId]);
            }
        );
        if (isset($started['error'])) {
            cavemen_json_response($started['status'], ['error' => $started['error']]);
            return;
        }
        $paymentUrl = $started['paymentUrl'];
        $flow = $started['flow'];
        cavemen_json_response(201, [
            'ok' => true,
            'registrationId' => $registrationId,
            'message' => 'Registration received successfully.',
            'paymentUrl' => $paymentUrl,
            'ticketPriceNaira' => $data['ticketPriceNaira'],
            'paymentFlow' => $flow,
        ]);
    } catch (PDOException $e) {
        error_log('[cavemen] Tatsuniya registration PDO error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'Registration could not be saved. The database may be misconfigured (check MYSQL_* in .env and cPanel MySQL user privileges).',
        ]);
    } catch (Throwable $e) {
        error_log('[cavemen] Tatsuniya registration error: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Registration could not be completed. Please try again in a moment or contact info@cavemen.africa.',
        ]);
    }
}

function cavemen_handle_api_kanti_orders_post()
{
    try {
        $pdo = cavemen_pdo();
        try {
            $raw = cavemen_read_request_body(65536);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Payload too large') {
                cavemen_json_response(413, ['error' => 'Request body too large.']);
                return;
            }
            cavemen_json_response(400, ['error' => 'Could not read the checkout request. Please try again.']);
            return;
        }
        $payload = json_decode($raw ?: '[]', true);
        if (!is_array($payload)) {
            cavemen_json_response(400, ['error' => 'Invalid JSON request body.']);
            return;
        }
        $validation = cavemen_validate_kanti_order($payload);
        if (isset($validation['error'])) {
            cavemen_json_response(400, ['error' => $validation['error']]);
            return;
        }
        $data = $validation['data'];

        $ins = $pdo->prepare('INSERT INTO kanti_orders (
    full_name, phone, email, address, city, state, product_type, selected_scent, product_title, quantity, unit_price_naira, total_naira, notes, payment_status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
        $ins->execute([
            $data['fullName'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['city'],
            $data['state'],
            $data['productType'],
            $data['selectedScent'],
            $data['productTitle'],
            $data['quantity'],
            $data['unitPriceNaira'],
            $data['totalNaira'],
            $data['notes'],
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $txRef = 'KANTI-' . $orderId . '-' . (int) (microtime(true) * 1000);
        $upd = $pdo->prepare('UPDATE kanti_orders SET tx_ref = :tx WHERE id = :id');
        $upd->execute([':tx' => $txRef, ':id' => $orderId]);

        $thankYou = cavemen_public_base_url() . '/kanti/purchase/thank-you/?tx_ref=' . rawurlencode($txRef);
        $started = cavemen_start_kanti_payment($pdo, $orderId, $txRef, $data, $thankYou);
        if (isset($started['error'])) {
            cavemen_json_response($started['status'], ['error' => $started['error']]);
            return;
        }
        $paymentUrl = $started['paymentUrl'];
        $flow = $started['flow'];
        cavemen_json_response(201, [
            'ok' => true,
            'orderId' => $orderId,
            'message' => 'Order received successfully.',
            'paymentUrl' => $paymentUrl,
            'ticketPriceNaira' => $data['totalNaira'],
            'totalNaira' => $data['totalNaira'],
            'paymentFlow' => $flow,
        ]);
    } catch (PDOException $e) {
        error_log('[cavemen] Kanti order PDO error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'Order could not be saved. The database may be misconfigured (check MYSQL_* in .env and cPanel MySQL user privileges).',
        ]);
    } catch (Throwable $e) {
        error_log('[cavemen] Kanti order error: ' . $e->getMessage());
        cavemen_json_response(500, [
            'error' => 'Checkout could not be completed. Please try again in a moment or contact info@cavemen.africa.',
        ]);
    }
}

function cavemen_request_header_value($name)
{
    $want = strtolower((string) $name);
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (is_array($headers)) {
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $want) {
                return (string) $v;
            }
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $want));
    if (!empty($_SERVER[$serverKey])) {
        return (string) $_SERVER[$serverKey];
    }
    return '';
}

function cavemen_flutterwave_webhook_authorized($raw, $secretHash)
{
    $hmacSig = cavemen_request_header_value('flutterwave-signature');
    if ($hmacSig !== '') {
        $calc = base64_encode(hash_hmac('sha256', (string) $raw, (string) $secretHash, true));
        if (hash_equals($calc, $hmacSig)) {
            return true;
        }
    }
    $verif = cavemen_request_header_value('verif-hash');
    return $verif !== '' && hash_equals((string) $secretHash, $verif);
}

function cavemen_handle_api_flutterwave_webhook()
{
    $expected = cavemen_env('FLUTTERWAVE_SECRET_HASH');
    if ($expected === null || $expected === '') {
        cavemen_json_response(503, ['error' => 'Webhook not configured (FLUTTERWAVE_SECRET_HASH).']);
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
        error_log('[cavemen] webhook body: ' . $e->getMessage());
        cavemen_json_response(400, ['error' => 'Could not read webhook body.']);
        return;
    }
    if (!cavemen_flutterwave_webhook_authorized($raw, (string) $expected)) {
        cavemen_json_response(401, ['error' => 'Invalid webhook signature.']);
        return;
    }
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        cavemen_json_response(400, ['error' => 'Invalid JSON.']);
        return;
    }
    $eventName = (string) ($body['type'] ?? $body['event'] ?? '');
    $accepted = ['charge.completed', 'charge.success', 'charge.successful'];
    if (!in_array($eventName, $accepted, true)) {
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    $d = $body['data'] ?? null;
    $status = is_array($d) ? (string) ($d['status'] ?? '') : '';
    if (!is_array($d) || ($status !== 'successful' && $status !== 'succeeded')) {
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    $transactionId = $d['id'] ?? null;
    $txRef = $d['tx_ref'] ?? ($d['reference'] ?? null);
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
    $kind = cavemen_checkout_kind((string) $txRef);
    if (isset($meta['cavemen_event'])) {
        $fromMeta = (string) $meta['cavemen_event'];
        if (in_array($fromMeta, ['asali', 'dahk', 'tatsuniya', 'kanti'], true)) {
            $kind = $fromMeta;
        }
    }
    $pdo = cavemen_pdo();
    if ($kind === 'tatsuniya') {
        cavemen_mark_tatsuniya_paid($pdo, (string) $txRef, (string) $transactionId, $meta);
        return;
    }
    if ($kind === 'kanti') {
        cavemen_mark_kanti_paid($pdo, (string) $txRef, (string) $transactionId, $meta);
        return;
    }

    $idFromMeta = null;
    if (isset($meta['registration_id'])) {
        $idFromMeta = (int) $meta['registration_id'];
    }
    $metaIdValid = $idFromMeta !== null && $idFromMeta > 0;
    $tableFromMeta = $kind === 'dahk' ? 'dahk_seasons_registrations' : 'asali_registrations';

    try {
        $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
        $table = cavemen_registration_table_for_txref((string) $txRef);
    } catch (InvalidArgumentException $e) {
        $reg = null;
        $table = $tableFromMeta;
    }
    if (!$reg && $metaIdValid) {
        $byId = cavemen_fetch_registration_by_id($pdo, $idFromMeta, $tableFromMeta);
        if ($byId && ($byId['txRef'] ?? null) === $txRef) {
            $reg = $byId;
            $table = $tableFromMeta;
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
    $ticketCode = $reg['ticketCode'] ?: (
        $table === 'dahk_seasons_registrations'
            ? cavemen_generate_dahk_ticket_code($reg['id'])
            : cavemen_generate_ticket_code($reg['id'])
    );
    if ($reg['paymentStatus'] !== 'paid') {
        $st = $pdo->prepare("UPDATE {$table} SET
      payment_status = 'paid',
      flutterwave_transaction_id = :tid,
      ticket_code = :code
     WHERE id = :id");
        $st->execute([
            ':tid' => (string) $transactionId,
            ':code' => $ticketCode,
            ':id' => $reg['id'],
        ]);
    } elseif (empty($reg['ticketCode'])) {
        $st = $pdo->prepare("UPDATE {$table} SET ticket_code = :code WHERE id = :id");
        $st->execute([':code' => $ticketCode, ':id' => $reg['id']]);
    }
    $reg = cavemen_fetch_registration_by_id($pdo, $reg['id'], $table);
    $reg['ticketCode'] = $ticketCode;
    if (empty($reg['ticketEmailSentAt'])) {
        try {
            $eventNameForEmail = $table === 'dahk_seasons_registrations' ? cavemen_dahk_event_name() : cavemen_event_name();
            $sent = cavemen_send_ticket_email_php(
                $reg,
                $eventNameForEmail,
                (string) $txRef,
                $table === 'dahk_seasons_registrations',
                is_array($d) ? $d : []
            );
            if ($sent) {
                $m = $pdo->prepare("UPDATE {$table} SET ticket_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
                $m->execute([$reg['id']]);
            }
        } catch (Throwable $e) {
            error_log('[cavemen] ticket email: ' . $e->getMessage());
        }
    }
    cavemen_json_response(200, ['received' => true]);
}

function cavemen_generate_tatsuniya_code($registrationId)
{
    $suffix = strtoupper(bin2hex(random_bytes(4)));
    return 'TATS-' . $registrationId . '-' . $suffix;
}

function cavemen_fetch_tatsuniya_by_txref(PDO $pdo, $txRef)
{
    $stmt = $pdo->prepare('SELECT * FROM tatsuniya_registrations WHERE tx_ref = :tx');
    $stmt->execute([':tx' => $txRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? cavemen_tatsuniya_row($row) : null;
}

function cavemen_tatsuniya_row($row)
{
    return [
        'id' => (int) $row['id'],
        'childName' => $row['child_name'],
        'childAge' => (int) $row['child_age'],
        'childSchool' => $row['child_school'],
        'parentName' => $row['parent_name'],
        'fullName' => $row['parent_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'discovery' => $row['discovery'],
        'medical' => $row['medical'],
        'notes' => $row['notes'],
        'ticketPriceNaira' => (int) $row['ticket_price_naira'],
        'paymentStatus' => $row['payment_status'] ?? 'pending',
        'txRef' => $row['tx_ref'] ?? null,
        'ticketCode' => $row['ticket_code'] ?? null,
        'flutterwaveTransactionId' => $row['flutterwave_transaction_id'] ?? null,
        'ticketEmailSentAt' => $row['ticket_email_sent_at'] ?? null,
    ];
}

function cavemen_fetch_kanti_by_txref(PDO $pdo, $txRef)
{
    $stmt = $pdo->prepare('SELECT * FROM kanti_orders WHERE tx_ref = :tx');
    $stmt->execute([':tx' => $txRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? cavemen_kanti_order_row($row) : null;
}

function cavemen_kanti_order_row($row)
{
    return [
        'id' => (int) $row['id'],
        'fullName' => $row['full_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'address' => $row['address'],
        'city' => $row['city'],
        'state' => $row['state'],
        'productType' => $row['product_type'],
        'selectedScent' => $row['selected_scent'],
        'productTitle' => $row['product_title'],
        'quantity' => (int) $row['quantity'],
        'unitPriceNaira' => (int) $row['unit_price_naira'],
        'totalNaira' => (int) $row['total_naira'],
        'notes' => $row['notes'],
        'paymentStatus' => $row['payment_status'] ?? 'pending',
        'txRef' => $row['tx_ref'] ?? null,
        'flutterwaveTransactionId' => $row['flutterwave_transaction_id'] ?? null,
        'confirmationEmailSentAt' => $row['confirmation_email_sent_at'] ?? null,
    ];
}

function cavemen_mark_tatsuniya_paid(PDO $pdo, $txRef, $transactionId, array $meta)
{
    $reg = cavemen_fetch_tatsuniya_by_txref($pdo, $txRef);
    if (!$reg && isset($meta['registration_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM tatsuniya_registrations WHERE id = :id');
        $stmt->execute([':id' => (int) $meta['registration_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['tx_ref'] ?? null) === $txRef) {
            $reg = cavemen_tatsuniya_row($row);
        }
    }
    if (!$reg) {
        error_log('[cavemen] Webhook: Tatsuniya registration not found ' . $txRef);
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    if ($reg['paymentStatus'] === 'paid' && !empty($reg['ticketEmailSentAt'])) {
        cavemen_json_response(200, ['received' => true, 'duplicate' => true]);
        return;
    }
    $code = $reg['ticketCode'] ?: cavemen_generate_tatsuniya_code($reg['id']);
    if ($reg['paymentStatus'] !== 'paid') {
        $st = $pdo->prepare("UPDATE tatsuniya_registrations SET
      payment_status = 'paid',
      flutterwave_transaction_id = :tid,
      ticket_code = :code
     WHERE id = :id");
        $st->execute([
            ':tid' => $transactionId,
            ':code' => $code,
            ':id' => $reg['id'],
        ]);
    } elseif (empty($reg['ticketCode'])) {
        $st = $pdo->prepare('UPDATE tatsuniya_registrations SET ticket_code = :code WHERE id = :id');
        $st->execute([':code' => $code, ':id' => $reg['id']]);
    }
    $reg = cavemen_fetch_tatsuniya_by_txref($pdo, $txRef);
    if ($reg && empty($reg['ticketEmailSentAt'])) {
        try {
            $built = CavemenCheckoutEmail::build(
                $reg['parentName'],
                'Tatsuniya registration confirmed',
                'your workshop place is confirmed.',
                [
                    'Child' => $reg['childName'] . ' (' . $reg['childAge'] . ' years)',
                    'Workshop' => cavemen_tatsuniya_event_name(),
                    'Fee' => cavemen_format_ngn($reg['ticketPriceNaira']),
                    'Confirmation' => $code,
                    'Reference' => $txRef,
                ],
                'Bring this confirmation to the workshop. If you have questions, write to info@cavemen.africa.'
            );
            $sent = CavemenCheckoutEmail::send(
                $reg['email'],
                'Your Tatsuniya place is confirmed — Cavemen Africa',
                $built['html'],
                $built['text']
            );
            if ($sent) {
                $m = $pdo->prepare('UPDATE tatsuniya_registrations SET ticket_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?');
                $m->execute([$reg['id']]);
            }
        } catch (Throwable $e) {
            error_log('[cavemen] Tatsuniya confirmation email: ' . $e->getMessage());
        }
    }
    cavemen_json_response(200, ['received' => true]);
}

function cavemen_mark_kanti_paid(PDO $pdo, $txRef, $transactionId, array $meta)
{
    $order = cavemen_fetch_kanti_by_txref($pdo, $txRef);
    if (!$order && isset($meta['registration_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM kanti_orders WHERE id = :id');
        $stmt->execute([':id' => (int) $meta['registration_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['tx_ref'] ?? null) === $txRef) {
            $order = cavemen_kanti_order_row($row);
        }
    }
    if (!$order) {
        error_log('[cavemen] Webhook: Kanti order not found ' . $txRef);
        cavemen_json_response(200, ['received' => true, 'ignored' => true]);
        return;
    }
    if ($order['paymentStatus'] === 'paid' && !empty($order['confirmationEmailSentAt'])) {
        cavemen_json_response(200, ['received' => true, 'duplicate' => true]);
        return;
    }
    if ($order['paymentStatus'] !== 'paid') {
        $st = $pdo->prepare("UPDATE kanti_orders SET
      payment_status = 'paid',
      flutterwave_transaction_id = :tid
     WHERE id = :id");
        $st->execute([
            ':tid' => $transactionId,
            ':id' => $order['id'],
        ]);
    }
    $order = cavemen_fetch_kanti_by_txref($pdo, $txRef);
    if ($order && empty($order['confirmationEmailSentAt'])) {
        try {
            $delivery = $order['address'] . ', ' . $order['city'] . ', ' . $order['state'];
            $built = CavemenCheckoutEmail::build(
                $order['fullName'],
                'Kanti order confirmed',
                'we have received your payment and will pack this order by hand.',
                [
                    'Item' => $order['productTitle'],
                    'Quantity' => (string) $order['quantity'],
                    'Total' => cavemen_format_ngn($order['totalNaira']),
                    'Delivery' => $delivery,
                    'Reference' => $txRef,
                ],
                'We will be in touch about delivery. Questions: info@cavemen.africa.'
            );
            $sent = CavemenCheckoutEmail::send(
                $order['email'],
                'Your Kanti order — Cavemen Africa',
                $built['html'],
                $built['text']
            );
            if ($sent) {
                $m = $pdo->prepare('UPDATE kanti_orders SET confirmation_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?');
                $m->execute([$order['id']]);
            }
        } catch (Throwable $e) {
            error_log('[cavemen] Kanti confirmation email: ' . $e->getMessage());
        }
    }
    cavemen_json_response(200, ['received' => true]);
}

function cavemen_handle_registration_payment_status()
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
    try {
        $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    } catch (InvalidArgumentException $e) {
        cavemen_json_response(404, ['error' => 'Registration not found.']);
        return;
    }
    if (!$reg) {
        cavemen_json_response(404, ['error' => 'Registration not found.']);
        return;
    }
    $table = cavemen_registration_table_for_txref($txRef);
    $isDahk = $table === 'dahk_seasons_registrations';
    $paid = $reg['paymentStatus'] === 'paid';
    $hasCode = !empty($reg['ticketCode']);
    $ticketReady = $paid && $hasCode;
    $pdf = $ticketReady ? cavemen_public_ticket_pdf_url($txRef, $isDahk) : null;
    $eventName = $isDahk ? cavemen_dahk_event_name() : cavemen_event_name();
    cavemen_json_response(200, [
        'status' => $paid ? 'paid' : 'pending',
        'eventName' => $eventName,
        'fullName' => $reg['fullName'],
        'attendanceType' => $reg['attendanceType'],
        'ticketPriceNaira' => $reg['ticketPriceNaira'],
        'ticketCode' => $reg['ticketCode'] ?: null,
        'ticketReady' => $ticketReady,
        'pdfUrl' => $pdf,
    ]);
}

function cavemen_handle_asali_payment_status()
{
    cavemen_handle_registration_payment_status();
}

function cavemen_handle_dahk_payment_status()
{
    cavemen_handle_registration_payment_status();
}

function cavemen_read_tx_ref_query()
{
    $txRef = '';
    if (isset($_GET['tx_ref'])) {
        $txRef = trim((string) $_GET['tx_ref']);
    }
    if ($txRef === '' && isset($_GET['txRef'])) {
        $txRef = trim((string) $_GET['txRef']);
    }
    return $txRef;
}

function cavemen_handle_tatsuniya_payment_status()
{
    $txRef = cavemen_read_tx_ref_query();
    if ($txRef === '') {
        cavemen_json_response(400, ['error' => 'tx_ref is required.']);
        return;
    }
    $pdo = cavemen_pdo();
    $reg = cavemen_fetch_tatsuniya_by_txref($pdo, $txRef);
    if (!$reg) {
        cavemen_json_response(404, ['error' => 'Registration not found.']);
        return;
    }
    $paid = $reg['paymentStatus'] === 'paid';
    cavemen_json_response(200, [
        'status' => $paid ? 'paid' : 'pending',
        'eventName' => cavemen_tatsuniya_event_name(),
        'fullName' => $reg['parentName'],
        'childName' => $reg['childName'],
        'attendanceType' => 'Workshop',
        'ticketPriceNaira' => $reg['ticketPriceNaira'],
        'ticketCode' => $reg['ticketCode'] ?: null,
        'ticketReady' => $paid && !empty($reg['ticketCode']),
        'pdfUrl' => null,
    ]);
}

function cavemen_handle_kanti_payment_status()
{
    $txRef = cavemen_read_tx_ref_query();
    if ($txRef === '') {
        cavemen_json_response(400, ['error' => 'tx_ref is required.']);
        return;
    }
    $pdo = cavemen_pdo();
    $order = cavemen_fetch_kanti_by_txref($pdo, $txRef);
    if (!$order) {
        cavemen_json_response(404, ['error' => 'Order not found.']);
        return;
    }
    $paid = $order['paymentStatus'] === 'paid';
    cavemen_json_response(200, [
        'status' => $paid ? 'paid' : 'pending',
        'eventName' => $order['productTitle'],
        'fullName' => $order['fullName'],
        'productTitle' => $order['productTitle'],
        'quantity' => $order['quantity'],
        'ticketPriceNaira' => $order['totalNaira'],
        'totalNaira' => $order['totalNaira'],
        'ticketCode' => null,
        'ticketReady' => $paid,
        'pdfUrl' => null,
    ]);
}

function cavemen_hosted_pay_url($txRef)
{
    return cavemen_public_base_url() . '/cavemen-api.php?route=hosted-pay&tx_ref=' . rawurlencode((string) $txRef);
}

/**
 * One checkout path for Asali, DAHK, Tatsuniya, and Kanti.
 *
 * @param callable():void $onAbort
 * @param array<string,mixed> $flow
 * @param array<string,string> $customer
 * @return array{paymentUrl?:string,flow?:string,error?:string,status?:int}
 */
function cavemen_begin_flutterwave_payment($txRef, $amountNaira, array $customer, $thankYou, $registrationId, $attendanceType, array $flow, $staticUrl, $onAbort)
{
    if (cavemen_flutterwave_uses_v4() || cavemen_flutterwave_secret_key() !== '') {
        try {
            $paymentUrl = cavemen_flutterwave_init_payment(
                $txRef,
                $amountNaira,
                $customer,
                $thankYou,
                $registrationId,
                $attendanceType,
                $flow
            );
            return ['paymentUrl' => $paymentUrl, 'flow' => 'flutterwave_api'];
        } catch (Throwable $e) {
            error_log('[cavemen] Flutterwave init failed: ' . $e->getMessage());
            $onAbort();
            return [
                'status' => 502,
                'error' => 'Payment could not be started. Please try again in a moment or contact info@cavemen.africa.',
            ];
        }
    }

    if (cavemen_flutterwave_public_key() !== '') {
        return ['paymentUrl' => cavemen_hosted_pay_url($txRef), 'flow' => 'hosted_pay'];
    }

    $link = is_string($staticUrl) ? trim($staticUrl) : '';
    if ($link !== '') {
        return [
            'paymentUrl' => cavemen_with_payment_ref($link, $txRef, $customer['email']),
            'flow' => 'payment_link',
        ];
    }

    $onAbort();
    return [
        'status' => 503,
        'error' => 'Flutterwave is not configured. Add FLUTTERWAVE_CLIENT_ID, FLUTTERWAVE_CLIENT_SECRET, and FLUTTERWAVE_ENCRYPTION_KEY to site/.env.',
    ];
}

/**
 * @param array<string,mixed> $data
 * @return array{paymentUrl?:string,flow?:string,error?:string,status?:int}
 */
function cavemen_start_kanti_payment(PDO $pdo, $orderId, $txRef, array $data, $thankYou)
{
    $links = cavemen_get_kanti_payment_links();
    $t = $data['productType'];
    $static = isset($links[$t]) ? trim((string) $links[$t]) : '';
    return cavemen_begin_flutterwave_payment(
        $txRef,
        $data['totalNaira'],
        [
            'email' => $data['email'],
            'phone' => $data['phone'],
            'name' => $data['fullName'],
        ],
        $thankYou,
        $orderId,
        $data['productType'],
        [
            'eventTitle' => 'Kanti · ' . $data['productTitle'],
            'metaEvent' => 'kanti',
            'description' => 'Kanti order — Cavemen Africa',
        ],
        $static,
        function () use ($pdo, $orderId) {
            $del = $pdo->prepare('DELETE FROM kanti_orders WHERE id = ?');
            $del->execute([$orderId]);
        }
    );
}

/**
 * @return ?array<string,mixed>
 */
function cavemen_hosted_pay_context(PDO $pdo, $txRef)
{
    $kind = cavemen_checkout_kind($txRef);
    $base = cavemen_public_base_url();
    if ($kind === 'kanti') {
        $order = cavemen_fetch_kanti_by_txref($pdo, $txRef);
        if (!$order) {
            return null;
        }
        return [
            'id' => $order['id'],
            'fullName' => $order['fullName'],
            'email' => $order['email'],
            'phone' => $order['phone'],
            'amount' => $order['totalNaira'],
            'paid' => $order['paymentStatus'] === 'paid',
            'event' => 'kanti',
            'title' => 'Kanti · ' . $order['productTitle'],
            'description' => 'Kanti order — Cavemen Africa',
            'thankYou' => $base . '/kanti/purchase/thank-you/?tx_ref=' . rawurlencode($txRef),
        ];
    }
    if ($kind === 'tatsuniya') {
        $reg = cavemen_fetch_tatsuniya_by_txref($pdo, $txRef);
        if (!$reg) {
            return null;
        }
        return [
            'id' => $reg['id'],
            'fullName' => $reg['fullName'],
            'email' => $reg['email'],
            'phone' => $reg['phone'],
            'amount' => $reg['ticketPriceNaira'],
            'paid' => $reg['paymentStatus'] === 'paid',
            'event' => 'tatsuniya',
            'title' => cavemen_tatsuniya_event_name(),
            'description' => 'Tatsuniya workshop fee — Cavemen Africa',
            'thankYou' => $base . '/tatsuniya/register/thank-you/?tx_ref=' . rawurlencode($txRef),
        ];
    }
    $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    if (!$reg) {
        return null;
    }
    $isDahk = $kind === 'dahk';
    return [
        'id' => $reg['id'],
        'fullName' => $reg['fullName'],
        'email' => $reg['email'],
        'phone' => $reg['phone'],
        'amount' => $reg['ticketPriceNaira'],
        'paid' => $reg['paymentStatus'] === 'paid',
        'event' => $isDahk ? 'dahk' : 'asali',
        'title' => $isDahk ? cavemen_dahk_event_name() : cavemen_event_name(),
        'description' => 'Payment — Cavemen Africa',
        'thankYou' => $base . ($isDahk ? '/dahk-seasons/register/thank-you/' : '/asali/register/thank-you/')
            . '?tx_ref=' . rawurlencode($txRef),
    ];
}

function cavemen_handle_hosted_pay()
{
    $txRef = cavemen_read_tx_ref_query();
    if ($txRef === '') {
        cavemen_json_response(400, ['error' => 'A payment reference is required.']);
        return;
    }
    $public = cavemen_flutterwave_public_key();
    if ($public === '') {
        cavemen_json_response(503, [
            'error' => 'FLUTTERWAVE_PUBLIC_KEY is not set. Add the public, secret, and encryption keys to site/.env.',
        ]);
        return;
    }
    try {
        $pdo = cavemen_pdo();
        $ctx = cavemen_hosted_pay_context($pdo, $txRef);
    } catch (Throwable $e) {
        error_log('[cavemen] hosted-pay database error: ' . $e->getMessage());
        cavemen_json_response(503, [
            'error' => 'The order database is unreachable. Check MYSQL_* in site/.env.',
        ]);
        return;
    }
    if (!$ctx) {
        cavemen_json_response(404, ['error' => 'Order not found.']);
        return;
    }
    if ($ctx['paid']) {
        header('Location: ' . $ctx['thankYou'], true, 302);
        exit;
    }
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };
    $logo = cavemen_cloudinary_asset_url('assets/cavemen-icon.svg');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pay · '
        . $e($ctx['title']) . '</title></head><body style="font-family:Georgia,serif;background:#f6f1e8;color:#1c1915;padding:3rem 1.5rem;text-align:center;">'
        . '<p>Opening Flutterwave checkout for ' . $e($ctx['title']) . ' — ' . $e(cavemen_format_ngn($ctx['amount'])) . '</p>'
        . '<form id="cavemen-fw-pay" method="POST" action="https://checkout.flutterwave.com/v3/hosted/pay">'
        . '<input type="hidden" name="public_key" value="' . $e($public) . '">'
        . '<input type="hidden" name="tx_ref" value="' . $e($txRef) . '">'
        . '<input type="hidden" name="amount" value="' . $e((string) (int) $ctx['amount']) . '">'
        . '<input type="hidden" name="currency" value="NGN">'
        . '<input type="hidden" name="redirect_url" value="' . $e($ctx['thankYou']) . '">'
        . '<input type="hidden" name="customer[email]" value="' . $e($ctx['email']) . '">'
        . '<input type="hidden" name="customer[name]" value="' . $e($ctx['fullName']) . '">'
        . '<input type="hidden" name="customer[phonenumber]" value="' . $e(cavemen_fw_normalize_phone($ctx['phone'])) . '">'
        . '<input type="hidden" name="customizations[title]" value="' . $e($ctx['title']) . '">'
        . '<input type="hidden" name="customizations[description]" value="' . $e($ctx['description']) . '">'
        . '<input type="hidden" name="customizations[logo]" value="' . $e($logo) . '">'
        . '<input type="hidden" name="meta[cavemen_event]" value="' . $e($ctx['event']) . '">'
        . '<input type="hidden" name="meta[registration_id]" value="' . $e((string) $ctx['id']) . '">'
        . '<button type="submit" style="margin-top:1.25rem;padding:0.85rem 1.4rem;border:0;border-radius:999px;background:#c45c3e;color:#fff;font-weight:700;cursor:pointer;">Continue to Flutterwave</button>'
        . '</form><script>document.getElementById("cavemen-fw-pay").submit();</script></body></html>';
    exit;
}

function cavemen_output_registration_ticket_pdf()
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
    try {
        $reg = cavemen_fetch_registration_by_txref($pdo, $txRef);
    } catch (InvalidArgumentException $e) {
        cavemen_json_response(404, [
            'error' => 'Ticket not available yet. If you just paid, wait a few seconds and try again, or use the link in your email.',
        ]);
        return;
    }
    if (!$reg || $reg['paymentStatus'] !== 'paid' || empty($reg['ticketCode'])) {
        cavemen_json_response(404, [
            'error' => 'Ticket not available yet. If you just paid, wait a few seconds and try again, or use the link in your email.',
        ]);
        return;
    }
    $table = cavemen_registration_table_for_txref($txRef);
    $isDahk = $table === 'dahk_seasons_registrations';
    $built = cavemen_build_ticket_pdf_attachment($reg, $txRef, $isDahk);
    if ($built === null) {
        cavemen_json_response(500, [
            'error' => 'PDF is not set up. Run: composer install in the site/ folder (requires dompdf).',
        ]);
        return;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $built['filename'] . '"');
    header('Cache-Control: no-store');
    echo $built['content'];
    exit;
}

function cavemen_output_asali_ticket_pdf()
{
    cavemen_output_registration_ticket_pdf();
}

function cavemen_output_dahk_ticket_pdf()
{
    cavemen_output_registration_ticket_pdf();
}
