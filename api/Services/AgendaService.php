<?php

namespace App\Services;

use App\Repositories\AgendaRepository;
use PDOException;

class AgendaService {
    private AgendaRepository $repo;

    public function __construct() {
        $this->repo = new AgendaRepository();
    }

    public function listarGradeFormatada(): array {
        try {
            $registros = $this->repo->listarTodosComColaboradores();
            $blocos = [];

            foreach ($registros as $linha) {
                $id = (int)$linha['id_horario'];
                if (!isset($blocos[$id])) {
                    $inicio = substr($linha['horario_inicio'], 0, 5);
                    $fim = substr($linha['horario_fim'], 0, 5);

                    $blocos[$id] = [
                        'id' => $id,
                        'horario' => "{$inicio} - {$fim}",
                        'total_ocupado' => 0,
                        'limite' => 4,
                        'colaboradores' => []
                    ];
                }
                if (!empty($linha['nome_user'])) {
                    $blocos[$id]['colaboradores'][] = [
                        'id' => (int)$linha['id_user'],
                        'nome' => $linha['nome_user'],
                    ];
                    $blocos[$id]['total_ocupado']++;
                }
            }
            return [
                'agenda_bloqueada' => $this->repo->obterBloqueioAgenda(),
                'horarios' => array_values($blocos)
            ];
        } catch (PDOException $e) {
            return [
                'agenda_bloqueada' => false,
                'horarios' => []
            ];
        }
    }

