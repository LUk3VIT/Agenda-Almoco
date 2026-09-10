<?php

namespace App\Repositories;

use App\Config\Database;
use PDO;

class UserRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function buscarPorId(int $idUser): ?array {
        $sql = "SELECT id_user, nome_user, permissao_user, secret_2fa, is_2fa_enabled, criado_em 
                FROM users WHERE id_user = :id_user LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_user' => $idUser]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user : null;
    }

    public function buscarPorNome(string $nomeUser): ?array {
        $sql = "SELECT id_user, nome_user, senha_user, permissao_user, secret_2fa, is_2fa_enabled, criado_em 
                FROM users WHERE nome_user = :nome_user LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nome_user' => $nomeUser]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user : null;
    }

    public function criarUser(string $nomeUser, string $senhaHash, string $permissao = 'colaborador'): bool {
        $sql = "INSERT INTO users (nome_user, senha_user, permissao_user) 
                VALUES (:nome_user, :senha_user, :permissao_user)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nome_user' => $nomeUser,
            'senha_user' => $senhaHash,
            'permissao_user' => $permissao
        ]);
    }

    public function salvarSecret2FA(int $idUser, string $secretEncrypted): bool {
        $sql = "UPDATE users SET secret_2fa = :secret_2fa WHERE id_user = :id_user";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'secret_2fa' => $secretEncrypted,
            'id_user' => $idUser
        ]);
    }

    public function ativar2FA(int $idUser): bool {
        $sql = "UPDATE users SET is_2fa_enabled = 1 WHERE id_user = :id_user";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id_user' => $idUser]);
    }

    public function desativar2FA(int $idUser): bool {
        $sql = "UPDATE users SET is_2fa_enabled = 0, secret_2fa = NULL WHERE id_user = :id_user";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id_user' => $idUser]);
    }

    public function listarTodosUsuarios(): array {
        $sql = "SELECT id_user, nome_user, permissao_user, is_2fa_enabled, criado_em 
                FROM users ORDER BY id_user DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarPermissao(int $idUser, string $novaPermissao): bool {
        $sql = "UPDATE users SET permissao_user = :permissao WHERE id_user = :id_user";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'permissao' => $novaPermissao,
            'id_user' => $idUser
        ]);
    }

    public function atualizarUser(int $idUser, string $nomeUser, ?string $senhaHash, string $permissao): bool {
        if ($senhaHash !== null && !empty($senhaHash)) {
            $sql = "UPDATE users SET nome_user = :nome, senha_user = :senha, permissao_user = :permissao WHERE id_user = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'nome' => $nomeUser,
                'senha' => $senhaHash,
                'permissao' => $permissao,
                'id' => $idUser
            ]);
        } else {
            $sql = "UPDATE users SET nome_user = :nome, permissao_user = :permissao WHERE id_user = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'nome' => $nomeUser,
                'permissao' => $permissao,
                'id' => $idUser
            ]);
        }
    }

    public function excluirUser(int $idUser): bool {
        $sql = "DELETE FROM users WHERE id_user = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $idUser]);
    }
}