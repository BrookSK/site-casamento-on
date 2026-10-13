<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model dos presentes (tabela gifts).
 */
final class Gift extends Model
{
    protected string $table = 'gifts';

    /**
     * Lista presentes ordenados.
     *
     * @param bool $onlyAvailable Se true, traz só os disponíveis (site público)
     * @return array<int, array<string, mixed>>
     */
    public function listAll(bool $onlyAvailable = false): array
    {
        $sql = 'SELECT * FROM ' . $this->table;
        if ($onlyAvailable) {
            $sql .= " WHERE status = 'available'";
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->select($sql);
    }

    /**
     * @param array{name:string, description:?string, price_cents:int, image:?string, status:string, sort_order:int} $data
     */
    public function createGift(array $data): int
    {
        $this->execute(
            'INSERT INTO ' . $this->table
            . ' (name, description, price_cents, image, status, sort_order) '
            . 'VALUES (:name, :description, :price_cents, :image, :status, :sort_order)',
            [
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price_cents' => $data['price_cents'],
                'image'       => $data['image'] ?? null,
                'status'      => $data['status'],
                'sort_order'  => $data['sort_order'],
            ]
        );
        return (int) $this->lastInsertId();
    }

    /**
     * @param array{name:string, description:?string, price_cents:int, image:?string, status:string, sort_order:int} $data
     */
    public function updateGift(int $id, array $data): int
    {
        return $this->execute(
            'UPDATE ' . $this->table . ' SET '
            . 'name = :name, description = :description, price_cents = :price_cents, '
            . 'image = :image, status = :status, sort_order = :sort_order '
            . 'WHERE id = :id',
            [
                'id'          => $id,
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price_cents' => $data['price_cents'],
                'image'       => $data['image'] ?? null,
                'status'      => $data['status'],
                'sort_order'  => $data['sort_order'],
            ]
        );
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM ' . $this->table . ' WHERE id = :id', ['id' => $id]);
    }

    public function countAll(): int
    {
        $row = $this->selectOne('SELECT COUNT(*) AS n FROM ' . $this->table);
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Busca um presente disponível pelo ID (usado no fluxo de presentear).
     *
     * @return array<string, mixed>|null
     */
    public function findAvailable(int $id): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE id = :id AND status = \'available\' LIMIT 1',
            ['id' => $id]
        );
    }
}