    public function cadastrarColaborador(int $idHorario, string $nomeUser): array {
        // Verificar se a agenda está bloqueada pelo administrador
        if ($this->repo->obterBloqueioAgenda()) {
            return [
                'sucesso' => false,
                'codigo' => 403,
                'mensagem' => 'A agenda está bloqueada para novos agendamentos pelo administrador.'
            ];
        }

        $nomeLimpo = trim($nomeUser);
        if (empty($nomeLimpo)) {
            return [
                'sucesso' => false,
                'codigo' => 400,
                'mensagem' => 'O nome não pode ser vazio.'
            ];
        }

        try {
            $ocupantes = $this->repo->contarOcupantes($idHorario);
            if ($ocupantes >= 4) {
                return [
                    'sucesso' => false,
                    'codigo' => 422,
                    'mensagem' => 'O horário já atingiu o limite de 4 colaboradores.'
                ];
            }

            $salvo = $this->repo->salvar($idHorario, $nomeLimpo);
            if (!$salvo) {
                return [
                    'sucesso' => false,
                    'codigo' => 500,
                    'mensagem' => 'Erro ao salvar o colaborador.'
                ];
            }

            return [
                'sucesso' => true,
                'codigo' => 201,
                'mensagem' => 'Colaborador cadastrado com sucesso.'
            ];
        } catch (PDOException $e) {
            // Se falhar devido à Chave Estrangeira (Foreign Key id_horario inexistente no banco)
            if ($e->getCode() == '23000') {
                return [
                    'sucesso' => false,
                    'codigo' => 400,
                    'mensagem' => 'O horário selecionado é inválido ou não existe.'
                ];
            }
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno ao processar o agendamento.'
            ];
        }
    }

    public function toggleBloqueioAgenda(bool $bloquear, ?int $adminId = null): array {
        try {
            $sucesso = $this->repo->setBloqueioAgenda($bloquear, $adminId);
            $msg = $bloquear ? 'Agenda bloqueada para novos agendamentos com sucesso.' : 'Agenda liberada para agendamentos.';
            return [
                'sucesso' => $sucesso,
                'codigo' => $sucesso ? 200 : 500,
                'mensagem' => $msg,
                'agenda_bloqueada' => $bloquear
            ];
        } catch (PDOException $e) {
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao alterar estado de bloqueio da agenda.'
            ];
        }
    }

    public function cadastrarHorario(string $inicio, string $fim): array {
        if (empty($inicio) || empty($fim)) {
            return [
                'sucesso' => false,
                'codigo' => 400,
                'mensagem' => 'Os horários de início e fim são obrigatórios.'
            ];
        }

        try {
            $salvo = $this->repo->cadastrarHorario($inicio, $fim);
            return [
                'sucesso' => $salvo,
                'codigo' => $salvo ? 201 : 500,
                'mensagem' => $salvo ? 'Novo horário cadastrado com sucesso.' : 'Erro ao cadastrar novo horário.'
            ];
        } catch (PDOException $e) {
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao inserir novo horário no banco de dados.'
            ];
        }
    }

    public function atualizarHorario(int $idHorario, string $inicio, string $fim): array {
        if ($idHorario <= 0 || empty($inicio) || empty($fim)) {
            return [
                'sucesso' => false,
                'codigo' => 400,
                'mensagem' => 'ID, início e fim do horário são obrigatórios.'
            ];
        }

        try {
            $atualizado = $this->repo->atualizarHorario($idHorario, $inicio, $fim);
            return [
                'sucesso' => $atualizado,
                'codigo' => $atualizado ? 200 : 500,
                'mensagem' => $atualizado ? 'Horário atualizado com sucesso.' : 'Erro ao atualizar horário.'
            ];
        } catch (PDOException $e) {
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno ao atualizar horário.'
            ];
        }
    }

    public function excluirHorario(int $idHorario): array {
        if ($idHorario <= 0) {
            return [
                'sucesso' => false,
                'codigo' => 400,
                'mensagem' => 'ID do horário inválido.'
            ];
        }

        try {
            $excluido = $this->repo->excluirHorario($idHorario);
            return [
                'sucesso' => $excluido,
                'codigo' => $excluido ? 200 : 500,
                'mensagem' => $excluido ? 'Horário e agendamentos associados excluídos com sucesso.' : 'Erro ao excluir horário.'
            ];
        } catch (PDOException $e) {
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno ao excluir horário.'
            ];
        }
    }


    public function limparAgendamentos(): array {
        try {
            $limpo = $this->repo->limparTodosAgendamentos();
            if (!$limpo) {
                return [
                    'sucesso' => false,
                    'codigo' => 500,
                    'mensagem' => 'Erro ao limpar os agendamentos.'
                ];
            }
            return [
                'sucesso' => true,
                'codigo' => 200,
                'mensagem' => 'Todos os agendamentos foram limpos com sucesso!'
            ];
        } catch (PDOException $e) {
            return [
                'sucesso' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno de banco de dados ao limpar agendamentos.'
            ];
        }
    }

    public function gerarCsvRelatorio(): void {
        try {
            $registros = $this->repo->listarRelatorioExportacao();
            $nomeArquivo = "relatorio_almoco_" . date('Y-m-d') . ".csv";

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

            $output = fopen('php://output', 'w');

            // Insere o BOM UTF-8 para o Excel reconhecer acentos automaticamente
            fprintf($output, "\xEF\xBB\xBF");

            // Cabeçalho do arquivo CSV
            fputcsv($output, ['Horário de Almoço', 'Nome do Colaborador', 'Status', 'Data do Agendamento'], ';');

            foreach ($registros as $row) {
                $inicio = substr($row['horario_inicio'], 0, 5);
                $fim = substr($row['horario_fim'], 0, 5);
                $horario = "{$inicio} - {$fim}";

                $nome = !empty($row['nome_user']) ? $row['nome_user'] : '[ Vago / Livre ]';
                $status = !empty($row['nome_user']) ? 'Ocupado' : 'Disponível';
                $data = !empty($row['criado_em']) ? date('d/m/Y H:i', strtotime($row['criado_em'])) : '-';

                fputcsv($output, [$horario, $nome, $status, $data], ';');
            }

            fclose($output);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "erro",
                "mensagem" => "Erro ao gerar o relatório."
            ]);
            exit;
        }
    }
}