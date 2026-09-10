<?php

namespace App\Repositories;

use App\Config\Database;
use PDO;

class AgendaRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function listarTodosComColaboradores(): array {
        $sql = "SELECT 
                    h.id_horario,
                    h.horario_inicio,
                    h.horario_fim,
                    a.id_user,
                    a.nome_user,
                    a.criado_em
                FROM horarios h
                LEFT JOIN agendamentos a ON h.id_horario = a.id_horario
                ORDER BY h.horario_inicio ASC, a.id_user ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function contarOcupantes(int $idHorario): int {
        $sql = "SELECT COUNT(*) FROM agendamentos WHERE id_horario = :id_horario";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_horario' => $idHorario]);

        return (int) $stmt->fetchColumn();
    }

    public function salvar(int $idHorario, string $nomeUser): bool {
        $sql = "INSERT INTO agendamentos (id_horario, nome_user) 
                VALUES (:id_horario, :nome_user)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id_horario' => $idHorario,
            'nome_user' => $nomeUser
        ]);
    }

    public function limparTodosAgendamentos(): bool {
        $result = $this->db->exec("DELETE FROM agendamentos");
        return $result !== false;
    }

    public function listarRelatorioExportacao(): array {
        $sql = "SELECT 
                    h.horario_inicio,
                    h.horario_fim,
                    a.nome_user,
                    a.criado_em
                FROM horarios h
                LEFT JOIN agendamentos a ON h.id_horario = a.id_horario
                ORDER BY h.horario_inicio ASC, a.nome_user ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obterBloqueioAgenda(): bool {
        $stmt = $this->db->prepare("SELECT is_bloqueada FROM agenda_config WHERE id_config = 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return (int)$val === 1;
    }

    public function setBloqueioAgenda(bool $bloqueada, ?int $adminId = null): bool {
        $val = $bloqueada ? 1 : 0;
        $driver = \App\Config\Env::get('DB_DRIVER', 'pgsql');
        if ($driver === 'pgsql') {
            $sql = "INSERT INTO agenda_config (id_config, is_bloqueada, bloqueada_por_user_id) 
                    VALUES (1, :val, :adminId) 
                    ON CONFLICT (id_config) DO UPDATE SET is_bloqueada = EXCLUDED.is_bloqueada, bloqueada_por_user_id = EXCLUDED.bloqueada_por_user_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'val' => $val,
                'adminId' => $adminId
            ]);
        } else {
            $sql = "INSERT INTO agenda_config (id_config, is_bloqueada, bloqueada_por_user_id) 
                    VALUES (1, :val, :adminId) 
                    ON DUPLICATE KEY UPDATE is_bloqueada = :val2, bloqueada_por_user_id = :adminId2";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'val' => $val,
                'adminId' => $adminId,
                'val2' => $val,
                'adminId2' => $adminId
            ]);
        }
    }

    public function cadastrarHorario(string $horarioInicio, string $horarioFim): bool {
        $sql = "INSERT INTO horarios (horario_inicio, horario_fim) VALUES (:inicio, :fim)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'inicio' => $horarioInicio,
            'fim' => $horarioFim
        ]);
    }

    public function atualizarHorario(int $idHorario, string $horarioInicio, string $horarioFim): bool {
        $sql = "UPDATE horarios SET horario_inicio = :inicio, horario_fim = :fim WHERE id_horario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'inicio' => $horarioInicio,
            'fim' => $horarioFim,
            'id' => $idHorario
        ]);
    }

    public function excluirHorario(int $idHorario): bool {
        $sql = "DELETE FROM horarios WHERE id_horario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $idHorario]);
    }
}


