<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model dos presentes (tabela gifts).
 */
final class Gift extends Model
{
    protected string $table = 'gifts';

    /** Status que tira o presente do site (definido no painel). */
    public const STATUS_HIDDEN = 'hidden';

    /**
     * Lista presentes ordenados.
     *
     * @param bool $onlyVisible Se true, omite os ocultos (site público)
     * @return array<int, array<string, mixed>>
     */
    public function listAll(bool $onlyVisible = false): array
    {
        $sql = 'SELECT * FROM ' . $this->table;
        if ($onlyVisible) {
            $sql .= " WHERE status <> '" . self::STATUS_HIDDEN . "'";
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->select($sql);
    }

    /**
     * Retorna a próxima posição livre (MAX(sort_order) + 1), para novos
     * presentes entrarem automaticamente no fim da fila.
     */
    public function nextSortOrder(): int
    {
        $row = $this->selectOne('SELECT MAX(sort_order) AS m FROM ' . $this->table);
        return (int) ($row['m'] ?? 0) + 1;
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
     * Insere vários presentes numa única transação. Reusa a mesma estrutura de
     * dados de createGift(). Se qualquer INSERT falhar, faz rollback de todos
     * (tudo ou nada) para não deixar a importação pela metade.
     *
     * @param array<int, array{name:string, description:?string, price_cents:int, image:?string, status:string, sort_order:int}> $gifts
     * @return int Quantidade de presentes efetivamente inseridos.
     */
    public function createManyGifts(array $gifts): int
    {
        if ($gifts === []) {
            return 0;
        }

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . $this->table
                . ' (name, description, price_cents, image, status, sort_order) '
                . 'VALUES (:name, :description, :price_cents, :image, :status, :sort_order)'
            );
            $count = 0;
            foreach ($gifts as $data) {
                $stmt->execute([
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price_cents' => $data['price_cents'],
                    'image'       => $data['image'] ?? null,
                    'status'      => $data['status'],
                    'sort_order'  => $data['sort_order'],
                ]);
                $count++;
            }
            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reordena os presentes conforme a lista de IDs informada, gravando
     * sort_order = 1, 2, 3, ... numa única transação. IDs inexistentes são
     * ignorados; presentes não citados mantêm-se após os citados, na ordem
     * atual (defensivo contra listas parciais).
     *
     * @param array<int, int> $orderedIds IDs na ordem desejada (1º = topo).
     * @return bool true se a ordem foi aplicada.
     */
    public function reorder(array $orderedIds): bool
    {
        // IDs válidos existentes no banco.
        $valid = [];
        foreach ($this->select('SELECT id FROM ' . $this->table) as $r) {
            $valid[(int) $r['id']] = true;
        }

        // Mantém só os IDs recebidos que existem, sem duplicar.
        $final = [];
        $seen  = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if (isset($valid[$id]) && !isset($seen[$id])) {
                $final[] = $id;
                $seen[$id] = true;
            }
        }
        // Anexa, ao fim, quaisquer presentes que não vieram na lista (ordem atual).
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

    /**
     * Garante que os sort_order formem uma sequência contígua 1..N seguindo a
     * ordem atual (sort_order ASC, id ASC). Idempotente: só grava quando algo
     * está fora de sequência. Usado ao abrir a lista do admin, para o número
     * exibido fazer sentido mesmo em bases antigas (vários "0").
     *
     * @return bool true se precisou normalizar.
     */
    public function normalizeOrder(): bool
    {
        $rows = $this->select('SELECT id, sort_order FROM ' . $this->table . ' ORDER BY sort_order ASC, id ASC');
        $needs = false;
        $pos = 1;
        foreach ($rows as $r) {
            if ((int) $r['sort_order'] !== $pos) {
                $needs = true;
                break;
            }
            $pos++;
        }
        if (!$needs) {
            return false;
        }

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE ' . $this->table . ' SET sort_order = :o WHERE id = :id');
            $pos = 1;
            foreach ($rows as $r) {
                $stmt->execute(['o' => $pos, 'id' => (int) $r['id']]);
                $pos++;
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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
     * Presente que pode ser comprado agora: existe e não está oculto. Não há
     * mais reserva nem "já comprado" — o mesmo presente pode ser dado quantas
     * vezes as pessoas quiserem (pedido do casal: muita gente escolhe pelo
     * valor).
     *
     * @return array<string, mixed>|null
     */
    public function findPurchasable(int $id): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE id = :id AND status <> :hidden LIMIT 1',
            ['id' => $id, 'hidden' => self::STATUS_HIDDEN]
        );
    }
}
