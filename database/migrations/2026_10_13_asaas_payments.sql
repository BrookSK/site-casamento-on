-- =============================================================
--  Migração: integração de pagamentos ASAAS (PIX / cartão / boleto)
--
--  - Acrescenta colunas em gift_purchases para rastrear a cobrança no ASAAS
--    (id do cliente, id da cobrança, forma de pagamento, URL da fatura e o
--     copia-e-cola do PIX).
--  - Cria a tabela asaas_webhook_events para idempotência (o ASAAS pode
--    reenviar o mesmo evento; guardamos o id para não processar duas vezes).
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
--  Pré-requisito: a tabela gift_purchases já existe (database/schema.sql).
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
--  gift_purchases — colunas da integração ASAAS
-- -------------------------------------------------------------

-- ID do cliente no ASAAS (cus_...). Reutilizado em novas cobranças.
ALTER TABLE `gift_purchases`
    ADD COLUMN `asaas_customer_id` VARCHAR(60) NULL AFTER `payment_ref`;

-- Forma de pagamento escolhida pelo presenteador.
ALTER TABLE `gift_purchases`
    ADD COLUMN `billing_type` ENUM('PIX','CREDIT_CARD','BOLETO','UNDEFINED')
        NOT NULL DEFAULT 'UNDEFINED' AFTER `asaas_customer_id`;

-- URL da fatura hospedada pelo ASAAS (usada p/ cartão e boleto).
ALTER TABLE `gift_purchases`
    ADD COLUMN `invoice_url` VARCHAR(255) NULL AFTER `billing_type`;

-- Linha digitável / URL do boleto (quando billingType = BOLETO).
ALTER TABLE `gift_purchases`
    ADD COLUMN `bank_slip_url` VARCHAR(255) NULL AFTER `invoice_url`;

-- Código "copia e cola" do PIX (payload) e imagem do QR Code (base64).
ALTER TABLE `gift_purchases`
    ADD COLUMN `pix_payload` TEXT NULL AFTER `bank_slip_url`;
ALTER TABLE `gift_purchases`
    ADD COLUMN `pix_qr_base64` MEDIUMTEXT NULL AFTER `pix_payload`;

-- Índice para localizar rapidamente a compra pelo id da cobrança (webhook).
ALTER TABLE `gift_purchases`
    ADD KEY `idx_purchases_payment_ref` (`payment_ref`);

-- -------------------------------------------------------------
--  asaas_webhook_events — idempotência dos webhooks do ASAAS
--  Guarda o id do evento (evt_...) já processado. Entrega "at least once":
--  o mesmo evento pode chegar mais de uma vez — ignoramos duplicados.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asaas_webhook_events` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id`     VARCHAR(60) NOT NULL,
    `event_type`   VARCHAR(60) NOT NULL,
    `payment_id`   VARCHAR(60) NULL,
    `received_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_asaas_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
