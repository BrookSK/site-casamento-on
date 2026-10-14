<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model do histórico de comunicações automáticas (tabela communication_logs).
 *
 * Registra cada tentativa de envio (e-mail/WhatsApp) com o resultado, para que
 * o administrador consiga auditar envios e falhas (ponto 7 — "Registrar envios
 * e falhas"). A gravação é deliberadamente tolerante a falhas: um erro ao
 * registrar o log NUNCA deve interromper o fluxo do convidado.
 */
final class CommunicationLog extends Model
{
    protected string $table = 'communication_logs';

    public const CHANNEL_EMAIL    = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Registra uma tentativa de comunicação. Tolerante a falhas.
     *
     * @param array{
     *   guest_id?: int|null,
     *   channel: string,
     *   type: string,
     *   recipient?: string|null,
     *   status: string,
     *   error?: string|null
     * } $data
     */
    public function record(array $data): void
    {
        try {
            $this->execute(
                'INSERT INTO ' . $this->table
                . ' (guest_id, channel, type, recipient, status, error) '
                . 'VALUES (:guest_id, :channel, :type, :recipient, :status, :error)',
                [
                    'guest_id'  => $data['guest_id'] ?? null,
                    'channel'   => (string) $data['channel'],
                    'type'      => (string) $data['type'],
                    'recipient' => $data['recipient'] ?? null,
                    'status'    => (string) $data['status'],
                    'error'     => isset($data['error']) ? mb_substr((string) $data['error'], 0, 2000) : null,
                ]
            );
        } catch (\Throwable $e) {
            // Não propaga: o log é acessório ao fluxo principal.
            error_log('[CommunicationLog] record: ' . $e->getMessage());
        }
    }

    /**
     * Lista os últimos registros, mais recentes primeiro, com o nome do
     * convidado quando houver.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        return $this->select(
            'SELECT c.*, g.name AS guest_name '
            . 'FROM ' . $this->table . ' c '
            . 'LEFT JOIN guests g ON g.id = c.guest_id '
            . 'ORDER BY c.id DESC LIMIT ' . $limit
        );
    }
}
