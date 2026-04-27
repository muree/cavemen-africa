<?php

/**
 * HTML/plain ticket email (mirrors site/lib/asali-email.js for PHP / PHPMailer).
 */
class AsaliEmailPhp
{
    public static function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function buildTicketEmailHtml($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName)
    {
        $safeName = self::escapeHtml($recipientName);
        $safeCode = self::escapeHtml($ticketCode);
        $safeType = self::escapeHtml($attendanceType);
        $safeEvent = self::escapeHtml($eventName);
        $safeAmt = self::escapeHtml((string) $amountNaira);
        $sans = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"color-scheme\" content=\"light\">
  <title>Your ticket</title>
</head>
<body style=\"margin:0;padding:0;font-family:Georgia,'Times New Roman',serif;background:#e5dcd0;color:#1c1915;\">
  <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:linear-gradient(180deg,#e8e0d4 0%,#d9cfc0 100%);padding:28px 14px 36px;\">
    <tr>
      <td align=\"center\">
        <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:520px;border-radius:20px;overflow:hidden;border:1px solid #c4b5a0;box-shadow:0 20px 50px rgba(28,25,21,0.12);\">
          <tr>
            <td bgcolor=\"#1e3d2f\" style=\"background:linear-gradient(150deg,#152a1f 0%,#1e3d2f 46%,#6b2f24 100%);padding:0;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td style=\"height:4px;font-size:0;line-height:0;background:linear-gradient(90deg,transparent 0%,#c45c3e 35%,#e8a090 50%,#c45c3e 65%,transparent 100%);\">&nbsp;</td>
                </tr>
                <tr>
                  <td style=\"padding:26px 26px 22px 26px;\">
                    <p style=\"margin:0 0 8px;font-size:11px;letter-spacing:0.2em;text-transform:uppercase;color:#f6f1e8;opacity:0.88;font-family:{$sans};font-weight:600;\">Cavemen Africa</p>
                    <p style=\"margin:0 0 2px;font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:#e8a090;font-weight:600;font-family:{$sans};\">Studio of Studios &middot; Kano</p>
                    <h1 style=\"margin:12px 0 10px;font-size:25px;line-height:1.2;font-weight:700;color:#fefdfb;letter-spacing:-0.02em;\">{$safeEvent}</h1>
                    <p style=\"margin:0;font-size:14px;line-height:1.45;color:rgba(246,241,232,0.9);font-family:{$sans};font-style:italic;\">Where raw voices rise&mdash;a creative space in Northern Nigeria.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style=\"background:#fefdfb;padding:24px 26px 6px 26px;\">
              <p style=\"margin:0 0 10px;font-size:16px;font-weight:600;color:#1c1915;font-family:{$sans};\">Hi {$safeName},</p>
              <p style=\"margin:0;font-size:15px;line-height:1.6;color:#4a443a;font-family:{$sans};\">
                Your payment is in&mdash;you're on the list. The pass below is your code at the door. Keep this email; creative spaces work best when we show up for each other.
              </p>
            </td>
          </tr>
          <tr>
            <td style=\"background:#fefdfb;padding:6px 22px 26px 22px;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;border-radius:14px;overflow:hidden;border:1px solid #d4c9b5;\">
                <tr>
                  <td width=\"10\" style=\"width:10px;background:#c45c3e;\">&nbsp;</td>
                  <td style=\"background:linear-gradient(165deg,#f6f1e8 0%,#ebe3d2 100%);padding:22px 20px 24px 20px;\">
                    <p style=\"margin:0 0 10px;font-size:10px;letter-spacing:0.22em;text-transform:uppercase;color:#9e4328;font-weight:800;font-family:{$sans};\">Entry pass</p>
                    <p style=\"margin:0 0 6px;font-size:12px;color:#4a443a;font-family:{$sans};\">Code</p>
                    <p style=\"margin:0 0 18px;font-size:28px;font-weight:700;letter-spacing:0.1em;color:#1e3d2f;font-family:ui-monospace,Menlo,Consolas,monospace;line-height:1.1;\">{$safeCode}</p>
                    <table cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;\">
                      <tr>
                        <td style=\"background:#1e3d2f;color:#f6f1e8;padding:9px 18px;border-radius:999px;font-size:13px;font-weight:700;font-family:{$sans};\">{$safeType} <span style=\"opacity:0.75;\">&middot;</span> N{$safeAmt}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              <p style=\"margin:20px 0 0;font-size:12px;line-height:1.55;color:#6b6358;text-align:center;font-family:{$sans};\">
                No 2 Guda Abdullahi Road, Farm Center, Kano &middot; <strong style=\"color:#4a443a;\">Cavemen Africa</strong> &middot; Consortium &amp; Asali home
              </p>
            </td>
          </tr>
        </table>
        <p style=\"margin:16px 0 0;font-size:11px;color:#7a6f62;font-family:{$sans};text-align:center;max-width:480px;\">Payment confirmed. Questions? <span style=\"color:#9e4328;\">info@cavemen.africa</span></p>
      </td>
    </tr>
  </table>
</body>
</html>";
    }

    public static function buildTicketEmailText($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName)
    {
        $lines = [
            "Hi {$recipientName},",
            '',
            "Cavemen Africa | {$eventName}",
            'Studio of Studios · Kano, Northern Nigeria',
            '',
            "Your entry pass: {$ticketCode}",
            "{$attendanceType} · N{$amountNaira} (paid)",
            '',
            'No 2 Guda Abdullahi Road, Farm Center, Kano',
            'info@cavemen.africa',
        ];
        return implode("\n", $lines);
    }

    /**
     * @return bool true if sent
     */
    public static function sendWithPhpMailer($to, $subject, $html, $text)
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
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('[cavemen] PHPMailer: ' . $e->getMessage());
            return false;
        }
    }
}
