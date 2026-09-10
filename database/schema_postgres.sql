-- Script SQL para criação do Banco de Dados PostgreSQL do projeto Agenda-Almoço (braseqWeb)

-- Tabela de Horários
CREATE TABLE IF NOT EXISTS horarios (
  id_horario SERIAL PRIMARY KEY,
  horario_inicio TIME NOT NULL,
  horario_fim TIME NOT NULL
);

-- Tabela de Agendamentos (Almoço dos Colaboradores)
CREATE TABLE IF NOT EXISTS agendamentos (
  id_user SERIAL PRIMARY KEY,
  id_horario INT NOT NULL,
  nome_user VARCHAR(255) NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_agendamentos_horarios FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE
);

-- Tabela de Usuários (Com suporte a BCrypt e 2FA TOTP Criptografado com AES-256)
CREATE TABLE IF NOT EXISTS users (
  id_user SERIAL PRIMARY KEY,
  nome_user VARCHAR(100) NOT NULL UNIQUE,
  senha_user VARCHAR(255) NOT NULL,
  permissao_user VARCHAR(50) NOT NULL DEFAULT 'colaborador',
  secret_2fa VARCHAR(255) NULL,
  is_2fa_enabled SMALLINT DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Configurações Globais da Agenda (Com Suporte a Auditoria do Administrador)
CREATE TABLE IF NOT EXISTS agenda_config (
  id_config INT PRIMARY KEY DEFAULT 1,
  is_bloqueada SMALLINT NOT NULL DEFAULT 0,
  bloqueada_por_user_id INT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_agenda_config_user FOREIGN KEY (bloqueada_por_user_id) REFERENCES users(id_user) ON DELETE SET NULL
);

-- Inserção de Horários padrão caso a tabela esteja vazia
INSERT INTO horarios (horario_inicio, horario_fim)
SELECT '11:30:00', '11:45:00' WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE horario_inicio = '11:30:00');
INSERT INTO horarios (horario_inicio, horario_fim)
SELECT '11:45:00', '12:00:00' WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE horario_inicio = '11:45:00');
INSERT INTO horarios (horario_inicio, horario_fim)
SELECT '12:00:00', '12:15:00' WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE horario_inicio = '12:00:00');
INSERT INTO horarios (horario_inicio, horario_fim)
SELECT '12:15:00', '12:30:00' WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE horario_inicio = '12:15:00');
INSERT INTO horarios (horario_inicio, horario_fim)
SELECT '12:45:00', '13:00:00' WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE horario_inicio = '12:45:00');

-- Inserção do estado inicial de configuração da agenda
INSERT INTO agenda_config (id_config, is_bloqueada) VALUES (1, 0)
ON CONFLICT (id_config) DO NOTHING;
