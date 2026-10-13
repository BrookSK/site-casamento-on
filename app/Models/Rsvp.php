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
     * Registra uma confirmação: cria o convidado, o RSVP e os acompanhantes
     * numa única transação.
     *
     * @param array{name:string, email:?string, phone:?string} $guest
     * @param array{status:string, companions:int, message:?string} $rsvp
     * @param array<int, string> $companionNames Nomes dos acompanhantes
     */
    public function createWithGuest(array $guest, array $rsvp, array $companionNames = []): int
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

            if ($companionNames !== []) {
                $stmt = $pdo->prepare(
                    'INSERT INTO rsvp_companions (rsvp_id, name) VALUES (:rid, :name)'
                );
                foreach ($companionNames as $cname) {
                    $cname = trim($cname);
                    if ($cname === '') {
                        continue;
                    }
                    $stmt->execute(['rid' => $rsvpId, 'name' => mb_substr($cname, 0, 150)]);
                }
            }

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
        // Agrega os nomes dos acompanhantes num campo só, com separador '||'.
        $sql = 'SELECT r.id, r.status, r.companions, r.message, r.responded_at, '
             . 'g.name, g.email, g.phone, '
             . "GROUP_CONCAT(c.name ORDER BY c.id SEPARATOR '||') AS companion_names "
             . 'FROM rsvps r '
             . 'JOIN guests g ON g.id = r.guest_id '
             . 'LEFT JOIN rsvp_companions c ON c.rsvp_id = r.id ';
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= 'WHERE r.status = :status ';
            $params['status'] = $status;
        }

        $sql .= 'GROUP BY r.id, r.status, r.companions, r.message, r.responded_at, g.name, g.email, g.phone ';
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

    /**
     * Remove uma confirmação E o cadastro do convidado associado.
     *
     * Apaga acompanhantes, confirmações e o próprio convidado numa transação,
     * de forma explícita (sem depender de ON DELETE CASCADE no banco). Assim o
     * e-mail/telefone deixa de estar em uso e pode ser reaproveitado num novo
     * cadastro/confirmação.
     *
     * Compras de presente (`gift_purchases`) são preservadas: o vínculo com o
     * convidado é apenas anulado, mantendo o histórico financeiro.
     *
     * @return int 1 se o convidado foi removido; 0 se a confirmação não existia.
     */
    public function delete(int $id): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            // Descobre o convidado dono desta confirmação.
            $stmt = $pdo->prepare('SELECT guest_id FROM rsvps WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $guestId = $stmt->fetchColumn();

            if ($guestId === false) {
                // Confirmação não existe mais; nada a remover.
                $pdo->commit();
                return 0;
            }
            $guestId = (int) $guestId;

            // Remoção explícita, na ordem correta — NÃO depende de a FK do banco
            // estar com ON DELETE CASCADE (varia conforme como o banco foi
            // criado). Assim o convidado é realmente apagado, liberando o
            // e-mail e o telefone (índices UNIQUE) para um novo cadastro.

            // 1) Acompanhantes de todas as confirmações do convidado.
            $pdo->prepare(
                'DELETE c FROM rsvp_companions c '
                . 'JOIN rsvps r ON r.id = c.rsvp_id '
                . 'WHERE r.guest_id = :gid'
            )->execute(['gid' => $guestId]);

            // 2) Confirmações do convidado.
            $pdo->prepare('DELETE FROM rsvps WHERE guest_id = :gid')
                ->execute(['gid' => $guestId]);

            // 3) Desvincula compras de presente (preserva o histórico financeiro).
            $pdo->prepare('UPDATE gift_purchases SET guest_id = NULL WHERE guest_id = :gid')
                ->execute(['gid' => $guestId]);

            // 4) O convidado em si — libera e-mail e telefone.
            $stmt = $pdo->prepare('DELETE FROM guests WHERE id = :gid');
            $stmt->execute(['gid' => $guestId]);
            $affected = $stmt->rowCount();

            $pdo->commit();
            return $affected;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
