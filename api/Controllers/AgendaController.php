<?php

namespace App\Controllers;

use App\Services\AgendaService;
use App\Security\AuthMiddleware;

class AgendaController {
    private AgendaService $service;

    public function __construct() {
        $this->service = new AgendaService();
    }

    public function listar(): void {
        $resultado = $this->service->listarGradeFormatada();

        http_response_code(200);
        echo json_encode([
            "status" => "sucesso",
            "agenda_bloqueada" => $resultado['agenda_bloqueada'] ?? false,
            "dados"  => $resultado['horarios'] ?? []
        ]);
    }

    public function agendar(): void {
        // Tenta identificar se o usuário está logado por token JWT. Se não estiver, aceita o nome público no corpo.
        $usuarioLogado = AuthMiddleware::tryAuthenticate();

        $corpo = json_decode(file_get_contents('php://input'), true) ?? [];
        $idHorario = $corpo['id_horario'] ?? $_POST['id_horario'] ?? null;
        $nomeUser = $usuarioLogado['nome_user'] ?? $corpo['nome_colaborador'] ?? $corpo['nome_user'] ?? $_POST['nome_colaborador'] ?? $_POST['nome_user'] ?? '';

        if (!$idHorario || empty(trim($nomeUser))) {
            http_response_code(400);
            echo json_encode([
                "status"   => "erro",
                "mensagem" => "O campo 'id_horario' e o nome do colaborador são obrigatórios."
            ]);
            return;
        }

        $resultado = $this->service->cadastrarColaborador((int)$idHorario, (string)$nomeUser);

        http_response_code($resultado['codigo']);
        echo json_encode([
            "status"   => $resultado['sucesso'] ? "sucesso" : "erro",
            "mensagem" => $resultado['mensagem']
        ]);
    }

    public function limpar(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        AuthMiddleware::requireAdmin();

        $resultado = $this->service->limparAgendamentos();

        http_response_code($resultado['codigo']);
        echo json_encode([
            "status"   => $resultado['sucesso'] ? "sucesso" : "erro",
            "mensagem" => $resultado['mensagem']
        ]);
    }

    public function exportar(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        AuthMiddleware::requireAdmin();

        $this->service->gerarCsvRelatorio();
    }

    public function toggleBloqueio(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        $usuarioAdmin = AuthMiddleware::requireAdmin();

        $corpo = json_decode(file_get_contents('php://input'), true) ?? [];
        $bloquear = isset($corpo['bloquear']) ? (bool)$corpo['bloquear'] : (isset($_POST['bloquear']) ? (bool)$_POST['bloquear'] : true);

        $resultado = $this->service->toggleBloqueioAgenda($bloquear, $usuarioAdmin['id_user']);

        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function cadastrarHorario(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        AuthMiddleware::requireAdmin();

        $corpo = json_decode(file_get_contents('php://input'), true) ?? [];
        $inicio = $corpo['horario_inicio'] ?? $_POST['horario_inicio'] ?? '';
        $fim = $corpo['horario_fim'] ?? $_POST['horario_fim'] ?? '';

        $resultado = $this->service->cadastrarHorario($inicio, $fim);

        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function atualizarHorario(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        AuthMiddleware::requireAdmin();

        $corpo = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($corpo['id_horario'] ?? $_POST['id_horario'] ?? 0);
        $inicio = $corpo['horario_inicio'] ?? $_POST['horario_inicio'] ?? '';
        $fim = $corpo['horario_fim'] ?? $_POST['horario_fim'] ?? '';

        $resultado = $this->service->atualizarHorario($id, $inicio, $fim);

        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }

    public function excluirHorario(): void {
        // Exige privilégios de Admin (permissao_user === 'admin')
        AuthMiddleware::requireAdmin();

        $corpo = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($corpo['id_horario'] ?? $_POST['id_horario'] ?? 0);

        $resultado = $this->service->excluirHorario($id);

        http_response_code($resultado['codigo']);
        echo json_encode($resultado);
    }
}