-- =============================================================
--  Migração: tabela de acompanhantes das confirmações
--  Adiciona a tabela rsvp_companions. NÃO altera tabelas existentes.
--
--  COMO EXECUTAR (produção, Plesk):
--   Bancos de dados > site-casamento-on > phpMyAdmin > aba SQL
--   Cole o conteúdo abaixo e execute. (Ou use "Importar" com este arquivo.)
-- =============================================================

SET NAMES utf8mb4;

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
