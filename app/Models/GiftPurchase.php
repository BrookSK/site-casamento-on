<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model das compras/contribuições de presentes (tabela gift_purchases).
 *
 * Cada linha representa uma tentativa de presentear um item, com o status do
 * pagamento no ASAAS (pending/paid/failed/refunded) e os dados da cobrança
 * (id da cobrança, forma de pagamento, QR Code do PIX, URL da fatura, etc.).
 */
final class GiftPurchase extends Model
{
    protected string $table = 'gift_purchases';

    /**
     * Cria uma compra pendente e retorna o ID interno.
     *
     * @param array{
     *   gift_id:int,
     *   guest_id:?int,
     *   buyer_name:?string,
     *   buyer_email:?string,
     *   amount_cents:int,
     *   payment_ref:?string,
     *   asaas_customer_id:?string,
     *   billing_type:string,
     *   invoice_url:?string,
     *   bank_slip_url:?string,
     *   pix_payload:?string,
     *   pix_qr_base64:?string,
     *   message:?string
     * } $data
     */
    public function createPending(array $data): int
    {
        $this->execute(
            'INSERT INTO ' . $this->table . ' ('
            . 'gift_id, guest_id, buyer_name, buyer_email, amount_cents, '
            . 'payment_status, payment_ref, asaas_customer_id, billing_type, '
            . 'invoice_url, bank_slip_url, pix_payload, pix_qr_base64, message'
            . ') VALUES ('
            . ':gift_id, :guest_id, :buyer_name, :buyer_email, :amount_cents, '
            . '\'pending\', :payment_ref, :asaas_customer_id, :billing_type, '
            . ':invoice_url, :bank_slip_url, :pix_payload, :pix_qr_base64, :message)',
            [
                'gift_id'           => $data['gift_id'],
                'guest_id'          => $data['guest_id'] ?? null,
                'buyer_name'        => $data['buyer_name'] ?? null,
                'buyer_email'       => $data['buyer_email'] ?? null,
                'amount_cents'      => $data['amount_cents'],
                'payment_ref'       => $data['payment_ref'] ?? null,
                'asaas_customer_id' => $data['asaas_customer_id'] ?? null,
                'billing_type'      => $data['billing_type'],
                'invoice_url'       => $data['invoice_url'] ?? null,
                'bank_slip_url'     => $data['bank_slip_url'] ?? null,
                'pix_payload'       => $data['pix_payload'] ?? null,
                'pix_qr_base64'     => $data['pix_qr_base64'] ?? null,
                'message'           => $data['message'] ?? null,
            ]
        );
        return (int) $this->lastInsertId();
    }

