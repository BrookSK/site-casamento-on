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
     * Registra uma confirmação numa única transação.
     *
     * Diferente da versão antiga (que sempre inseria um convidado novo e
     * quebrava quando o e-mail/telefone já existia), agora o convidado é
     * REUTILIZADO quando já há cadastro com o mesmo e-mail ou telefone. Isso
     * permite que a confirmação crie/assuma a CONTA do convidado (ponto 6):
     *
     *   - Convidado novo: cria em `guests` já com um `invite_token` (base para
     *     o link seguro de definição de senha) e `account_created_at`.
     *   - Convidado existente SEM senha: atualiza nome/contatos e garante um
     *     `invite_token`.
     *   - Convidado existente COM senha (conta ativa): preserva a conta; apenas
     *     completa dados de contato que estejam faltando. Não gera token novo.
     *
     * @param array{name:string, email:?string, phone:?string} $guest
     * @param array{status:string, companions:int, message:?string} $rsvp
     * @param array<int, string> $companionNames Nomes dos acompanhantes
     *
     * @return array{
     *   rsvp_id:int,
     *   guest:array{id:int,name:string,email:string},
     *   invite_token:?string,
     *   has_password:bool
     * }
     */
    public function createWithGuest(array $guest, array $rsvp, array $companionNames = []): array
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $name       = (string) $guest['name'];
            $email      = $guest['email'] ?? null;
            $phone      = $guest['phone'] ?? null;
            $phoneNorm  = $phone !== null ? Guest::normalizePhone((string) $phone) : '';
            $phoneNorm  = $phoneNorm !== '' ? $phoneNorm : null;

            // Procura um convidado existente por e-mail OU por telefone.
            $existing = $this->findGuestForRsvp($pdo, $email, $phoneNorm);

            $inviteToken = null;
            $hasPassword = false;

            if ($existing === null) {
                // --- Convidado novo: cria já com token de convite (7 dias). ---
                $inviteToken = Guest::generateToken();
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO guests (name, email, phone, phone_normalized, invite_token, invite_token_expires_at, account_created_at) '
                        . 'VALUES (:name, :email, :phone, :pnorm, :token, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())'
                    );
                    $stmt->execute([
                        'name'  => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'pnorm' => $phoneNorm,
                        'token' => $inviteToken,
                    ]);
                    $guestId    = (int) $pdo->lastInsertId();
                    $finalName  = $name;
                    $finalEmail = (string) ($email ?? '');
                } catch (\PDOException $e) {
                    // Corrida: outro request inseriu o mesmo e-mail/telefone
                    // entre o SELECT e o INSERT. Rebusca e reutiliza.
                    $existing = $this->findGuestForRsvp($pdo, $email, $phoneNorm);
                    if ($existing === null) {
                        throw $e; // não foi violação de unicidade: propaga
                    }
                    $inviteToken = null;
                    $guestId     = (int) $existing['id'];
                    $hasPassword = !empty($existing['password_hash']);
                    $finalName   = $name;
                    $finalEmail  = (string) ($existing['email'] ?? ($email ?? ''));
                }
            } else {
                // --- Convidado já existe: reutiliza, sem duplicar. ---
                $guestId     = (int) $existing['id'];
                $hasPassword = !empty($existing['password_hash']);

                // Completa dados que faltam, sem sobrescrever o que já existe.
                $newEmail = $existing['email'] ?: $email;
                $newPhone = $existing['phone'] ?: $phone;
                $newPnorm = $existing['phone_normalized'] ?: $phoneNorm;

                if ($hasPassword) {
                    // Conta ativa: não gera token; só atualiza nome/contatos.
                    $pdo->prepare(
                        'UPDATE guests SET name = :name, email = :email, '
                        . 'phone = :phone, phone_normalized = :pnorm WHERE id = :id'
                    )->execute([
                        'id'    => $guestId,
                        'name'  => $name,
                        'email' => $newEmail,
                        'phone' => $newPhone,
                        'pnorm' => $newPnorm,
                    ]);
                } else {
                    // Sem senha: gera um token novo (7 dias) a cada confirmação,
                    // garantindo que o link enviado agora esteja válido.
                    $inviteToken = Guest::generateToken();
                    $pdo->prepare(
                        'UPDATE guests SET name = :name, email = :email, '
                        . 'phone = :phone, phone_normalized = :pnorm, '
                        . 'invite_token = :token, '
                        . 'invite_token_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), '
                        . 'account_created_at = COALESCE(account_created_at, NOW()) '
                        . 'WHERE id = :id'
                    )->execute([
                        'id'    => $guestId,
                        'name'  => $name,
                        'email' => $newEmail,
                        'phone' => $newPhone,
                        'pnorm' => $newPnorm,
                        'token' => $inviteToken,
                    ]);
                }

                $finalName  = $name;
                $finalEmail = (string) ($newEmail ?? '');
            }

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

            return [
                'rsvp_id'      => $rsvpId,
                'guest'        => ['id' => $guestId, 'name' => $finalName, 'email' => $finalEmail],
                'invite_token' => $inviteToken,
                'has_password' => $hasPassword,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Procura um convidado por e-mail ou telefone (dentro da transação).
     *
     * @return array<string,mixed>|null
     */
    private function findGuestForRsvp(\PDO $pdo, ?string $email, ?string $phoneNorm): ?array
    {
        if ($email !== null && $email !== '') {
            $stmt = $pdo->prepare('SELECT * FROM guests WHERE email = :e LIMIT 1');
            $stmt->execute(['e' => $email]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
        if ($phoneNorm !== null && $phoneNorm !== '') {
            $stmt = $pdo->prepare('SELECT * FROM guests WHERE phone_normalized = :p LIMIT 1');
            $stmt->execute(['p' => $phoneNorm]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
        return null;
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
