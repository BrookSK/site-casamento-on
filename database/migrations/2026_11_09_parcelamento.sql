-- =============================================================
--  Migração: parcelamento no cartão de crédito
--
--  Quem presenteia pode parcelar no cartão; a taxa do parcelamento é somada
--  ao valor cobrado, para o casal receber o valor cheio do presente.
--
--  - installment_count: em quantas vezes foi parcelado (1 = à vista).
--  - installment_ref:   id do parcelamento no ASAAS. Cada parcela vira uma
--    cobrança com id próprio; este campo liga os avisos (webhook) de todas
--    as parcelas à mesma compra.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
--  Instalação nova a partir do schema.sql atual já tem as colunas: o MySQL
--  acusará "Duplicate column" e pode ser ignorado.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE `gift_purchases`
    ADD COLUMN `installment_count` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `billing_type`;

ALTER TABLE `gift_purchases`
    ADD COLUMN `installment_ref` VARCHAR(60) NULL AFTER `installment_count`;

ALTER TABLE `gift_purchases`
    ADD KEY `idx_purchases_installment_ref` (`installment_ref`);
