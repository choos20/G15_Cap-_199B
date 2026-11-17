<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Small RFC 6238 TOTP implementation used by the privileged-account MFA flow.
 *
 * The implementation deliberately keeps the secret and verification logic on
 * the server. Authenticator apps only need the standard otpauth URI generated
 * here; no vendor-specific SafeAuth integration is required.
 */
final class TotpService
{
    public const PERIOD_SECONDS = 30;

    private const SECRET_BYTES = 20;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bits = '';

        foreach (str_split(random_bytes(self::SECRET_BYTES)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $secret;
    }

    public static function provisioningUri(User $user, string $secret): string
    {
        $label = rawurlencode('ProjectLink:' . $user->email);
        $issuer = rawurlencode('ProjectLink');

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=" . self::PERIOD_SECONDS;
    }

    public static function formatSecret(string $secret): string
    {
        return implode(' ', str_split($secret, 4));
    }

    /**
     * Render a QR data URI, preferring PNG when GD is available and falling
     * back to SVG so enrollment still works on a server without GD enabled.
     */
    public static function qrCodeDataUri(string $payload): string
    {
        try {
            if (extension_loaded('gd') && function_exists('gd_info')) {
                $writer = new Writer(new GDLibRenderer(260));

                return 'data:image/png;base64,' . base64_encode($writer->writeString($payload));
            }
        } catch (\Throwable) {
            // Fall through to SVG generation.
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(260),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $key = self::base32Decode($secret);
        if ($key === null) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeAt($key, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the current six-digit code for a secret. This is useful for
     * deterministic integration tests and keeps the RFC 6238 calculation in
     * one place; the application never sends this value anywhere.
     */
    public static function code(string $secret, ?int $timestamp = null): ?string
    {
        $key = self::base32Decode($secret);
        if ($key === null) {
            return null;
        }

        return self::codeAt($key, intdiv($timestamp ?? time(), self::PERIOD_SECONDS));
    }

    private static function codeAt(string $key, int $counter): string
    {
        $high = intdiv($counter, 4294967296);
        $low = $counter % 4294967296;
        $hash = hash_hmac('sha1', pack('N2', $high, $low), $key, true);
        $offset = ord($hash[19]) & 0x0f;

        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): ?string
    {
        $secret = strtoupper((string) preg_replace('/[\s-]+/', '', $secret));
        $secret = rtrim($secret, '=');
        if ($secret === '') {
            return null;
        }

        $bits = '';
        foreach (str_split($secret) as $character) {
            $index = strpos(self::BASE32_ALPHABET, $character);
            if ($index === false) {
                return null;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }

            $bytes .= chr(bindec($chunk));
        }

        return $bytes !== '' ? $bytes : null;
    }
}
