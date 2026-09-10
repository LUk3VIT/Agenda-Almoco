<?php

namespace App\Security;

use App\Config\Env;

class Encrypter {
    private static function getMasterKey(): string {
        return Env::get('APP_MASTER_KEY', 'CHANGE_ME_MASTER_KEY');
    }

    /**
     * Criptografa dados sensíveis (como a chave 2FA) usando AES-256-CBC.
     */
    public static function encrypt(string $plainText): string {
        if (empty($plainText)) {
            return '';
        }
        $cipher = 'aes-256-cbc';
        $key = hash('sha256', self::getMasterKey(), true);
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($plainText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa dados sensíveis salvos no banco.
     */
    public static function decrypt(string $encryptedBase64): string {
        if (empty($encryptedBase64)) {
            return '';
        }

        $decoded = base64_decode($encryptedBase64, true);
        if ($decoded === false) {
            return $encryptedBase64;
        }

        $cipher = 'aes-256-cbc';
        $key = hash('sha256', self::getMasterKey(), true);
        $ivLength = openssl_cipher_iv_length($cipher);

        if (strlen($decoded) < $ivLength) {
            return $encryptedBase64;
        }

        $iv = substr($decoded, 0, $ivLength);
        $rawEncrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($rawEncrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $encryptedBase64;
    }
}
