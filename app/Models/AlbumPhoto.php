<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model das fotos dos álbuns (tabela album_photos).
 */
final class AlbumPhoto extends Model
{
    protected string $table = 'album_photos';

    /**
     * Fotos de um álbum, ordenadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByAlbum(int $albumId): array
    {
        return $this->select(
            'SELECT * FROM ' . $this->table
            . ' WHERE album_id = :a ORDER BY sort_order ASC, id ASC',
            ['a' => $albumId]
        );
    }

    /**
     * Todas as fotos de vários álbuns, agrupadas por album_id (para a galeria
     * pública montar tudo numa consulta só).
     *
     * @param array<int, int> $albumIds
     * @return array<int, array<int, array<string, mixed>>> mapa album_id => fotos
     */
    public function listForAlbums(array $albumIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $albumIds)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->select(
            'SELECT * FROM ' . $this->table
            . ' WHERE album_id IN (' . $in . ') ORDER BY sort_order ASC, id ASC',
            $ids
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int) $r['album_id']][] = $r;
        }
        return $grouped;
    }

    /** Adiciona uma foto a um álbum (no fim da ordem). */
    public function add(int $albumId, string $image, ?string $caption = null): int
    {
        // Próxima posição de ordenação.
        $row = $this->selectOne(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next FROM ' . $this->table
            . ' WHERE album_id = :a',
            ['a' => $albumId]
        );
        $next = (int) ($row['next'] ?? 1);

        $this->execute(
            'INSERT INTO ' . $this->table . ' (album_id, image, caption, sort_order) '
            . 'VALUES (:a, :img, :cap, :ord)',
            ['a' => $albumId, 'img' => $image, 'cap' => $caption, 'ord' => $next]
        );
        return (int) $this->lastInsertId();
    }

    /** Busca uma foto pelo id. @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM ' . $this->table . ' WHERE id = :id', ['id' => $id]);
    }
}