    /** Busca uma compra pela referência da cobrança no ASAAS (pay_...). */
    public function findByPaymentRef(string $ref): ?array
    {
        if ($ref === '') {
            return null;
        }
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE payment_ref = :r LIMIT 1',
            ['r' => $ref]
        );
    }

    /** Atualiza o status do pagamento (chamado pelo webhook). */
    public function updateStatus(int $id, string $status): int
    {
        $allowed = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        return $this->execute(
            'UPDATE ' . $this->table . ' SET payment_status = :s WHERE id = :id',
            ['s' => $status, 'id' => $id]
        );
    }

    /**
     * Lista as compras com o nome do presente (para o painel admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWithGift(): array
    {
        return $this->select(
            'SELECT p.*, g.name AS gift_name '
            . 'FROM ' . $this->table . ' p '
            . 'LEFT JOIN gifts g ON g.id = p.gift_id '
            . 'ORDER BY p.id DESC'
        );
    }

    /**
     * Conta as compras de um presente que precisam ser preservadas
     * (qualquer uma que não seja uma tentativa falha). Usado para impedir a
     * exclusão de um presente com histórico financeiro relevante.
     */
    public function countRelevantByGift(int $giftId): int
    {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS n FROM ' . $this->table
            . " WHERE gift_id = :g AND payment_status <> 'failed'",
            ['g' => $giftId]
        );
        return (int) ($row['n'] ?? 0);
    }

    // ================================================================
    //  Gestão financeira (ponto 11)
    // ================================================================

    /**
     * Lista transações financeiras com dados do presente e do convidado,
     * aplicando filtros opcionais. Ordenada da mais recente para a mais antiga.
     *
     * @param array{
     *   status?:string, gift_id?:int, guest?:string,
     *   date_from?:string, date_to?:string
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function listFinance(array $filters = []): array
    {
        [$where, $params] = $this->buildFinanceWhere($filters);

        $sql = 'SELECT p.*, g.name AS gift_name, gu.name AS guest_name '
             . 'FROM ' . $this->table . ' p '
             . 'LEFT JOIN gifts g ON g.id = p.gift_id '
             . 'LEFT JOIN guests gu ON gu.id = p.guest_id '
             . $where
             . ' ORDER BY p.created_at DESC, p.id DESC';

        return $this->select($sql, $params);
    }

    /**
     * Detalhe de uma transação (com nome do presente e do convidado).
     *
     * @return array<string, mixed>|null
     */
    public function findFinance(int $id): ?array
    {
        return $this->selectOne(
            'SELECT p.*, g.name AS gift_name, gu.name AS guest_name '
            . 'FROM ' . $this->table . ' p '
            . 'LEFT JOIN gifts g ON g.id = p.gift_id '
            . 'LEFT JOIN guests gu ON gu.id = p.guest_id '
            . 'WHERE p.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Histórico de mudanças de status de uma transação.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statusHistory(int $purchaseId): array
    {
        return $this->select(
            'SELECT * FROM gift_purchase_status_log '
            . 'WHERE purchase_id = :id ORDER BY id ASC',
            ['id' => $purchaseId]
        );
    }

    /** Registra uma transição de status no histórico (trilha de auditoria). */
    public function logStatus(int $purchaseId, ?string $from, string $to, ?string $note = null): void
    {
        $this->execute(
            'INSERT INTO gift_purchase_status_log (purchase_id, from_status, to_status, note) '
            . 'VALUES (:p, :f, :t, :n)',
            ['p' => $purchaseId, 'f' => $from, 't' => $to, 'n' => $note]
        );
    }

    /**
     * Agregados para o dashboard financeiro, respeitando os mesmos filtros.
     *
     * Convenções (centavos):
     *   - "vendido": soma do valor de todas as transações não falhas/canceladas;
     *   - "aprovado": pagas (paid);
     *   - "recebido/líquido": net_cents das pagas;
     *   - "taxas": fee_cents das pagas;
     *   - "pendente": pending;
     *   - "estorno/reembolso": refunded.
     *
     * @param array{status?:string, gift_id?:int, guest?:string, date_from?:string, date_to?:string} $filters
     * @return array<string, int>
     */
    public function financeSummary(array $filters = []): array
    {
        [$where, $params] = $this->buildFinanceWhere($filters);

        $row = $this->selectOne(
            'SELECT '
            . "COUNT(*) AS tx_total, "
            . "SUM(p.payment_status = 'paid') AS tx_paid, "
            . "SUM(p.payment_status = 'pending') AS tx_pending, "
            . "SUM(p.payment_status = 'failed') AS tx_failed, "
            . "SUM(p.payment_status = 'refunded') AS tx_refunded, "
            // Valores (em centavos)
            . "COALESCE(SUM(CASE WHEN p.payment_status NOT IN ('failed') THEN p.amount_cents ELSE 0 END), 0) AS sold_cents, "
            . "COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount_cents ELSE 0 END), 0) AS approved_cents, "
            . "COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.net_cents ELSE 0 END), 0) AS net_cents, "
            . "COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.fee_cents ELSE 0 END), 0) AS fee_cents, "
            . "COALESCE(SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount_cents ELSE 0 END), 0) AS pending_cents, "
            . "COALESCE(SUM(CASE WHEN p.payment_status = 'refunded' THEN p.amount_cents ELSE 0 END), 0) AS refunded_cents "
            . 'FROM ' . $this->table . ' p '
            . 'LEFT JOIN gifts g ON g.id = p.gift_id '
            . 'LEFT JOIN guests gu ON gu.id = p.guest_id '
            . $where,
            $params
        ) ?? [];

        return [
            'tx_total'       => (int) ($row['tx_total'] ?? 0),
            'tx_paid'        => (int) ($row['tx_paid'] ?? 0),
            'tx_pending'     => (int) ($row['tx_pending'] ?? 0),
            'tx_failed'      => (int) ($row['tx_failed'] ?? 0),
            'tx_refunded'    => (int) ($row['tx_refunded'] ?? 0),
            'sold_cents'     => (int) ($row['sold_cents'] ?? 0),
            'approved_cents' => (int) ($row['approved_cents'] ?? 0),
            'net_cents'      => (int) ($row['net_cents'] ?? 0),
            'fee_cents'      => (int) ($row['fee_cents'] ?? 0),
            'pending_cents'  => (int) ($row['pending_cents'] ?? 0),
            'refunded_cents' => (int) ($row['refunded_cents'] ?? 0),
        ];
    }

    /**
     * Monta a cláusula WHERE e os parâmetros a partir dos filtros.
     *
     * @param array<string, mixed> $filters
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function buildFinanceWhere(array $filters): array
    {
        $conds  = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $conds[] = 'p.payment_status = :status';
            $params['status'] = $status;
        }

        $giftId = (int) ($filters['gift_id'] ?? 0);
        if ($giftId > 0) {
            $conds[] = 'p.gift_id = :gift_id';
            $params['gift_id'] = $giftId;
        }

        $guest = trim((string) ($filters['guest'] ?? ''));
        if ($guest !== '') {
            // Busca pelo nome do comprador OU do convidado vinculado.
            $conds[] = '(p.buyer_name LIKE :guest OR gu.name LIKE :guest)';
            $params['guest'] = '%' . $guest . '%';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $conds[] = 'p.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $conds[] = 'p.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $where = $conds === [] ? '' : (' WHERE ' . implode(' AND ', $conds));
        return [$where, $params];
    }
}
