<?php

/**
 * HTML for PDF ticket (used with Dompdf on PHP / cPanel).
 * Horizontal stub layout inspired by classic event tickets.
 */
class AsaliTicketPdfHtml
{
    private const QR_DISPLAY_PX = 210;

    /**
     * @param array<string,mixed> $reg
     * @param array<string,mixed> $options eventWhen, seriesLabel, isDahk
     */
    public static function build(array $reg, $eventName, $venueLine, $txRef, $qrPng = null, array $options = [])
    {
        $name = self::e($reg['fullName'] ?? '');
        $type = self::e($reg['attendanceType'] ?? '');
        $amountNaira = (int) ($reg['ticketPriceNaira'] ?? 0);
        $amount = self::e('₦' . number_format($amountNaira, 0, '.', ','));
        $event = self::e($eventName);
        $venue = self::e($venueLine);
        $ref = self::e($txRef);
        $isDahk = !empty($options['isDahk']);
        $series = self::e((string) ($options['seriesLabel'] ?? ($isDahk ? 'DAHK · THE EXPERIENCE' : 'ASALI · POETRY SESSIONS')));
        $when = self::e((string) ($options['eventWhen'] ?? ''));

        $qrBlock = '';
        if (is_string($qrPng) && $qrPng !== '') {
            $size = self::QR_DISPLAY_PX;
            $qrBlock = '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center" style="padding:8px 0 12px;">'
                . '<div style="background:#ffffff;padding:10px;display:inline-block;">'
                . '<img src="data:image/png;base64,' . base64_encode($qrPng) . '" width="' . $size . '" height="' . $size . '" alt="Gate QR" />'
                . '</div></td></tr></table>';
        }

        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #1a2744;
      font-size: 9pt;
      margin: 0;
      padding: 18px;
      background: #ffffff;
    }
    .ticket-wrap {
      border: 2px solid #c9a962;
      outline: 1px solid #c9a962;
      outline-offset: 3px;
    }
    .label {
      font-size: 6.5pt;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #b8956b;
      font-weight: 700;
      margin: 0 0 4px;
    }
    .serif {
      font-family: DejaVu Serif, serif;
    }
    .mono {
      font-family: DejaVu Sans Mono, monospace;
      letter-spacing: 0.04em;
    }
    .perforation {
      width: 10px;
      background: #f4efe4;
      border-left: 2px dashed #8a96ab;
      border-right: 2px dashed #8a96ab;
    }
  </style>
</head>
<body>
  <table class="ticket-wrap" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;background:#f4efe4;">
    <tr>
      <td width="70%" style="background:#f4efe4;padding:22px 24px 20px;vertical-align:top;border-right:none;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td width="72%" style="vertical-align:top;padding-bottom:14px;">
              <p style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:#1a2744;font-weight:700;margin:0 0 2px;">Cavemen Africa</p>
              <p style="font-size:6.5pt;letter-spacing:0.12em;text-transform:uppercase;color:#6b6358;margin:0;">Studio of Studios · Kano</p>
            </td>
            <td width="28%" align="right" style="vertical-align:top;">
              <p class="label" style="margin:0 0 2px;">Admit</p>
              <p class="serif" style="font-size:28pt;line-height:1;color:#1a2744;font-weight:700;margin:0;">01</p>
            </td>
          </tr>
        </table>

        <p class="label" style="margin:0 0 6px;">' . $series . '</p>
        <h1 class="serif" style="font-size:22pt;line-height:1.15;color:#1a2744;font-weight:700;margin:0 0 16px;">' . $event . '</h1>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:18px;">
          <tr>
            <td width="38%" style="vertical-align:top;padding-right:12px;">
              <p class="label">Date</p>
              <p class="serif" style="font-size:10.5pt;line-height:1.35;color:#1a2744;margin:0;">' . ($when !== '' ? $when : 'See event listing') . '</p>
            </td>
            <td width="62%" style="vertical-align:top;">
              <p class="label">Location</p>
              <p class="serif" style="font-size:10.5pt;line-height:1.35;color:#1a2744;margin:0;">' . $venue . '</p>
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-top:1px solid #d9cdb8;padding-top:14px;">
          <tr>
            <td width="44%" style="vertical-align:top;padding-right:10px;">
              <p class="label">Attendee</p>
              <p class="serif" style="font-size:12pt;line-height:1.3;color:#1a2744;font-weight:700;margin:0;">' . $name . '</p>
            </td>
            <td width="28%" style="vertical-align:top;padding-right:10px;">
              <p class="label">Ticket</p>
              <p class="serif" style="font-size:11pt;line-height:1.3;color:#1a2744;font-weight:700;margin:0;">' . $type . '</p>
            </td>
            <td width="28%" style="vertical-align:top;">
              <p class="label">Paid</p>
              <p class="serif" style="font-size:16pt;line-height:1.1;color:#1a2744;font-weight:700;margin:0;">' . $amount . '</p>
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:16px;border-top:1px solid #d9cdb8;padding-top:10px;">
          <tr>
            <td>
              <p class="label">Reference</p>
              <p class="mono" style="font-size:8.5pt;color:#4a5568;margin:0;">' . $ref . '</p>
            </td>
          </tr>
        </table>
      </td>

      <td class="perforation" style="width:10px;">&nbsp;</td>

      <td width="30%" style="background:#1a2744;padding:18px 16px 14px;vertical-align:top;text-align:center;">
        <p class="label" style="color:#c9a962;margin:0 0 4px;">Scan at entrance</p>
        <p class="serif" style="font-size:11pt;line-height:1.2;color:#f4efe4;font-style:italic;margin:0 0 10px;">Present this ticket</p>
        ' . $qrBlock . '
        <p class="mono" style="font-size:6.5pt;color:#8a96ab;margin:8px 0 0;word-break:break-all;">' . $ref . '</p>
      </td>
    </tr>
  </table>
  <p style="text-align:center;font-size:7pt;color:#8a96ab;margin-top:12px;font-style:italic;">Cavemen Africa · CAVEMEN IMPACT SOLUTIONS LTD</p>
</body>
</html>';
    }

    private static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
