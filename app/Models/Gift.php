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

    /**
     * Reserva um presente de forma atômica: só muda para 'reserved' quando ele
     * ainda está 'available'. Retorna true se a reserva foi feita por ESTA
     * chamada — e false se outra transação já o havia reservado/comprado.
     *
     * O UPDATE condicional (status = 'available' no WHERE) garante exclusão
     * mútua mesmo com dois pagamentos simultâneos: apenas um afeta a linha,
     * impedindo compras duplicadas do mesmo item (ponto 9).
     */
    public function reserveIfAvailable(int $id): bool
    {
        $affected = $this->execute(
            'UPDATE ' . $this->table
            . " SET status = 'reserved' WHERE id = :id AND status = 'available'",
            ['id' => $id]
        );
        return $affected > 0;
    }

    /** Marca o presente como adquirido (pagamento confirmado). */
    public function markPurchased(int $id): int
    {
        return $this->execute(
            'UPDATE ' . $this->table . " SET status = 'purchased' WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Libera a reserva, voltando o presente a 'available'. Só atua quando ele
     * está 'reserved' — nunca "desfaz" um presente já 'purchased'. Usado quando
     * um pagamento pendente falha, expira ou é cancelado/estornado (ponto 9).
     */
    public function releaseIfReserved(int $id): int
    {
        return $this->execute(
            'UPDATE ' . $this->table
            . " SET status = 'available' WHERE id = :id AND status = 'reserved'",
            ['id' => $id]
        );
    }

    /**
     * Libera a reserva com segurança: só volta a 'available' quando o presente
     * está 'reserved' E não há nenhuma compra ainda ativa para ele (pendente
     * ou paga). Protege contra liberar um item que tenha outra tentativa de
     * pagamento legítima em andamento. Usado após expirar compras pendentes.
     */
    public function releaseIfNoActivePurchase(int $id): int
    {
        return $this->execute(
            'UPDATE ' . $this->table . ' g'
            . " SET g.status = 'available' "
            . " WHERE g.id = :id AND g.status = 'reserved' "
            . '   AND NOT EXISTS ('
            . '       SELECT 1 FROM gift_purchases p '
            . "       WHERE p.gift_id = g.id AND p.payment_status IN ('pending','paid')"
            . '   )',
            ['id' => $id]
        );
    }
}
