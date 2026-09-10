<?php

namespace App\Security;

class TwoFactorAuth {
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Gera uma chave secreta aleatória de 16 caracteres em Base32.
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Gera a URI no padrão otpauth:// para TOTP RFC 6238.
     */
    public static function getOtpauthUri(string $username, string $secret, string $issuer = 'BrasEq-Almoco'): string {
        return "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($username) . "?secret=" . rawurlencode($secret) . "&issuer=" . rawurlencode($issuer);
    }

    /**
     * Retorna a URL do QR Code (suporta renderização interna e fallback).
     */
    public static function getQrCodeUrl(string $username, string $secret, string $issuer = 'BrasEq-Almoco'): string {
        $otpauthUrl = self::getOtpauthUri($username, $secret, $issuer);
        return "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($otpauthUrl) . "&size=220x220&ecc=M";
    }

    /**
     * Valida o código de 6 dígitos temporário (TOTP RFC 6238) gerado no celular.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        $cleanCode = preg_replace('/\s+/', '', $code);

        if (strlen($cleanCode) !== 6 || !is_numeric($cleanCode)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::calculateTotpCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o código TOTP atual de 6 dígitos para uma chave secreta.
     */
    public static function getCode(string $secret): string {
        $timeSlice = floor(time() / 30);
        return self::calculateTotpCode($secret, $timeSlice);
    }

    private static function calculateTotpCode(string $secret, float $timeSlice): string {
        $secretKey = self::base32Decode($secret);
        $timeData = pack('N*', 0, $timeSlice);
        $hmac = hash_hmac('sha1', $timeData, $secretKey, true);

        $offset = ord($hmac[19]) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashPart)[1];
        $value = $value & 0x7FFFFFFF;

        $modulo = $value % 1000000;
        return str_pad((string)$modulo, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string {
        $secret = strtoupper($secret);
        $binaryString = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $position = strpos(self::$base32Chars, $secret[$i]);
            if ($position === false) continue;
            $binaryString .= sprintf('%05b', $position);
        }

        $bytes = [];
        for ($i = 0; $i < strlen($binaryString) - 7; $i += 8) {
            $bytes[] = chr(bindec(substr($binaryString, $i, 8)));
        }

        return implode('', $bytes);
    }
}
