<?php

/**
 * Gate-pass QR (PNG) encoding the Flutterwave payment reference.
 */
class CavemenTicketQr
{
    /**
     * @return string|null raw PNG bytes
     */
    public static function pngBytes($payload)
    {
        $data = trim((string) $payload);
        if ($data === '') {
            return null;
        }
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_readable($autoload) || !extension_loaded('gd')) {
            return null;
        }
        require_once $autoload;
        if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
            return null;
        }

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M,
                'scale' => 10,
                'imageBase64' => false,
            ]);
            $out = (new \chillerlan\QRCode\QRCode($options))->render($data);
            if (!is_string($out) || $out === '') {
                return null;
            }
            if (strpos($out, 'data:image/') === 0) {
                $parts = explode(',', $out, 2);
                if (!isset($parts[1])) {
                    return null;
                }
                $decoded = base64_decode($parts[1], true);
                return $decoded !== false ? $decoded : null;
            }

            return $out;
        } catch (Throwable $e) {
            error_log('[cavemen] QR render: ' . $e->getMessage());
            return null;
        }
    }
}
