<?php

declare(strict_types=1);

namespace App\Models;

use PDOException;

/**
 * Registro de eventos de webhook do ASAAS, para idempotência.
 *
 * O ASAAS entrega eventos em modelo "at least once": o mesmo evento pode
 * chegar mais de uma vez. Guardamos o id (evt_...) e só processamos a regra de
 * negócio na primeira vez.
 */
final class AsaasWebhookEvent extends Model
{
    protected string $table = 'asaas_webhook_events';

    /**
     * Tenta registrar o evento. Retorna true se é a PRIMEIRA vez (deve
     * processar) ou false se já foi recebido antes (ignorar).
     */
    public function registerIfNew(string $eventId, string $eventType, ?string $paymentId): bool
    {
        if ($eventId === '') {
            // Sem id não há como garantir idempotência; processa mesmo assim.
            return true;
        }

        try {
            $this->execute(
                'INSERT INTO ' . $this->table . ' (event_id, event_type, payment_id) '
                . 'VALUES (:e, :t, :p)',
                ['e' => $eventId, 't' => $eventType, 'p' => $paymentId]
            );
            return true;
        } catch (PDOException $e) {
            // 23000 / 1062 = chave única duplicada => evento já processado.
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }
}
