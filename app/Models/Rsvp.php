<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model das confirmações de presença (tabela rsvps + guests).
 *
 * Registra a resposta do convidado e expõe consultas para o painel.
 */
final class Rsvp extends Model
{
    protected string $table = 'rsvps';

    /**
     * Registra uma confirmação: cria o convidado e o RSVP numa transação.
     *
     * @param array{name:string, email:?string, phone:?string} $guest
     * @param array{status:string, companions:int, message:?string} $rsvp
     */
    public function createWithGuest(array $guest, array $rsvp): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO guests (name, email, phone) VALUES (:name, :email, :phone)'
            );
            $stmt->execute([
                'name'  => $guest['name'],
                'email' => $guest['email'] ?? null,
                'phone' => $guest['phone'] ?? null,
            ]);
            $guestId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO rsvps (guest_id, status, companions, message, responded_at) '
                . 'VALUES (:gid, :status, :companions, :message, NOW())'
            );
            $stmt->execute([
                'gid'        => $guestId,
                'status'     => $rsvp['status'],
                'companions' => $rsvp['companions'],
                'message'    => $rsvp['message'] ?? null,
            ]);
            $rsvpId = (int) $pdo->lastInsertId();

            $pdo->commit();
            return $rsvpId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lista as confirmações com os dados do convidado, para o painel.
     *
     * @param string|null $status Filtra por status (ou null = todos)
     * @return array<int, array<string, mixed>>
     */
    public function listWithGuests(?string $status = null): array
    {
        $sql = 'SELECT r.id, r.status, r.companions, r.message, r.responded_at, '
             . 'g.name, g.email, g.phone '
             . 'FROM rsvps r JOIN guests g ON g.id = r.guest_id ';
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= 'WHERE r.status = :status ';
            $params['status'] = $status;
        }

        $sql .= 'ORDER BY r.responded_at DESC, r.id DESC';
        return $this->select($sql, $params);
    }

    /**
     * Resumo para o dashboard: contagem por status e total de pessoas.
     *
     * @return array{total:int, confirmed:int, declined:int, pending:int, people:int}
     */
    public function summary(): array
    {
        $row = $this->selectOne(
            'SELECT '
            . 'COUNT(*) AS total, '
            . "SUM(status = 'confirmed') AS confirmed, "
            . "SUM(status = 'declined') AS declined, "
            . "SUM(status = 'pending') AS pending, "
            . "SUM(CASE WHEN status = 'confirmed' THEN companions + 1 ELSE 0 END) AS people "
            . 'FROM rsvps'
        ) ?? [];

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'declined'  => (int) ($row['declined'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'people'    => (int) ($row['people'] ?? 0),
        ];
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM rsvps WHERE id = :id', ['id' => $id]);
    }
}
