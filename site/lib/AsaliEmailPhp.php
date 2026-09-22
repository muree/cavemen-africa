<?php

require_once __DIR__ . '/CavemenTicketTier.php';

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

        $tier = is_array($details['tier'] ?? null)
            ? $details['tier']
            : CavemenTicketTier::forType($attendanceType, !empty($details['isDahk']));
        $tierHeader = (string) $tier['emailHeader'];
        $tierAccent = (string) $tier['emailAccent'];
        $tierBadgeInk = (string) $tier['emailBadgeInk'];
        $tierBadge = self::escapeHtml(strtoupper((string) $tier['badge']));
        $tierMark = self::escapeHtml((string) $tier['mark']);
        $tierSummary = self::escapeHtml((string) $tier['summary']);

        $summaryBlock = $tierSummary === ''
            ? ''
            : "<p style=\"margin:12px 0 0;font-size:15px;line-height:1.6;color:{$tierAccent};font-family:{$sans};font-weight:600;\">{$tierMark} {$tierSummary}</p>";

        $tierNotes = '';
        foreach ($tier['lines'] as $line) {
            $safeLine = self::escapeHtml((string) $line);
            $tierNotes .= "<p style=\"margin:0 0 6px;font-size:13px;line-height:1.55;color:#d4c9b5;font-family:{$sans};\">{$tierMark} {$safeLine}</p>";
        }
        $shareCid = (string) ($details['shareCid'] ?? 'cavemen-share-card');
        $shareBlock = '';
        if (!empty($details['hasShare'])) {
            $caption = self::escapeHtml(self::shareCaption($eventName, (string) ($details['when'] ?? '')));
            $shareBlock = "<tr>
            <td style=\"padding:8px 22px 20px 22px;background:#1a1815;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;border-radius:14px;overflow:hidden;border:1px solid #3a332c;background:#221f1b;\">
                <tr>
                  <td style=\"padding:18px 18px 8px;\">
                    <p style=\"margin:0 0 4px;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:{$tierAccent};font-family:{$sans};font-weight:800;\">Tell the room you’re coming</p>
                    <p style=\"margin:0 0 14px;font-size:13px;line-height:1.55;color:#d4c9b5;font-family:{$sans};\">Post the card below to your feed or story and tag <strong style=\"color:#f6f1e8;\">@cavemenafrica</strong>. It is attached to this email in full size, along with a story-sized version.</p>
                  </td>
                </tr>
                <tr>
                  <td align=\"center\" style=\"padding:0 18px;\">
                    <img src=\"cid:{$shareCid}\" alt=\"Share card\" width=\"420\" style=\"display:block;width:100%;max-width:420px;height:auto;border:0;border-radius:10px;\">
                  </td>
                </tr>
                <tr>
                  <td style=\"padding:14px 18px 18px;\">
                    <p style=\"margin:0;font-size:12px;line-height:1.6;color:#9a9084;font-family:{$sans};font-style:italic;\">Caption idea: {$caption}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>";
        }

        $notesBlock = $tierNotes === ''
            ? ''
            : "<tr>
            <td style=\"padding:0 22px 8px 22px;background:#1a1815;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;border-radius:14px;border-left:4px solid {$tierAccent};background:#221f1b;\">
                <tr>
                  <td style=\"padding:16px 18px;\">
                    <p style=\"margin:0 0 8px;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:{$tierAccent};font-family:{$sans};font-weight:800;\">{$tierBadge}</p>
                    {$tierNotes}
                  </td>
                </tr>
              </table>
            </td>
          </tr>";

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
            <td style=\"background:{$tierHeader};padding:0;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td style=\"height:6px;font-size:0;line-height:0;background:{$tierAccent};\">&nbsp;</td>
                </tr>
                <tr>
                  <td style=\"padding:22px 26px 20px 26px;\">
                    <p style=\"margin:0 0 6px;font-size:11px;letter-spacing:0.22em;text-transform:uppercase;color:#f6f1e8;opacity:0.85;font-family:{$sans};font-weight:700;\">Cavemen Africa</p>
                    <p style=\"margin:0 0 10px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:{$tierAccent};font-weight:600;font-family:{$sans};\">Studio of Studios &middot; Kano</p>
                    <h1 style=\"margin:0 0 12px;font-size:24px;line-height:1.25;font-weight:700;color:#fefdfb;font-family:Georgia,'Times New Roman',serif;\">{$safeEvent}</h1>
                    <table cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"border-collapse:separate;\">
                      <tr>
                        <td style=\"background:{$tierAccent};border-radius:999px;padding:7px 14px;font-size:12px;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;color:{$tierBadgeInk};font-family:{$sans};\">{$tierMark} {$tierBadge}</td>
                      </tr>
                    </table>
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
              {$summaryBlock}
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
                        <td style=\"padding:8px 0;font-size:15px;color:{$tierAccent};text-align:right;font-family:{$sans};font-weight:700;\">{$tierMark} {$safeType}</td>
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
          {$notesBlock}
          {$shareBlock}
          <tr>
            <td style=\"padding:8px 22px 26px 22px;background:#1a1815;\" align=\"center\">
              <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse:separate;border-radius:16px;background:#f6f1e8;padding:18px;\">
                <tr>
                  <td align=\"center\" style=\"padding:0;\">
                    <p style=\"margin:0 0 4px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#1e3d2f;font-family:{$sans};font-weight:800;\">Scan at the gate</p>
                    <p style=\"margin:0 0 10px;font-size:13px;letter-spacing:0.1em;text-transform:uppercase;color:{$tierHeader};font-family:{$sans};font-weight:800;\">{$tierMark} {$safeType}</p>
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

    public static function shareCaption($eventName, $when)
    {
        $dayPart = $when !== '' ? ' — ' . $when : '';

        return $eventName . $dayPart . '. Come sit with us. @cavemenafrica #AsaliPoetrySessions #CavemenAfrica #Kano';
    }

    public static function buildTicketEmailText($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName, array $details = [])
    {
        $amountLabel = isset($details['amountLabel']) && (string) $details['amountLabel'] !== ''
            ? (string) $details['amountLabel']
            : '₦' . number_format((int) $amountNaira, 0, '.', ',');
        $venue = (string) ($details['venue'] ?? 'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria');
        $when = (string) ($details['when'] ?? '');
        $txRef = (string) ($details['txRef'] ?? $ticketCode);
        $tier = is_array($details['tier'] ?? null)
            ? $details['tier']
            : CavemenTicketTier::forType($attendanceType, !empty($details['isDahk']));
        $lines = [
            "Hi {$recipientName},",
            '',
            "Cavemen Africa | {$eventName}",
            $when !== '' ? $when : null,
            $venue,
            '',
            strtoupper((string) $tier['badge']),
            (string) $tier['summary'] !== '' ? (string) $tier['summary'] : null,
            '',
            "Amount paid: {$amountLabel}",
            "Ticket: {$attendanceType}",
            "Payment reference: {$txRef}",
            "Door code: {$ticketCode}",
        ];
        foreach ($tier['lines'] as $line) {
            $lines[] = '- ' . $line;
        }
        $lines = array_merge($lines, [
            '',
            'Show the QR code in this email (or the attached PDF) at the gate.',
        ]);
        if (!empty($details['hasShare'])) {
            $lines = array_merge($lines, [
                '',
                'Share card attached — post it and tag @cavemenafrica.',
                'Caption idea: ' . self::shareCaption($eventName, $when),
            ]);
        }
        $lines = array_merge($lines, [
            '',
            'info@cavemen.africa',
        ]);

        return implode("\n", array_values(array_filter($lines, static function ($line) {
            return $line !== null;
        })));
    }

    /**
     * Resend hosted template (alias cavemen-ticket). Variables use {{{KEY}}}.
     */
    public static function ticketTemplateAlias()
    {
        $alias = getenv('RESEND_TICKET_TEMPLATE') ?: 'cavemen-ticket';

        return $alias !== '' ? $alias : 'cavemen-ticket';
    }

    /**
     * @return array<int,array{key:string,type:string,fallback_value:string}>
     */
    public static function ticketTemplateVariableDefs()
    {
        $keys = [
            'GUEST_NAME', 'EVENT_NAME', 'TIER_LABEL', 'TIER_BADGE', 'TIER_MARK',
            'TIER_SUMMARY', 'TIER_NOTE_1', 'TIER_NOTE_2', 'HEADER_BG', 'ACCENT',
            'BADGE_INK', 'WHEN_LABEL', 'VENUE', 'AMOUNT', 'TX_REF', 'DOOR_CODE',
            'SHARE_CAPTION',
        ];
        $defs = [];
        foreach ($keys as $key) {
            $defs[] = ['key' => $key, 'type' => 'string', 'fallback_value' => ' '];
        }

        return $defs;
    }

    /**
     * @param array<string,mixed> $details
     * @return array<string,string>
     */
    public static function ticketTemplateValues($recipientName, $ticketCode, $attendanceType, $amountNaira, $eventName, array $details = [])
    {
        $tier = is_array($details['tier'] ?? null)
            ? $details['tier']
            : CavemenTicketTier::forType($attendanceType, !empty($details['isDahk']));
        $amountLabel = isset($details['amountLabel']) && (string) $details['amountLabel'] !== ''
            ? (string) $details['amountLabel']
            : '₦' . number_format((int) $amountNaira, 0, '.', ',');
        $when = trim((string) ($details['when'] ?? ''));
        $lines = is_array($tier['lines'] ?? null) ? $tier['lines'] : [];

        return [
            'GUEST_NAME' => (string) $recipientName,
            'EVENT_NAME' => (string) $eventName,
            'TIER_LABEL' => (string) ($tier['label'] ?? $attendanceType),
            'TIER_BADGE' => strtoupper((string) ($tier['badge'] ?? $attendanceType)),
            'TIER_MARK' => (string) ($tier['mark'] ?? ''),
            'TIER_SUMMARY' => (string) ($tier['summary'] ?? ''),
            'TIER_NOTE_1' => (string) ($lines[0] ?? ''),
            'TIER_NOTE_2' => (string) ($lines[1] ?? ''),
            'HEADER_BG' => (string) ($tier['emailHeader'] ?? '#1e3d2f'),
            'ACCENT' => (string) ($tier['emailAccent'] ?? '#e8a090'),
            'BADGE_INK' => (string) ($tier['emailBadgeInk'] ?? '#221007'),
            'WHEN_LABEL' => $when !== '' ? $when : 'See event listing',
            'VENUE' => (string) ($details['venue'] ?? 'No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria'),
            'AMOUNT' => $amountLabel,
            'TX_REF' => (string) ($details['txRef'] ?? $ticketCode),
            'DOOR_CODE' => (string) $ticketCode,
            'SHARE_CAPTION' => self::shareCaption($eventName, $when),
        ];
    }

    public static function ticketTemplateHtml()
    {
        $sans = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your ticket</title>
</head>
<body style="margin:0;padding:0;background:#12100e;color:#f6f1e8;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#12100e;padding:24px 12px 36px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;border-radius:20px;overflow:hidden;border:1px solid #3a332c;background:#1a1815;">
          <tr>
            <td style="background:{{{HEADER_BG}}};padding:0;">
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                  <td style="height:6px;font-size:0;line-height:0;background:{{{ACCENT}}};">&nbsp;</td>
                </tr>
                <tr>
                  <td style="padding:22px 26px 20px 26px;">
                    <p style="margin:0 0 6px;font-size:11px;letter-spacing:0.22em;text-transform:uppercase;color:#f6f1e8;font-family:' . $sans . ';font-weight:700;">Cavemen Africa</p>
                    <p style="margin:0 0 10px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:{{{ACCENT}}};font-weight:600;font-family:' . $sans . ';">Studio of Studios &middot; Kano</p>
                    <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;font-weight:700;color:#fefdfb;font-family:Georgia,\'Times New Roman\',serif;">{{{EVENT_NAME}}}</h1>
                    <p style="margin:0;display:inline-block;background:{{{ACCENT}}};border-radius:999px;padding:7px 14px;font-size:12px;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;color:{{{BADGE_INK}}};font-family:' . $sans . ';">{{{TIER_MARK}}} {{{TIER_BADGE}}}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 26px 8px 26px;background:#1a1815;">
              <p style="margin:0 0 10px;font-size:16px;font-weight:600;color:#f6f1e8;font-family:' . $sans . ';">Hi {{{GUEST_NAME}}},</p>
              <p style="margin:0;font-size:15px;line-height:1.6;color:#d4c9b5;font-family:' . $sans . ';">Your payment is confirmed. This is your gate pass. Show the QR code at the entrance, or quote your payment reference if a scanner is not available. A PDF copy is attached.</p>
              <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:{{{ACCENT}}};font-family:' . $sans . ';font-weight:600;">{{{TIER_MARK}}} {{{TIER_SUMMARY}}}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 22px 8px 22px;background:#1a1815;">
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;border-radius:14px;overflow:hidden;border:1px solid #3a332c;background:#221f1b;">
                <tr>
                  <td style="padding:18px 20px;font-family:' . $sans . ';">
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Name</p>
                    <p style="margin:0 0 14px;font-size:15px;color:#f6f1e8;font-weight:600;">{{{GUEST_NAME}}}</p>
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Date &amp; time</p>
                    <p style="margin:0 0 14px;font-size:15px;color:#f6f1e8;">{{{WHEN_LABEL}}}</p>
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Venue</p>
                    <p style="margin:0 0 14px;font-size:15px;color:#f6f1e8;">{{{VENUE}}}</p>
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Ticket</p>
                    <p style="margin:0 0 14px;font-size:15px;color:{{{ACCENT}}};font-weight:700;">{{{TIER_MARK}}} {{{TIER_LABEL}}}</p>
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Amount paid</p>
                    <p style="margin:0 0 14px;font-size:15px;color:#f6f1e8;font-weight:700;">{{{AMOUNT}}}</p>
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#c9b8a4;">Payment reference</p>
                    <p style="margin:0;font-size:13px;color:#e8a090;font-family:ui-monospace,Menlo,Consolas,monospace;">{{{TX_REF}}}</p>
                    <p style="margin:16px 0 0;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:#9e4328;font-weight:700;">Door code &middot; {{{DOOR_CODE}}}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:0 22px 8px 22px;background:#1a1815;">
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;border-radius:14px;border-left:4px solid {{{ACCENT}}};background:#221f1b;">
                <tr>
                  <td style="padding:16px 18px;font-family:' . $sans . ';">
                    <p style="margin:0 0 8px;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:{{{ACCENT}}};font-weight:800;">{{{TIER_BADGE}}}</p>
                    <p style="margin:0 0 6px;font-size:13px;line-height:1.55;color:#d4c9b5;">{{{TIER_MARK}}} {{{TIER_NOTE_1}}}</p>
                    <p style="margin:0;font-size:13px;line-height:1.55;color:#d4c9b5;">{{{TIER_MARK}}} {{{TIER_NOTE_2}}}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 22px 20px 22px;background:#1a1815;">
              <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;border-radius:14px;overflow:hidden;border:1px solid #3a332c;background:#221f1b;">
                <tr>
                  <td style="padding:18px 18px 8px;font-family:' . $sans . ';">
                    <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:{{{ACCENT}}};font-weight:800;">Tell the room you are coming</p>
                    <p style="margin:0 0 14px;font-size:13px;line-height:1.55;color:#d4c9b5;">Post the card below and tag @cavemenafrica. Full-size and story versions are attached.</p>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding:0 18px;">
                    <img src="cid:cavemen-share-card" alt="Share card" width="420" style="display:block;width:100%;max-width:420px;height:auto;border:0;border-radius:10px;">
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 18px 18px;">
                    <p style="margin:0;font-size:12px;line-height:1.6;color:#9a9084;font-family:' . $sans . ';font-style:italic;">Caption idea: {{{SHARE_CAPTION}}}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 22px 26px 22px;background:#1a1815;" align="center">
              <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-radius:16px;background:#f6f1e8;padding:18px;">
                <tr>
                  <td align="center" style="padding:0;">
                    <p style="margin:0 0 4px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#1e3d2f;font-family:' . $sans . ';font-weight:800;">Scan at the gate</p>
                    <p style="margin:0 0 10px;font-size:13px;letter-spacing:0.1em;text-transform:uppercase;color:{{{HEADER_BG}}};font-family:' . $sans . ';font-weight:800;">{{{TIER_MARK}}} {{{TIER_LABEL}}}</p>
                    <img src="cid:cavemen-gate-qr" alt="Gate pass QR code" width="280" height="280" style="display:block;width:280px;height:280px;border:0;background:#f6f1e8;">
                    <p style="margin:10px 0 0;font-size:11px;color:#4a443a;font-family:ui-monospace,Menlo,Consolas,monospace;">{{{TX_REF}}}</p>
                  </td>
                </tr>
              </table>
              <p style="margin:18px 0 0;font-size:12px;line-height:1.55;color:#9a9084;text-align:center;font-family:' . $sans . ';">{{{VENUE}}}<br>Questions? info@cavemen.africa</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    public static function ticketTemplateText()
    {
        return "Hi {{{GUEST_NAME}}},\n\nCavemen Africa | {{{EVENT_NAME}}}\n{{{WHEN_LABEL}}}\n{{{VENUE}}}\n\n{{{TIER_BADGE}}}\n{{{TIER_SUMMARY}}}\n\nAmount paid: {{{AMOUNT}}}\nTicket: {{{TIER_LABEL}}}\nPayment reference: {{{TX_REF}}}\nDoor code: {{{DOOR_CODE}}}\n\n{{{TIER_NOTE_1}}}\n{{{TIER_NOTE_2}}}\n\nShow the QR code (or the attached PDF) at the gate.\nShare card attached — post it and tag @cavemenafrica.\nCaption idea: {{{SHARE_CAPTION}}}\n\ninfo@cavemen.africa\n";
    }

    /**
     * @param array<string,string>|null $pdfAttachment keys: content, filename
     * @param array{content:string,filename?:string,cid?:string}|null $qrAttachment raw PNG bytes
     * @param array<int,array{content:string,filename:string,cid?:string}> $images extra PNGs (share cards)
     * @param array{id:string,variables:array<string,string>}|null $template Resend template send payload
     */
    public static function sendWithResend($to, $subject, $html, $text, $pdfAttachment = null, $qrAttachment = null, array $images = [], $template = null)
    {
        $apiKey = getenv('RESEND_API_KEY') ?: '';
        if ($apiKey === '') {
            return false;
        }
        $from = getenv('RESEND_FROM') ?: (getenv('SMTP_FROM') ?: 'Cavemen Africa <info@cavemen.africa>');
        $useTemplate = is_array($template)
            && (string) ($template['id'] ?? '') !== ''
            && is_array($template['variables'] ?? null);
        $payload = [
            'from' => $from,
            'to' => [(string) $to],
            'subject' => $subject,
        ];
        if ($useTemplate) {
            $payload['template'] = [
                'id' => (string) $template['id'],
                'variables' => $template['variables'],
            ];
        } else {
            $payload['html'] = $html;
            $payload['text'] = $text;
        }
        $attachments = [];
        if (is_array($qrAttachment) && isset($qrAttachment['content']) && $qrAttachment['content'] !== '') {
            $attachments[] = [
                'filename' => (string) ($qrAttachment['filename'] ?? 'gate-pass.png'),
                'content' => base64_encode((string) $qrAttachment['content']),
                'content_type' => 'image/png',
                'content_id' => (string) ($qrAttachment['cid'] ?? 'cavemen-gate-qr'),
            ];
        }
        foreach ($images as $image) {
            if (!is_array($image) || (string) ($image['content'] ?? '') === '') {
                continue;
            }
            $entry = [
                'filename' => (string) ($image['filename'] ?? 'cavemen.png'),
                'content' => base64_encode((string) $image['content']),
                'content_type' => 'image/png',
            ];
            if ((string) ($image['cid'] ?? '') !== '') {
                $entry['content_id'] = (string) $image['cid'];
            }
            $attachments[] = $entry;
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
     * @param array<int,array{content:string,filename:string,cid?:string}> $images extra PNGs (share cards)
     * @return bool true if sent
     */
    public static function sendWithPhpMailer($to, $subject, $html, $text, $pdfAttachment = null, $qrAttachment = null, array $images = [])
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
            foreach ($images as $image) {
                if (!is_array($image) || (string) ($image['content'] ?? '') === '') {
                    continue;
                }
                $filename = (string) ($image['filename'] ?? 'cavemen.png');
                if ((string) ($image['cid'] ?? '') !== '') {
                    $mail->addStringEmbeddedImage(
                        (string) $image['content'],
                        (string) $image['cid'],
                        $filename,
                        \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                        'image/png'
                    );
                } else {
                    $mail->addStringAttachment(
                        (string) $image['content'],
                        $filename,
                        \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                        'image/png'
                    );
                }
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
