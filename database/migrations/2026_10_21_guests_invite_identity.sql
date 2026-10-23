-- =============================================================
--  Migração: identidade do convite (CPF público + convite individual)
--
--  Convite PÚBLICO (link único compartilhado): o convidado é identificado
--  pelo CPF informado no formulário de confirmação, armazenado apenas em
--  dígitos (cpf_normalized), com índice ÚNICO que ignora valores NULL — assim
--  cadastros antigos sem CPF não conflitam e cada CPF preenchido é único.
--
--  Convite INDIVIDUAL (link exclusivo por convidado): reaproveita a coluna
--  `invite_token` já existente como token do QR Code. A coluna `invite_type`
--  distingue os dois tipos de convite.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
-- =============================================================

SET NAMES utf8mb4;

-- Tipo do convite: 'public' (link único + CPF) ou 'individual' (link + QR).
-- Convidados já existentes ficam como 'public' (comportamento atual).
ALTER TABLE `guests`
    ADD COLUMN `invite_type` ENUM('public','individual') NOT NULL DEFAULT 'public' AFTER `name`;

-- CPF apenas em dígitos (ex.: 12345678901), usado para localizar o convidado
-- no convite público. Vários NULL são permitidos (padrão do MySQL/InnoDB);
-- valores preenchidos devem ser únicos.
ALTER TABLE `guests`
    ADD COLUMN `cpf_normalized` VARCHAR(11) NULL AFTER `phone_normalized`;

ALTER TABLE `guests`
    ADD UNIQUE KEY `uq_guests_cpf_normalized` (`cpf_normalized`);
