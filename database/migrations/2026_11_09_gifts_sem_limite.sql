-- =============================================================
--  Migração: presentes sem limite de compras
--
--  O casal pediu para não mostrar "presente já escolhido": o mesmo presente
--  pode ser dado por várias pessoas (muita gente escolhe pelo valor).
--
--  - Acrescenta o status 'hidden' (oculto do site), que passa a ser a forma
--    de tirar um presente da lista quando ele já tem compras (e por isso não
--    pode ser excluído, para preservar o histórico financeiro).
--  - Volta para 'available' os presentes que estavam 'reserved'/'purchased'
--    (esses status não bloqueiam mais nada e deixam de aparecer no painel).
--
--  As compras (gift_purchases) NÃO são alteradas.
--  Rode no phpMyAdmin (aba SQL) ou Importar.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE `gifts`
    MODIFY `status` ENUM('available','reserved','purchased','hidden') NOT NULL DEFAULT 'available';

UPDATE `gifts` SET `status` = 'available' WHERE `status` IN ('reserved', 'purchased');
