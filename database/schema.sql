-- =============================================================
--  Site de Casamento — Esquema do banco de dados
--  SGBD: MySQL / MariaDB
--  Charset: utf8mb4 (acentos e emojis)  |  Engine: InnoDB
--
--  COMO EXECUTAR:
--   - Plesk: Bancos de dados > phpMyAdmin > aba "Importar" > selecione este arquivo.
--   - Linha de comando:
--       mysql -u USUARIO -p NOME_DO_BANCO < database/schema.sql
--
--  Observação: NÃO cria o banco em si (use o painel para criá-lo). Este script
--  cria apenas as TABELAS dentro do banco já selecionado.
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
--  settings — dados editáveis do casamento (chave/valor)
--  Substitui, no futuro, os valores hoje fixos em app/Config/config.php.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_name`    VARCHAR(100) NOT NULL,
    `value`       TEXT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  guests — convidados (base para cadastro/login e confirmação)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `guests` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150) NOT NULL,
    `email`          VARCHAR(190) NULL,
    `phone`          VARCHAR(30) NULL,
    -- Telefone só com dígitos (ex.: 11988887777), para login por telefone.
    `phone_normalized` VARCHAR(20) NULL,
    -- Senha para login (sempre via password_hash). Nula até criar conta.
    `password_hash`  VARCHAR(255) NULL,
    -- Quando o convidado criou/assumiu a conta.
    `account_created_at` TIMESTAMP NULL,
    -- Token para links de confirmação/convite e 1ª definição de senha.
    `invite_token`   CHAR(32) NULL,
    -- Validade do token acima (NULL = sem expiração definida).
    `invite_token_expires_at` TIMESTAMP NULL,
    -- Token de RECUPERAÇÃO de senha (fluxo "esqueci minha senha").
    `password_reset_token`     CHAR(32) NULL,
    `password_reset_expires_at` TIMESTAMP NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_guests_email` (`email`),
    UNIQUE KEY `uq_guests_phone_normalized` (`phone_normalized`),
    UNIQUE KEY `uq_guests_invite_token` (`invite_token`),
    UNIQUE KEY `uq_guests_password_reset_token` (`password_reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  rsvps — confirmações de presença
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rsvps` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guest_id`      INT UNSIGNED NOT NULL,
    -- Status da resposta do convidado.
    `status`        ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending',
    -- Número de acompanhantes (além do próprio convidado).
    `companions`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `message`       TEXT NULL,
    `responded_at`  TIMESTAMP NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rsvps_guest` (`guest_id`),
    KEY `idx_rsvps_status` (`status`),
    CONSTRAINT `fk_rsvps_guest`
        FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  rsvp_companions — nomes dos acompanhantes de cada confirmação
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rsvp_companions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rsvp_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_companions_rsvp` (`rsvp_id`),
    CONSTRAINT `fk_companions_rsvp`
        FOREIGN KEY (`rsvp_id`) REFERENCES `rsvps` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  gifts — itens da lista de presentes
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gifts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150) NOT NULL,
    `description`  TEXT NULL,
    -- Preço em centavos evita problemas de arredondamento (ex.: 15000 = R$ 150,00).
    `price_cents`  INT UNSIGNED NOT NULL DEFAULT 0,
    `image`        VARCHAR(255) NULL,
    `status`       ENUM('available','reserved','purchased') NOT NULL DEFAULT 'available',
    `sort_order`   INT NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gifts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  gift_purchases — quem escolheu/comprou cada presente
--  Estrutura pronta para pagamentos futuros (SEM integração real agora).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gift_purchases` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gift_id`         INT UNSIGNED NOT NULL,
    -- Pode vincular a um convidado cadastrado (opcional).
    `guest_id`        INT UNSIGNED NULL,
    -- Dados de quem presenteou (caso não seja um convidado cadastrado).
    `buyer_name`      VARCHAR(150) NULL,
    `buyer_email`     VARCHAR(190) NULL,
    `amount_cents`    INT UNSIGNED NOT NULL DEFAULT 0,
    -- Snapshot do preço do presente no momento da compra (preserva histórico).
    `gift_price_cents` INT UNSIGNED NOT NULL DEFAULT 0,
    -- Taxas do gateway e valor líquido recebido (em centavos).
    `fee_cents`       INT UNSIGNED NOT NULL DEFAULT 0,
    `net_cents`       INT UNSIGNED NOT NULL DEFAULT 0,
    -- Datas do ciclo de vida da cobrança.
    `paid_at`         TIMESTAMP NULL,
    `confirmed_at`    TIMESTAMP NULL,
    `settled_at`      TIMESTAMP NULL,
    `refunded_at`     TIMESTAMP NULL,
    `payment_status`  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    -- Status financeiro granular (pending/confirmed/received/refused/canceled/refunded).
    `finance_status`  VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- Referência externa do gateway (preenchida no futuro).
    `payment_ref`     VARCHAR(190) NULL,
    `message`         TEXT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_purchases_gift` (`gift_id`),
    KEY `idx_purchases_guest` (`guest_id`),
    KEY `idx_purchases_status` (`payment_status`),
    CONSTRAINT `fk_purchases_gift`
        FOREIGN KEY (`gift_id`) REFERENCES `gifts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_purchases_guest`
        FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  gift_purchase_status_log — histórico de mudanças de status (ponto 11)
--  Trilha de auditoria: cada transição de status de uma compra.
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

-- -------------------------------------------------------------
--  communication_logs — histórico das mensagens automáticas (ponto 7)
--  Registra cada tentativa de envio (sucesso/falha), por canal.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `communication_logs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guest_id`     INT UNSIGNED NULL,
    `channel`      ENUM('email','whatsapp') NOT NULL,
    `type`         VARCHAR(40) NOT NULL,
    `recipient`    VARCHAR(190) NULL,
    `status`       ENUM('sent','failed','skipped') NOT NULL,
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
--  admins — usuários do painel administrativo (futuro)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150) NOT NULL,
    `email`          VARCHAR(190) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
