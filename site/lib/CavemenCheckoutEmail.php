<?php

/**
 * Paid-order confirmation emails for Tatsuniya and Kanti (Resend, then PHPMailer).
 */
class CavemenCheckoutEmail
{
    public static function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string,string> $rows
     * @return array{html:string,text:string}
     */
    public static function build($recipientName, $headline, $intro, array $rows, $closing)
    {
        $sans = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
        $safeName = self::escapeHtml($recipientName);
        $safeHeadline = self::escapeHtml($headline);
        $safeIntro = self::escapeHtml($intro);
        $safeClosing = self::escapeHtml($closing);

        $htmlRows = '';
        $textRows = '';
        foreach ($rows as $label => $value) {
            if ((string) $value === '') {
                continue;
            }
            $safeLabel = self::escapeHtml((string) $label);
            $safeValue = nl2br(self::escapeHtml((string) $value));
            $htmlRows .= "<tr>
              <td style=\"padding:8px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#9a9084;font-family:{$sans};width:38%;vertical-align:top;\">{$safeLabel}</td>
              <td style=\"padding:8px 0;font-size:15px;color:#f6f1e8;font-family:{$sans};\">{$safeValue}</td>
            </tr>";
            $textRows .= (string) $label . ': ' . (string) $value . "\n";
        }

        $html = "<!DOCTYPE html><html><body style=\"margin:0;padding:0;background:#f6f1e8;\">
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"background:#f6f1e8;padding:24px 12px;\">
  <tr><td align=\"center\">
    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"max-width:600px;width:100%;background:#1a1815;border-radius:16px;overflow:hidden;\">
      <tr><td style=\"padding:28px 28px 8px;font-family:Georgia,serif;font-size:26px;color:#f6f1e8;\">{$safeHeadline}</td></tr>
      <tr><td style=\"padding:8px 28px 20px;font-size:15px;line-height:1.6;color:#d4c9b5;font-family:{$sans};\">Hello {$safeName}, {$safeIntro}</td></tr>
      <tr><td style=\"padding:0 28px 12px;\">
        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">{$htmlRows}</table>
      </td></tr>
      <tr><td style=\"padding:8px 28px 28px;font-size:14px;line-height:1.6;color:#d4c9b5;font-family:{$sans};\">{$safeClosing}<br><br>Cavemen Africa<br>info@cavemen.africa</td></tr>
    </table>
  </td></tr>
</table>
</body></html>";

        $text = $headline . "\n\nHello " . $recipientName . ", " . $intro . "\n\n" . $textRows . "\n" . $closing . "\n\nCavemen Africa\ninfo@cavemen.africa\n";

        return ['html' => $html, 'text' => $text];
    }

    public static function send($to, $subject, $html, $text)
    {
        $hasResend = (string) (getenv('RESEND_API_KEY') ?: '') !== '';
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($hasResend && AsaliEmailPhp::sendWithResend($to, $subject, $html, $text)) {
                return true;
            }
            if ($attempt < 2) {
                usleep(250000 * ($attempt + 1));
            }
        }

        return AsaliEmailPhp::sendWithPhpMailer($to, $subject, $html, $text);
    }
}
