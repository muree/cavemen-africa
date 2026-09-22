<?php

/**
 * Generate ticket PDFs for a roster of registrations that did not come through
 * the Flutterwave flow (door sales, comps, imported lists).
 *
 * Usage (from site/):
 *   php bin/generate-tickets.php roster.csv [--out=DIR] [--event=asali|dahk] [--record] [--send]
 *
 * CSV needs a header row. Recognised columns (case/spacing insensitive):
 *   full name | first name + last name, ticket tier | attendance type, email,
 *   phone (optional), amount (optional, defaults to the tier price)
 *
 * --record   also insert each row into the registrations table as paid, so the
 *            QR resolves at the gate and the ticket stays re-downloadable.
 * --send     also email the gate pass (needs RESEND_API_KEY or SMTP_*).
 * --no-cards skip the shareable social PNGs written next to the tickets.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/common.php';

$args = array_slice($argv, 1);
$csvPath = null;
$outDir = null;
$event = 'asali';
$record = false;
$send = false;
$cards = true;

foreach ($args as $arg) {
    if (strpos($arg, '--out=') === 0) {
        $outDir = substr($arg, 6);
    } elseif (strpos($arg, '--event=') === 0) {
        $event = strtolower(substr($arg, 8));
    } elseif ($arg === '--record') {
        $record = true;
    } elseif ($arg === '--send') {
        $send = true;
    } elseif ($arg === '--no-cards') {
        $cards = false;
    } elseif (strpos($arg, '--') !== 0 && $csvPath === null) {
        $csvPath = $arg;
    }
}

if ($csvPath === null || !is_readable($csvPath)) {
    fwrite(STDERR, "Usage: php bin/generate-tickets.php roster.csv [--out=DIR] [--event=asali|dahk] [--record] [--send]\n");
    exit(1);
}

$isDahk = $event === 'dahk';
$outDir = $outDir !== null ? rtrim($outDir, '/') : dirname($csvPath) . '/pdf';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create output directory: {$outDir}\n");
    exit(1);
}

$rows = tickets_read_csv($csvPath);
if ($rows === []) {
    fwrite(STDERR, "No data rows found in {$csvPath}\n");
    exit(1);
}

$types = $isDahk ? cavemen_dahk_ticket_types() : cavemen_ticket_types();
$pdo = null;
if ($record) {
    $pdo = cavemen_pdo();
    cavemen_init_db($pdo);
}

$manifest = [['name', 'tier', 'email', 'tx_ref', 'ticket_code', 'pdf', 'recorded', 'emailed']];
$made = 0;
$failed = 0;

foreach ($rows as $i => $row) {
    $seq = $i + 1;
    $name = tickets_person_name($row);
    $email = trim((string) tickets_field($row, ['email', 'e-mail', 'mail']));
    $tier = tickets_match_tier(tickets_field($row, ['ticket tier', 'tier', 'attendance type', 'ticket type', 'role']), $types);

    if ($name === '' || $tier === null) {
        fwrite(STDERR, "Row {$seq}: skipped (missing name or unrecognised ticket tier)\n");
        $failed++;
        continue;
    }

    $amount = (int) tickets_field($row, ['amount', 'amount paid', 'price']);
    if ($amount <= 0) {
        $amount = (int) $types[$tier]['ticketPriceNaira'];
    }
    $phone = trim((string) tickets_field($row, ['phone', 'phone number', 'mobile']));

    $txRef = ($isDahk ? 'DAHK-M' : 'ASALI-M') . $seq . '-' . (int) (microtime(true) * 1000);
    $ticketCode = ($isDahk ? 'DAHKS-M' : 'ASALI9-M') . $seq . '-' . strtoupper(bin2hex(random_bytes(4)));

    $reg = [
        'fullName' => $name,
        'phone' => $phone,
        'email' => $email,
        'gender' => tickets_field($row, ['gender']) ?: 'Not provided',
        'discovery' => tickets_field($row, ['discovery', 'how did you hear']) ?: 'Imported roster',
        'attendanceType' => $tier,
        'ticketPriceNaira' => $amount,
        'notes' => null,
        'paymentStatus' => 'paid',
        'txRef' => $txRef,
        'ticketCode' => $ticketCode,
    ];

    $recorded = 'no';
    if ($record && $pdo !== null) {
        $recorded = tickets_record($pdo, $reg, $isDahk) ? 'yes' : 'failed';
    }

    $built = cavemen_build_ticket_pdf_attachment($reg, $txRef, $isDahk);
    if ($built === null) {
        fwrite(STDERR, "Row {$seq}: PDF build failed (run composer install in site/)\n");
        $failed++;
        continue;
    }
    $stem = tickets_slug($name) . '-' . strtolower(str_replace(' ', '-', $tier));
    $file = $outDir . '/' . $stem . '.pdf';
    file_put_contents($file, $built['content']);

    if ($cards) {
        $cardDir = $outDir . '/share';
        if (!is_dir($cardDir)) {
            mkdir($cardDir, 0775, true);
        }
        $ctx = [
            'name' => $name,
            'attendanceType' => $tier,
            'isDahk' => $isDahk,
            'eventName' => $isDahk ? cavemen_dahk_event_name() : cavemen_event_name(),
            'seriesLabel' => $isDahk ? 'DAHK · The Experience' : 'Asali · Poetry Sessions',
            'when' => cavemen_event_when($isDahk),
            'venue' => cavemen_share_venue_line(cavemen_event_venue($isDahk)),
        ];
        foreach (['square', 'story'] as $shape) {
            $png = CavemenShareCard::pngBytes($ctx, $shape);
            if ($png !== null) {
                file_put_contents($cardDir . '/' . $stem . '-' . $shape . '.png', $png);
            }
        }
    }

    $emailed = 'no';
    if ($send) {
        if ($email === '') {
            $emailed = 'no email address';
        } else {
            $emailed = cavemen_send_ticket_email_php($reg, null, $txRef, $isDahk) ? 'yes' : 'failed';
        }
    }

    $manifest[] = [$name, $tier, $email, $txRef, $ticketCode, basename($file), $recorded, $emailed];
    $made++;
    printf("%2d. %-34s %-10s %s\n", $seq, $name, $tier, basename($file));
}

$manifestPath = $outDir . '/manifest.csv';
$fh = fopen($manifestPath, 'w');
foreach ($manifest as $line) {
    fputcsv($fh, $line);
}
fclose($fh);

echo "\n{$made} ticket(s) written to {$outDir}\n";
if ($cards) {
    echo "Share cards: {$outDir}/share\n";
    file_put_contents(
        $outDir . '/share/suggested-caption.txt',
        AsaliEmailPhp::shareCaption($isDahk ? cavemen_dahk_event_name() : cavemen_event_name(), cavemen_event_when($isDahk)) . "\n"
    );
}
echo "Manifest (gate list): {$manifestPath}\n";
if ($failed > 0) {
    echo "{$failed} row(s) skipped — see messages above.\n";
}

/**
 * @return array<int,array<string,string>>
 */
