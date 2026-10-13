-- =============================================================
--  Migração: limpeza de convidados órfãos
--
--  CONTEXTO: até agora, remover uma confirmação pela administração apagava
--  apenas a linha de `rsvps`, deixando o `guests` no banco. Como `email` e
--  `phone_normalized` têm índice ÚNICO, esses convidados órfãos impediam que o
--  mesmo e-mail/telefone fosse usado numa nova confirmação.
--
--  Esta migração remove os convidados que:
--    - NÃO possuem nenhuma confirmação (rsvps) associada, E
--    - NÃO criaram conta de acesso (password_hash é NULL),
--  liberando esses e-mails/telefones para reutilização.
--
--  Convidados com conta criada (password_hash preenchido) são PRESERVADOS,
--  mesmo sem confirmação, para não apagar logins ativos.
--
--  Compras de presente desses convidados têm o vínculo anulado (guest_id = NULL)
--  antes da remoção, preservando o histórico financeiro.
--
--  Rode no phpMyAdmin (aba SQL) ou Importar. Idempotente: pode rodar de novo.
-- =============================================================

SET NAMES utf8mb4;

-- 1) Desvincula compras de presente dos convidados órfãos (preserva histórico).
UPDATE `gift_purchases` gp
LEFT JOIN `rsvps` r ON r.`guest_id` = gp.`guest_id`
JOIN `guests` g ON g.`id` = gp.`guest_id`
SET gp.`guest_id` = NULL
WHERE r.`id` IS NULL
  AND g.`password_hash` IS NULL;

-- 2) Remove acompanhantes de confirmações que porventura tenham ficado soltas
--    (segurança: normalmente a FK em cascata já cuidaria disso).
DELETE c FROM `rsvp_companions` c
LEFT JOIN `rsvps` r ON r.`id` = c.`rsvp_id`
WHERE r.`id` IS NULL;

-- 3) Remove os convidados órfãos: sem confirmação e sem conta de acesso.
DELETE g FROM `guests` g
LEFT JOIN `rsvps` r ON r.`guest_id` = g.`id`
WHERE r.`id` IS NULL
  AND g.`password_hash` IS NULL;
