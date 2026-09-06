<?php

/**
 * Ticket email HTML and delivery (Resend, then PHPMailer SMTP).
 */
class AsaliEmailPhp
{
    public static function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string,mixed> $details keys: venue, when, txRef, flierUrl, amountLabel, qrCid
     */
    public static function buildTicketEmailHtml($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName, array $details = [])
    {
        $safeName = self::escapeHtml($recipientName);
        $safeCode = self::escapeHtml($ticketCode);
        $safeType = self::escapeHtml($attendanceType);
        $safeEvent = self::escapeHtml($eventName);
        $amountLabel = isset($details['amountLabel']) && (string) $details['amountLabel'] !== ''
            ? (string) $details['amountLabel']
            : '₦' . number_format((int) $amountNaira, 0, '.', ',');
        $safeAmt = self::escapeHtml($amountLabel);
        $venue = self::escapeHtml((string) ($details['venue'] ?? 'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria'));
        $when = self::escapeHtml((string) ($details['when'] ?? ''));
        $txRef = self::escapeHtml((string) ($details['txRef'] ?? $ticketCode));
        $flierUrl = (string) ($details['flierUrl'] ?? '');
        $qrCid = (string) ($details['qrCid'] ?? 'cavemen-gate-qr');
        $hasQr = !empty($details['hasQr']);
        $sans = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

        $flierBlock = '';
        if ($flierUrl !== '' && preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $flierUrl)) {
            $safeFlier = self::escapeHtml($flierUrl);
            $flierBlock = "<tr>
              <td style=\"padding:0;line-height:0;font-size:0;background:#111;\">
                <img src=\"{$safeFlier}\" alt=\"{$safeEvent}\" width=\"600\" style=\"display:block;width:100%;max-width:600px;height:auto;border:0;\">
              </td>
            </tr>";
        }

