<?php

namespace App\Security;

use App\Repositories\UserRepository;

class AuthMiddleware {
    /**
     * Intercepta a requisição, valida o Token JWT e confirma a existência do usuário no MySQL.
     * Retorna os dados do usuário autenticado ou encerra com HTTP 401 Unauthorized.
     */
    public static function authenticate(): array {
        $token = self::getBearerToken();

        if (!$token) {
            http_response_code(401);
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Acesso não autorizado. Token JWT ausente."
            ]);
            exit;
        }

        // Validação 1: Assinatura Digital e Expiração do JWT
        $payload = JwtManager::validateToken($token);

        if (!$payload || !isset($payload->user->id_user)) {
            http_response_code(401);
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Acesso não autorizado. Token JWT inválido ou adulterado."
            ]);
            exit;
        }

        $idUser = (int)$payload->user->id_user;

        // Validação 2: Confirmação em tempo real no banco MySQL
        $userRepo = new UserRepository();
        $userBanco = $userRepo->buscarPorId($idUser);

        if (!$userBanco) {
            http_response_code(401);
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Acesso não autorizado. Usuário não encontrado ou inativo no banco de dados."
            ]);
            exit;
        }

        return [
            'id_user' => (int)$userBanco['id_user'],
            'nome_user' => $userBanco['nome_user'],
            'permissao_user' => $userBanco['permissao_user'] ?? 'colaborador'
        ];
    }

    /**
     * Exige que o usuário esteja autenticado E possua permissão de 'admin'.
     */
    public static function requireAdmin(): array {
        $usuario = self::authenticate();

        if (strtolower($usuario['permissao_user'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Acesso negado. Esta ação requer privilégios de administrador."
            ]);
            exit;
        }

        return $usuario;
    }

    /**
     * Tenta autenticar o usuário se o token for fornecido. Se não houver token, retorna null.
     */
    public static function tryAuthenticate(): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        $payload = JwtManager::validateToken($token);
        if (!$payload || !isset($payload->user->id_user)) {
            return null;
        }

        $idUser = (int)$payload->user->id_user;
        $userRepo = new UserRepository();
        $userBanco = $userRepo->buscarPorId($idUser);

        if (!$userBanco) {
            return null;
        }

        return [
            'id_user' => (int)$userBanco['id_user'],
            'nome_user' => $userBanco['nome_user'],
            'permissao_user' => $userBanco['permissao_user'] ?? 'colaborador'
        ];
    }


    /**
     * Extrai o Token Bearer do cabeçalho HTTP Authorization ou do parâmetro de URL ?token=.
     */
    private static function getBearerToken(): ?string {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
                return $matches[1];
            }
        }

        // Suporte a parâmetro de URL (ex: download da planilha CSV via navegador)
        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
        }

        return null;
    }
}
