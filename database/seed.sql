-- =============================================================
--  Site de Casamento — Dados de exemplo (SEED)
--  OPCIONAL: use para testar o banco com conteúdo de amostra.
--  Rode DEPOIS do schema.sql.
--
--  Linha de comando:
--    mysql -u USUARIO -p NOME_DO_BANCO < database/seed.sql
-- =============================================================

SET NAMES utf8mb4;

-- Configurações do casamento (equivalente ao que hoje está no config.php).
INSERT INTO `settings` (`key_name`, `value`) VALUES
    ('bride',         'Noiva'),
    ('groom',         'Noivo'),
    ('hashtag',       '#NossoCasamento'),
    ('date_iso',      '2027-01-01T16:00:00'),
    ('ceremony_place','Local a definir'),
    ('reception_place','Local a definir')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Presentes de exemplo (preços em centavos: 15000 = R$ 150,00).
INSERT INTO `gifts` (`name`, `description`, `price_cents`, `status`, `sort_order`) VALUES
    ('Jogo de panelas',   'Contribuição para a cozinha dos sonhos.', 25000, 'available', 1),
    ('Jantar romântico',  'Nos ajude a celebrar com um jantar especial.', 15000, 'available', 2),
    ('Lua de mel',        'Uma diária da nossa viagem.', 50000, 'available', 3),
    ('Cafeteira',         'Para as manhãs a dois.', 18000, 'available', 4);

-- Convidado de exemplo (sem senha; apenas para testar a estrutura).
INSERT INTO `guests` (`name`, `email`, `phone`) VALUES
    ('Convidado Exemplo', 'convidado@exemplo.com', '(11) 90000-0000');
