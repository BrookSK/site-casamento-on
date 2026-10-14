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
}
