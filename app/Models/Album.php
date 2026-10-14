<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model dos álbuns da galeria (tabela albums).
 */
final class Album extends Model
{
    protected string $table = 'albums';

    /**
     * Lista os álbuns ordenados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        return $this->select(
            'SELECT * FROM ' . $this->table . ' ORDER BY sort_order ASC, id ASC'
        );
    }

    /**
     * Lista os álbuns com a contagem de fotos (para o painel admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWithCounts(): array
    {
        return $this->select(
            'SELECT a.*, COUNT(p.id) AS photo_count '
            . 'FROM ' . $this->table . ' a '
            . 'LEFT JOIN album_photos p ON p.album_id = a.id '
            . 'GROUP BY a.id '
            . 'ORDER BY a.sort_order ASC, a.id ASC'
        );
    }

    /**
     * @param array{title:string, subtitle:?string, description:?string, sort_order:int} $data
     */
    public function createAlbum(array $data): int
    {
        $this->execute(
            'INSERT INTO ' . $this->table . ' (title, subtitle, description, sort_order) '
            . 'VALUES (:title, :subtitle, :description, :sort_order)',
            [
                'title'       => $data['title'],
                'subtitle'    => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order'  => $data['sort_order'] ?? 0,
            ]
        );
        return (int) $this->lastInsertId();
    }

    /**
     * @param array{title:string, subtitle:?string, description:?string, sort_order:int} $data
     */
    public function updateAlbum(int $id, array $data): int
    {
        return $this->execute(
            'UPDATE ' . $this->table . ' SET '
            . 'title = :title, subtitle = :subtitle, description = :description, '
            . 'sort_order = :sort_order WHERE id = :id',
            [
                'id'          => $id,
                'title'       => $data['title'],
                'subtitle'    => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order'  => $data['sort_order'] ?? 0,
            ]
        );
    }

    public function delete(int $id): int
    {
        // As fotos são removidas em cascata pela FK (ON DELETE CASCADE).
        return $this->execute('DELETE FROM ' . $this->table . ' WHERE id = :id', ['id' => $id]);
    }

    /**
     * Aplica a ordem recebida (1º = topo). IDs ausentes são anexados ao fim na
     * ordem atual. Defensivo contra listas parciais ou IDs inexistentes.
     *
     * @param array<int, int> $orderedIds
     */
    public function reorder(array $orderedIds): bool
    {
        $valid = [];
        foreach ($this->select('SELECT id FROM ' . $this->table) as $r) {
            $valid[(int) $r['id']] = true;
        }

        $final = [];
        $seen  = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if (isset($valid[$id]) && !isset($seen[$id])) {
                $final[] = $id;
                $seen[$id] = true;
            }
        }
        foreach ($this->select('SELECT id FROM ' . $this->table . ' ORDER BY sort_order ASC, id ASC') as $r) {
            $id = (int) $r['id'];
            if (!isset($seen[$id])) {
                $final[] = $id;
                $seen[$id] = true;
            }
        }

        if ($final === []) {
            return false;
        }

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE ' . $this->table . ' SET sort_order = :o WHERE id = :id');
            $pos = 1;
            foreach ($final as $id) {
                $stmt->execute(['o' => $pos, 'id' => $id]);
                $pos++;
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
