<?php

namespace App\Security;

class PasswordHasher {
    /**
     * Gera o hash BCrypt seguro para salvar no banco de dados.
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Valida se a senha informada corresponde ao hash BCrypt salvo.
     */
    public static function verifyPassword(string $password, string $hash): bool {
        if (empty($password) || empty($hash)) {
            return false;
        }

        // Se a senha estiver salva em texto limpo (migração/legado), faz a verificação direta
        if (!str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$2a$') && !str_starts_with($hash, '$2b$')) {
            return hash_equals($hash, $password);
        }

        return password_verify($password, $hash);
    }
}
