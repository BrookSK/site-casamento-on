-- =============================================================
--  Migração: galeria de fotos gerenciável (álbuns + fotos)
--
--  Substitui a galeria por convenção de arquivo por uma gerenciável no painel.
--  - albums: cada álbum tem título, subtítulo e descrição (opcionais) e ordem.
--  - album_photos: fotos de cada álbum (caminho relativo a assets/img) e ordem.
--
--  NÃO altera dados existentes. Rode no phpMyAdmin (aba SQL) ou Importar.
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `albums` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(150) NOT NULL,
    `subtitle`    VARCHAR(190) NULL,
    `description` TEXT NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_albums_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `album_photos` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `album_id`   INT UNSIGNED NOT NULL,
    -- Caminho relativo a public/assets/img (ex.: "albums/abc123.jpg").
    `image`      VARCHAR(255) NOT NULL,
    `caption`    VARCHAR(190) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_album_photos_album` (`album_id`),
    KEY `idx_album_photos_order` (`sort_order`),
    CONSTRAINT `fk_album_photos_album`
        FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
