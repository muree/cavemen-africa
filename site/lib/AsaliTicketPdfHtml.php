<?php

require_once __DIR__ . '/CavemenTicketTier.php';

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

        $tier = CavemenTicketTier::forType($reg['attendanceType'] ?? '', $isDahk);
        $paper = $tier['paper'];
        $ink = $tier['ink'];
        $accent = $tier['accent'];
        $rule = $tier['rule'];
        $onInk = $tier['onInk'];
        $inkSoft = $tier['inkSoft'];
        $badge = self::e(strtoupper($tier['badge']));
        $admitWord = self::e(strtoupper($tier['admitWord']));
        $mark = self::e($tier['mark']);
        $summary = self::e($tier['summary']);
        // DejaVu Serif has no glyph for some marks; render them in DejaVu Sans.
        $markSans = '<span style="font-family:DejaVu Sans, sans-serif;">' . $mark . '</span>';

        $summaryBlock = $summary === ''
            ? ''
            : '<p style="font-size:9pt;line-height:1.4;color:' . $ink . ';font-style:italic;margin:0 0 16px;">' . $summary . '</p>';

        $noteItems = '';
        foreach ($tier['lines'] as $line) {
            $noteItems .= '<p style="font-size:7.5pt;line-height:1.45;color:#4a5568;margin:0 0 3px;">' . $mark . ' ' . self::e($line) . '</p>';
        }
        $notesBlock = $noteItems === ''
            ? ''
            : '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:14px;border-top:1px solid ' . $rule . ';padding-top:10px;">
          <tr>
            <td>
              <p class="label" style="color:' . $accent . ';">' . $badge . '</p>
              ' . $noteItems . '
            </td>
          </tr>
        </table>';

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
      color: ' . $ink . ';
      font-size: 9pt;
      margin: 0;
      padding: 18px;
      background: #ffffff;
    }
    .ticket-wrap {
      border: 2px solid ' . $accent . ';
      outline: 1px solid ' . $accent . ';
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
      background: ' . $paper . ';
      border-left: 2px dashed ' . $inkSoft . ';
      border-right: 2px dashed ' . $inkSoft . ';
    }
  </style>
</head>
<body>
  <table class="ticket-wrap" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;background:' . $paper . ';">
    <tr>
      <td width="8" style="width:8px;background:' . $accent . ';">&nbsp;</td>

      <td width="69%" style="background:' . $paper . ';padding:22px 24px 20px;vertical-align:top;border-right:none;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td width="72%" style="vertical-align:top;padding-bottom:14px;">
              <p style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:' . $ink . ';font-weight:700;margin:0 0 2px;">Cavemen Africa</p>
              <p style="font-size:6.5pt;letter-spacing:0.12em;text-transform:uppercase;color:#6b6358;margin:0;">Studio of Studios · Kano</p>
            </td>
            <td width="28%" align="right" style="vertical-align:top;">
              <p class="label" style="margin:0 0 2px;color:' . $accent . ';">Admit</p>
              <p class="serif" style="font-size:20pt;line-height:1;color:' . $ink . ';font-weight:700;margin:0;">' . $markSans . ' ' . $admitWord . '</p>
            </td>
          </tr>
        </table>

        <table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 10px;">
          <tr>
            <td style="background:' . $accent . ';padding:5px 12px;">
              <p style="font-size:7.5pt;letter-spacing:0.18em;text-transform:uppercase;color:' . $ink . ';font-weight:700;margin:0;">' . $badge . '</p>
            </td>
          </tr>
        </table>

        <p class="label" style="margin:0 0 6px;color:' . $accent . ';">' . $series . '</p>
        <h1 class="serif" style="font-size:22pt;line-height:1.15;color:' . $ink . ';font-weight:700;margin:0 0 8px;">' . $event . '</h1>
        ' . $summaryBlock . '

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:18px;">
          <tr>
            <td width="38%" style="vertical-align:top;padding-right:12px;">
              <p class="label">Date</p>
              <p class="serif" style="font-size:10.5pt;line-height:1.35;color:' . $ink . ';margin:0;">' . ($when !== '' ? $when : 'See event listing') . '</p>
            </td>
            <td width="62%" style="vertical-align:top;">
              <p class="label">Location</p>
              <p class="serif" style="font-size:10.5pt;line-height:1.35;color:' . $ink . ';margin:0;">' . $venue . '</p>
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-top:1px solid ' . $rule . ';padding-top:14px;">
          <tr>
            <td width="44%" style="vertical-align:top;padding-right:10px;">
              <p class="label">Attendee</p>
              <p class="serif" style="font-size:12pt;line-height:1.3;color:' . $ink . ';font-weight:700;margin:0;">' . $name . '</p>
            </td>
            <td width="28%" style="vertical-align:top;padding-right:10px;">
              <p class="label">Ticket</p>
              <p class="serif" style="font-size:11pt;line-height:1.3;color:' . $ink . ';font-weight:700;margin:0;">' . $type . '</p>
            </td>
            <td width="28%" style="vertical-align:top;">
              <p class="label">Paid</p>
              <p class="serif" style="font-size:16pt;line-height:1.1;color:' . $ink . ';font-weight:700;margin:0;">' . $amount . '</p>
            </td>
          </tr>
        </table>

        ' . $notesBlock . '

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:16px;border-top:1px solid ' . $rule . ';padding-top:10px;">
          <tr>
            <td>
              <p class="label">Reference</p>
              <p class="mono" style="font-size:8.5pt;color:#4a5568;margin:0;">' . $ref . '</p>
            </td>
          </tr>
        </table>
      </td>

      <td class="perforation" style="width:10px;">&nbsp;</td>

      <td width="30%" style="background:' . $ink . ';padding:18px 16px 14px;vertical-align:top;text-align:center;">
        <p class="label" style="color:' . $accent . ';margin:0 0 4px;">' . $badge . '</p>
        <p class="serif" style="font-size:11pt;line-height:1.2;color:' . $onInk . ';font-style:italic;margin:0 0 10px;">Scan at entrance</p>
        ' . $qrBlock . '
        <p class="serif" style="font-size:10pt;color:' . $accent . ';font-weight:700;margin:6px 0 0;">' . $markSans . ' ' . $type . '</p>
        <p class="mono" style="font-size:6.5pt;color:' . $inkSoft . ';margin:6px 0 0;word-break:break-all;">' . $ref . '</p>
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
