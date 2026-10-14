-- =============================================================
--  Migração: gestão financeira dos presentes (ponto 11)
--
--  Acrescenta a gift_purchases os campos para rastreabilidade financeira
--  completa: valor original do presente, taxas, valor líquido, datas de
--  pagamento/confirmação/liquidação, status granular e marca de estorno.
--
--  Cria também gift_purchase_status_log para o HISTÓRICO de mudanças de
--  status (quem/quando/para qual status), preservando a trilha de auditoria.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
--  Observação: as colunas de ADD são idempotentes só na intenção — se já
--  existirem, o MySQL acusa "Duplicate column" e pode ser ignorado.
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
--  gift_purchases — campos financeiros
-- -------------------------------------------------------------

-- Valor original do presente no momento da compra (snapshot, em centavos).
-- Preserva o histórico mesmo que o preço do presente mude depois.
ALTER TABLE `gift_purchases`
    ADD COLUMN `gift_price_cents` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `amount_cents`;

-- Taxas cobradas pelo gateway (em centavos) e valor líquido recebido.
ALTER TABLE `gift_purchases`
    ADD COLUMN `fee_cents` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `gift_price_cents`;
ALTER TABLE `gift_purchases`
    ADD COLUMN `net_cents` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `fee_cents`;

-- Datas do ciclo de vida da cobrança.
ALTER TABLE `gift_purchases`
    ADD COLUMN `paid_at` TIMESTAMP NULL AFTER `net_cents`;
ALTER TABLE `gift_purchases`
    ADD COLUMN `confirmed_at` TIMESTAMP NULL AFTER `paid_at`;
ALTER TABLE `gift_purchases`
    ADD COLUMN `settled_at` TIMESTAMP NULL AFTER `confirmed_at`;
ALTER TABLE `gift_purchases`
    ADD COLUMN `refunded_at` TIMESTAMP NULL AFTER `settled_at`;

-- Status financeiro granular, complementar ao payment_status existente.
-- (pending / confirmed / received / refused / canceled / refunded)
ALTER TABLE `gift_purchases`
    ADD COLUMN `finance_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `payment_status`;

-- -------------------------------------------------------------
--  gift_purchase_status_log — histórico de mudanças de status
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gift_purchase_status_log` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id`  INT UNSIGNED NOT NULL,
    `from_status`  VARCHAR(20) NULL,
    `to_status`    VARCHAR(20) NOT NULL,
    `note`         VARCHAR(255) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_log_purchase` (`purchase_id`),
    CONSTRAINT `fk_status_log_purchase`
        FOREIGN KEY (`purchase_id`) REFERENCES `gift_purchases` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
