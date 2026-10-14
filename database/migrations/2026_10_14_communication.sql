-- =============================================================
--  Migração: Comunicação automática (cadastro e acesso) — ponto 7
--
--  CONTEXTO: ao criar/assumir a conta do convidado durante a confirmação de
--  presença, o site passa a enviar automaticamente mensagens de boas-vindas
--  (e-mail e, opcionalmente, WhatsApp) com o endereço do site, o e-mail
--  cadastrado e um link SEGURO para definição/recuperação de senha.
--
--  Esta migração:
--    1) cria a tabela `communication_logs` (registro de envios e falhas);
--    2) adiciona em `guests` o token de RECUPERAÇÃO de senha + validade.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
--  Se uma coluna/tabela já existir, o comando correspondente falha e pode ser
--  ignorado.
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
--  communication_logs — histórico de mensagens automáticas
--  Registra CADA tentativa de envio (sucesso ou falha), por canal.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `communication_logs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Convidado destinatário (quando aplicável). Mantém histórico mesmo que
    -- o convidado seja removido depois (ON DELETE SET NULL).
    `guest_id`     INT UNSIGNED NULL,
    -- Canal da mensagem: 'email' | 'whatsapp'.
    `channel`      ENUM('email','whatsapp') NOT NULL,
    -- Tipo/assunto da comunicação: 'welcome' | 'password_reset' | 'rsvp' ...
    `type`         VARCHAR(40) NOT NULL,
    -- Destino real usado (e-mail ou telefone normalizado).
    `recipient`    VARCHAR(190) NULL,
    -- Resultado do envio.
    `status`       ENUM('sent','failed','skipped') NOT NULL,
    -- Mensagem de erro/observação (quando falhou ou foi ignorado).
    `error`        TEXT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_commlog_guest` (`guest_id`),
    KEY `idx_commlog_channel` (`channel`),
    KEY `idx_commlog_type` (`type`),
    KEY `idx_commlog_status` (`status`),
    CONSTRAINT `fk_commlog_guest`
        FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  guests — token de RECUPERAÇÃO de senha (distinto do invite_token)
--  O invite_token cobre a 1ª definição de senha; este cobre o "esqueci a
--  senha" de contas já ativas. Mantê-los separados evita invalidar um fluxo
--  ao usar o outro.
-- -------------------------------------------------------------
ALTER TABLE `guests`
    ADD COLUMN `password_reset_token` CHAR(32) NULL AFTER `invite_token_expires_at`,
    ADD COLUMN `password_reset_expires_at` TIMESTAMP NULL AFTER `password_reset_token`,
    ADD UNIQUE KEY `uq_guests_password_reset_token` (`password_reset_token`);
