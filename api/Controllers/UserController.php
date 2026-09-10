<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Security\AuthMiddleware;

class UserController {
    private UserService $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $nomeUser = $input['nome_user'] ?? $_POST['nome_user'] ?? '';
        $senha = $input['senha'] ?? $_POST['senha'] ?? '';

        $resultado = $this->userService->loginUser($nomeUser, $senha);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function validar2FA(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $idUser = (int)($input['id_user'] ?? $_POST['id_user'] ?? 0);
        $codigo2FA = $input['codigo_2fa'] ?? $_POST['codigo_2fa'] ?? '';

        $resultado = $this->userService->validar2FA($idUser, $codigo2FA);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function setup2FA(): void {
        // Exige autenticação prévia pelo Middleware
        $usuarioLogado = AuthMiddleware::authenticate();

        $resultado = $this->userService->setup2FA($usuarioLogado['id_user']);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function checarSessao(): void {
        // Valida o Token JWT enviado no cabeçalho Authorization: Bearer <token>
        $usuarioLogado = AuthMiddleware::authenticate();

        http_response_code(200);
        echo json_encode([
            'status' => 'sucesso',
            'logado' => true,
            'usuario' => $usuarioLogado
        ]);
    }

    public function criar(): void {
        // Exige permissão de Admin para cadastrar novos usuários no sistema
        AuthMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $nomeUser = $input['nome_user'] ?? $_POST['nome_user'] ?? '';
        $senhaUser = $input['senha_user'] ?? $input['senha'] ?? $_POST['senha_user'] ?? '';
        $permissao = $input['permissao_user'] ?? $_POST['permissao_user'] ?? 'colaborador';

        $resultado = $this->userService->criarUser($nomeUser, $senhaUser, $permissao);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function listar(): void {
        // Exige permissão de Admin para listar todos os usuários
        AuthMiddleware::requireAdmin();

        $resultado = $this->userService->listarUsuarios();
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function confirmarSetup2FA(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $tempToken = $input['temp_2fa_token'] ?? $_POST['temp_2fa_token'] ?? '';
        $codigo2FA = $input['codigo_2fa'] ?? $_POST['codigo_2fa'] ?? '';

        $resultado = $this->userService->confirmarSetup2FA($tempToken, $codigo2FA);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function atualizarPermissao(): void {
        // Exige permissão de Admin para alterar cargos/permissões
        $usuarioAdmin = AuthMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $idUser = (int)($input['id_user'] ?? $_POST['id_user'] ?? 0);
        $novaPermissao = $input['permissao_user'] ?? $_POST['permissao_user'] ?? '';

        $resultado = $this->userService->alterarPermissaoUser($idUser, $novaPermissao, $usuarioAdmin['id_user']);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function atualizar(): void {
        // Exige permissão de Admin para atualizar dados do usuário
        $usuarioAdmin = AuthMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $idUser = (int)($input['id_user'] ?? $_POST['id_user'] ?? 0);
        $nomeUser = $input['nome_user'] ?? $_POST['nome_user'] ?? '';
        $senhaUser = !empty($input['senha_user']) ? $input['senha_user'] : (!empty($_POST['senha_user']) ? $_POST['senha_user'] : null);
        $permissao = $input['permissao_user'] ?? $_POST['permissao_user'] ?? 'colaborador';

        $resultado = $this->userService->atualizarUser($idUser, $nomeUser, $senhaUser, $permissao, $usuarioAdmin['id_user']);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function excluir(): void {
        // Exige permissão de Admin para excluir usuário
        $usuarioAdmin = AuthMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $idUser = (int)($input['id_user'] ?? $_POST['id_user'] ?? 0);

        $resultado = $this->userService->excluirUser($idUser, $usuarioAdmin['id_user']);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }
}