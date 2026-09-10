<?php

namespace App\Security;

use App\Config\Env;

class JwtManager {
    private static function getSecret(): string {
        return Env::get('JWT_SECRET', 'CHANGE_ME_JWT_SECRET');
    }

    /**
     * Gera um Token JWT assinado digitalmente com HMAC-SHA256.
     */
    public static function generateToken(array $userData, int $expirationHours = 8): string {
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);

        $issuedAt = time();
        $expiration = $issuedAt + ($expirationHours * 3600);

        $userPayload = array_merge([
            'id_user' => (int)($userData['id_user'] ?? $userData['id'] ?? 0),
            'nome_user' => $userData['nome_user'] ?? $userData['nome'] ?? '',
            'permissao_user' => $userData['permissao_user'] ?? $userData['permissao'] ?? 'colaborador'
        ], $userData);

        $payload = json_encode([
            'iss' => 'braseq-almoco-api',
            'iat' => $issuedAt,
            'exp' => $expiration,
            'user' => $userPayload
        ]);

        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", self::getSecret(), true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Valida a integridade, expiração e assinatura digital do Token JWT.
     * Retorna o payload decodificado se válido, ou null se alterado/expirado.
     */
    public static function validateToken(string $token): ?object {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        // Recalcula a assinatura com a Chave Secreta do Servidor
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", self::getSecret(), true);
        $expectedBase64Signature = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedBase64Signature, $base64Signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($base64Payload);
        $payload = json_decode($payloadJson);

        if (!$payload || !isset($payload->exp)) {
            return null;
        }

        if (time() >= $payload->exp) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