function tickets_read_csv($path)
{
    $fh = fopen($path, 'r');
    if ($fh === false) {
        return [];
    }
    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        return [];
    }
    $keys = array_map(static function ($h) {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $h)));
    }, $header);

    $rows = [];
    while (($line = fgetcsv($fh)) !== false) {
        if (count(array_filter($line, static function ($v) {
            return trim((string) $v) !== '';
        })) === 0) {
            continue;
        }
        $row = [];
        foreach ($keys as $idx => $key) {
            $row[$key] = isset($line[$idx]) ? trim((string) $line[$idx]) : '';
        }
        $rows[] = $row;
    }
    fclose($fh);

    return $rows;
}

/**
 * @param array<string,string> $row
 * @param string[] $names
 */
function tickets_field(array $row, array $names)
{
    foreach ($names as $name) {
        if (isset($row[$name]) && $row[$name] !== '') {
            return $row[$name];
        }
    }

    return '';
}

/**
 * @param array<string,string> $row
 */
function tickets_person_name(array $row)
{
    $full = tickets_field($row, ['full name', 'name', 'attendee']);
    if ($full === '') {
        $full = trim(tickets_field($row, ['first name', 'firstname']) . ' ' . tickets_field($row, ['last name', 'lastname', 'surname']));
    }

    return tickets_title_case(preg_replace('/\s+/', ' ', trim($full)));
}

/**
 * Rosters are often typed in caps; title-case those, leave deliberate casing alone.
 */
function tickets_title_case($name)
{
    if ($name === '' || $name !== mb_strtoupper($name, 'UTF-8')) {
        return $name;
    }

    return preg_replace_callback('/\p{L}[\p{L}\'’-]*/u', static function ($m) {
        return mb_strtoupper(mb_substr($m[0], 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($m[0], 1, null, 'UTF-8'), 'UTF-8');
    }, $name);
}

/**
 * Tolerates the spelling slips that show up in hand-typed rosters.
 *
 * @param array<string,array<string,int>> $types
 */
function tickets_match_tier($value, array $types)
{
    $v = strtolower(trim((string) $value));
    if ($v === '') {
        return null;
    }
    foreach (array_keys($types) as $type) {
        if ($v === strtolower($type)) {
            return $type;
        }
    }
    $aliases = [
        'Performer' => ['performer', 'perfomer', 'performa', 'poet', 'artist', 'stage'],
        'Audience' => ['audience', 'audiemce', 'audiance', 'audeince', 'guest', 'attendee', 'seat'],
        'DAHK Package' => ['dahk', 'dahk package', 'package'],
    ];
    foreach ($aliases as $type => $list) {
        if (isset($types[$type]) && in_array($v, $list, true)) {
            return $type;
        }
    }
    foreach (array_keys($types) as $type) {
        if (levenshtein($v, strtolower($type)) <= 2) {
            return $type;
        }
    }

    return null;
}

/**
 * @param array<string,mixed> $reg
 */
function tickets_record(PDO $pdo, array $reg, $isDahk)
{
    $table = $isDahk ? 'dahk_seasons_registrations' : 'asali_registrations';
    try {
        $exists = $pdo->prepare("SELECT id FROM {$table} WHERE tx_ref = ?");
        $exists->execute([$reg['txRef']]);
        if ($exists->fetchColumn()) {
            return true;
        }
        $ins = $pdo->prepare("INSERT INTO {$table} (
      full_name, phone, email, gender, discovery, attendance_type, ticket_price_naira,
      notes, payment_status, tx_ref, ticket_code
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)");

        return $ins->execute([
            $reg['fullName'],
            $reg['phone'],
            $reg['email'],
            $reg['gender'],
            $reg['discovery'],
            $reg['attendanceType'],
            $reg['ticketPriceNaira'],
            $reg['notes'],
            $reg['txRef'],
            $reg['ticketCode'],
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, '[record] ' . $e->getMessage() . "\n");

        return false;
    }
}

function tickets_slug($value)
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $value));

    return trim($slug, '-') ?: 'ticket';
}
