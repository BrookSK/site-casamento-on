-- =============================================================
--  Migração: contas de convidados (login por e-mail OU telefone)
--  Adiciona a coluna phone_normalized (só dígitos) à tabela guests,
--  com índice ÚNICO que ignora valores nulos — assim RSVPs antigos sem
--  conta não conflitam, e cada telefone de conta é único.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
-- =============================================================

SET NAMES utf8mb4;

-- Coluna com o telefone apenas em dígitos (ex.: 11988887777).
ALTER TABLE `guests`
    ADD COLUMN `phone_normalized` VARCHAR(20) NULL AFTER `phone`;

-- Índice único que permite múltiplos NULL (padrão do MySQL/InnoDB):
-- vários convidados podem ter phone_normalized NULL, mas valores
-- preenchidos devem ser únicos.
ALTER TABLE `guests`
    ADD UNIQUE KEY `uq_guests_phone_normalized` (`phone_normalized`);

-- Marca de quando o convidado criou/assumiu a conta (opcional, informativo).
ALTER TABLE `guests`
    ADD COLUMN `account_created_at` TIMESTAMP NULL AFTER `password_hash`;
