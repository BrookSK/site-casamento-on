-- =============================================================
--  Migração: validade do token de convite / definição de senha
--
--  CONTEXTO: após confirmar presença, o convidado recebe (por e-mail/WhatsApp)
--  um link seguro para DEFINIR a senha de acesso. Esse link usa o
--  `invite_token` já existente em `guests`. Para que o link não seja válido
--  para sempre, adicionamos a coluna de expiração.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
--  Idempotente na prática: se a coluna já existir, o ALTER falha e pode ser
--  ignorado.
-- =============================================================

SET NAMES utf8mb4;

-- Quando o token de convite/definição de senha deixa de ser válido.
ALTER TABLE `guests`
    ADD COLUMN `invite_token_expires_at` TIMESTAMP NULL AFTER `invite_token`;