        $whenRow = $when === ''
            ? ''
            : "<tr>
                  <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Date &amp; time</td>
                  <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;text-align:right;font-family:{$sans};\">{$when}</td>
                </tr>";

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
  <meta name=\"color-scheme\" content=\"dark light\">
  <meta name=\"supported-color-schemes\" content=\"dark light\">
  <title>Your ticket</title>
</head>
<body style=\"margin:0;padding:0;background:#12100e;color:#f6f1e8;\">
  <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#12100e;padding:24px 12px 36px;\">
    <tr>
      <td align=\"center\">
        <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;border-radius:20px;overflow:hidden;border:1px solid #3a332c;background:#1a1815;\">
          {$flierBlock}
          <tr>
            <td style=\"background:#1e3d2f;padding:0;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td style=\"height:4px;font-size:0;line-height:0;background:linear-gradient(90deg,transparent 0%,#c45c3e 35%,#e8a090 50%,#c45c3e 65%,transparent 100%);\">&nbsp;</td>
                </tr>
                <tr>
                  <td style=\"padding:22px 26px 20px 26px;\">
                    <p style=\"margin:0 0 6px;font-size:11px;letter-spacing:0.22em;text-transform:uppercase;color:#f6f1e8;opacity:0.85;font-family:{$sans};font-weight:700;\">Cavemen Africa</p>
                    <p style=\"margin:0 0 10px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#e8a090;font-weight:600;font-family:{$sans};\">Studio of Studios &middot; Kano</p>
                    <h1 style=\"margin:0;font-size:24px;line-height:1.25;font-weight:700;color:#fefdfb;font-family:Georgia,'Times New Roman',serif;\">{$safeEvent}</h1>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style=\"padding:24px 26px 8px 26px;background:#1a1815;\">
              <p style=\"margin:0 0 10px;font-size:16px;font-weight:600;color:#f6f1e8;font-family:{$sans};\">Hi {$safeName},</p>
              <p style=\"margin:0;font-size:15px;line-height:1.6;color:#d4c9b5;font-family:{$sans};\">
                Your payment is confirmed. This is your gate pass. Show the QR code at the entrance, or quote your payment reference if a scanner is not available. A PDF copy is attached.
              </p>
            </td>
          </tr>
          <tr>
            <td style=\"padding:16px 22px 8px 22px;background:#1a1815;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;border-radius:14px;overflow:hidden;border:1px solid #3a332c;background:#221f1b;\">
                <tr>
                  <td style=\"padding:18px 20px;\">
                    <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                      <tr>
                        <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Name</td>
                        <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;text-align:right;font-family:{$sans};font-weight:600;\">{$safeName}</td>
                      </tr>
                      {$whenRow}
                      <tr>
                        <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Venue</td>
                        <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;text-align:right;font-family:{$sans};\">{$venue}</td>
                      </tr>
                      <tr>
                        <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Ticket</td>
                        <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;text-align:right;font-family:{$sans};\">{$safeType}</td>
                      </tr>
                      <tr>
                        <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Amount paid</td>
                        <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;text-align:right;font-family:{$sans};font-weight:700;\">{$safeAmt}</td>
                      </tr>
                      <tr>
                        <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;font-family:{$sans};\">Payment reference</td>
                        <td style=\"padding:8px 0;font-size:13px;color:#e8a090;text-align:right;font-family:ui-monospace,Menlo,Consolas,monospace;\">{$txRef}</td>
                      </tr>
                    </table>
                    <p style=\"margin:16px 0 0;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:#9e4328;font-family:{$sans};font-weight:700;\">Door code &middot; {$safeCode}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style=\"padding:8px 22px 26px 22px;background:#1a1815;\" align=\"center\">
              <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse:separate;border-radius:16px;background:#f6f1e8;padding:18px;\">
                <tr>
                  <td align=\"center\" style=\"padding:0;\">
                    <p style=\"margin:0 0 10px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#1e3d2f;font-family:{$sans};font-weight:800;\">Scan at the gate</p>
                    " . ($hasQr
            ? "<img src=\"cid:{$qrCid}\" alt=\"Gate pass QR code\" width=\"280\" height=\"280\" style=\"display:block;width:280px;height:280px;border:0;background:#f6f1e8;\">"
            : "<p style=\"margin:0;font-size:18px;font-weight:700;letter-spacing:0.04em;color:#1e3d2f;font-family:ui-monospace,Menlo,Consolas,monospace;\">{$txRef}</p>") . "
                    <p style=\"margin:10px 0 0;font-size:11px;color:#4a443a;font-family:ui-monospace,Menlo,Consolas,monospace;\">{$txRef}</p>
                  </td>
                </tr>
              </table>
              <p style=\"margin:18px 0 0;font-size:12px;line-height:1.55;color:#9a9084;text-align:center;font-family:{$sans};\">
                {$venue}<br>
                Questions? info@cavemen.africa
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
    }

    public static function buildTicketEmailText($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName, array $details = [])
    {
        $amountLabel = isset($details['amountLabel']) && (string) $details['amountLabel'] !== ''
            ? (string) $details['amountLabel']
            : '₦' . number_format((int) $amountNaira, 0, '.', ',');
        $venue = (string) ($details['venue'] ?? 'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria');
        $when = (string) ($details['when'] ?? '');
        $txRef = (string) ($details['txRef'] ?? $ticketCode);
        $lines = [
            "Hi {$recipientName},",
            '',
            "Cavemen Africa | {$eventName}",
            $when !== '' ? $when : null,
            $venue,
            '',
            "Amount paid: {$amountLabel}",
            "Ticket: {$attendanceType}",
            "Payment reference: {$txRef}",
            "Door code: {$ticketCode}",
            '',
            'Show the QR code in this email (or the attached PDF) at the gate.',
            '',
            'info@cavemen.africa',
        ];

        return implode("\n", array_values(array_filter($lines, static function ($line) {
            return $line !== null;
        })));
    }

    /**
     * @param array<string,string>|null $pdfAttachment keys: content, filename
     * @param array{content:string,filename?:string,cid?:string}|null $qrAttachment raw PNG bytes
     */
    public static function sendWithResend($to, $subject, $html, $text, $pdfAttachment = null, $qrAttachment = null)
    {
        $apiKey = getenv('RESEND_API_KEY') ?: '';
        if ($apiKey === '') {
            return false;
        }
        $from = getenv('RESEND_FROM') ?: (getenv('SMTP_FROM') ?: 'Cavemen Africa <info@cavemen.africa>');
        $payload = [
            'from' => $from,
            'to' => [(string) $to],
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
        $attachments = [];
        if (is_array($qrAttachment) && isset($qrAttachment['content']) && $qrAttachment['content'] !== '') {
            $attachments[] = [
                'filename' => (string) ($qrAttachment['filename'] ?? 'gate-pass.png'),
                'content' => base64_encode((string) $qrAttachment['content']),
                'content_type' => 'image/png',
                'content_id' => (string) ($qrAttachment['cid'] ?? 'cavemen-gate-qr'),
            ];
        }
        if (is_array($pdfAttachment) && isset($pdfAttachment['content'], $pdfAttachment['filename'])) {
            $attachments[] = [
                'filename' => (string) $pdfAttachment['filename'],
                'content' => base64_encode((string) $pdfAttachment['content']),
                'content_type' => 'application/pdf',
            ];
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $res = cavemen_http_post_json(
            'https://api.resend.com/emails',
            ['Content-Type: application/json'],
            json_encode($payload),
            $apiKey,
            20
        );
        if (!($res['ok'] ?? false)) {
            $msg = is_array($res['data'] ?? null) && isset($res['data']['message'])
                ? $res['data']['message']
                : ($res['error'] ?? 'Resend request failed');
            $http = isset($res['http']) ? (' HTTP ' . $res['http']) : '';
            error_log('[cavemen] Resend:' . $http . ' ' . $msg);
            return false;
        }
        if (is_array($res['data'] ?? null) && isset($res['data']['id'])) {
            error_log('[cavemen] Resend sent id=' . $res['data']['id']);
        }

        return true;
    }

    /**
     * @param array<string,string>|null $pdfAttachment keys: content (raw bytes), filename
     * @param array{content:string,filename?:string,cid?:string}|null $qrAttachment
     * @return bool true if sent
     */
    public static function sendWithPhpMailer($to, $subject, $html, $text, $pdfAttachment = null, $qrAttachment = null)
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            return false;
        }
        require_once $autoload;

        $host = getenv('SMTP_HOST') ?: '';
        $user = getenv('SMTP_USER') ?: '';
        $pass = getenv('SMTP_PASS') ?: '';
        if ($host === '' || $user === '' || $pass === '') {
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            if (getenv('SMTP_SECURE') === 'true') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $from = getenv('SMTP_FROM') ?: $user;
            $mail->setFrom($from);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            if (is_array($qrAttachment) && isset($qrAttachment['content']) && $qrAttachment['content'] !== '') {
                $cid = (string) ($qrAttachment['cid'] ?? 'cavemen-gate-qr');
                $mail->addStringEmbeddedImage(
                    (string) $qrAttachment['content'],
                    $cid,
                    (string) ($qrAttachment['filename'] ?? 'gate-pass.png'),
                    \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                    'image/png'
                );
            }
            if (is_array($pdfAttachment) && isset($pdfAttachment['content'], $pdfAttachment['filename'])) {
                $fn = (string) $pdfAttachment['filename'];
                $mail->addStringAttachment(
                    (string) $pdfAttachment['content'],
                    $fn !== '' ? $fn : 'ticket.pdf',
                    \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                    'application/pdf'
                );
            }
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('[cavemen] PHPMailer: ' . $e->getMessage());
            return false;
        }
    }
}
