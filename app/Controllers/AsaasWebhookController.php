<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Notifier;
use App\Models\AsaasWebhookEvent;
use App\Models\Gift;
use App\Models\GiftPurchase;
use App\Models\Guest;
use App\Models\Setting;
use Throwable;

/**
 * Recebe os webhooks do ASAAS e atualiza o status das compras de presentes.
 *
 * O ASAAS envia um POST JSON { id, event, payment: {...} }. Esta rota:
 *   - valida o token opcional (header asaas-access-token);
 *   - garante idempotência (ignora eventos já processados pelo id);
 *   - mapeia o evento/status para o status interno (paid/failed/refunded);
 *   - responde SEMPRE 200 quando a mensagem foi aceita, para o ASAAS não
 *     reenfileirar (processamentos lentos deveriam ser assíncronos; aqui a
 *     atualização é simples e rápida).
 */
final class AsaasWebhookController extends Controller
{
    /** Eventos que indicam pagamento confirmado. */
    private const PAID_EVENTS = [
        'PAYMENT_RECEIVED',
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED_IN_CASH',
    ];

    /** Eventos que indicam falha/estorno. */
    private const FAILED_EVENTS   = ['PAYMENT_OVERDUE', 'PAYMENT_DELETED'];
    private const REFUND_EVENTS   = ['PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED'];

    public function handle(): void
    {
        // Lê o corpo bruto (o ASAAS envia JSON, não form-urlencoded).
        $raw     = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $this->respond(400, 'invalid payload');
            return;
        }

        // Validação opcional do token configurado no painel.
        if (!$this->tokenIsValid()) {
            $this->respond(401, 'unauthorized');
            return;
        }

        $eventId   = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['event'] ?? '');
        $payment   = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $paymentId = (string) ($payment['id'] ?? '');

        if ($eventType === '' || $paymentId === '') {
            // Nada a processar, mas confirmamos o recebimento.
            $this->respond(200, 'ignored');
            return;
        }

        try {
            // Idempotência: só processa a primeira ocorrência do evento.
            $events = new AsaasWebhookEvent($this->config);
            if (!$events->registerIfNew($eventId, $eventType, $paymentId)) {
                $this->respond(200, 'duplicate');
                return;
            }

            $status = $this->mapStatus($eventType, (string) ($payment['status'] ?? ''));
            if ($status !== null) {
                $purchases = new GiftPurchase($this->config);
                $purchase  = $purchases->findByPaymentRef($paymentId);
                if ($purchase !== null) {
                    $purchaseId = (int) $purchase['id'];
                    $prevStatus = (string) ($purchase['payment_status'] ?? '');
                    $wasPaid    = $prevStatus === 'paid';
                    $giftId     = (int) ($purchase['gift_id'] ?? 0);

                    $purchases->updateStatus($purchaseId, $status);

                    // Trilha de auditoria da mudança de status (ponto 11).
                    if ($prevStatus !== $status) {
                        $purchases->logStatus($purchaseId, $prevStatus ?: null, $status, 'webhook:' . $eventType);
                    }

                    // Captura financeira (ponto 11): taxas, valor líquido e datas
                    // extraídas do payload do gateway, gravadas na transação.
                    $purchases->updateFinance($purchaseId, $this->extractFinance($payment, $status));

                    // Disponibilidade do presente (ponto 9): confirma a aquisição
                    // ao pagar; libera a reserva quando falha/estorna.
                    if ($giftId > 0) {
                        $gifts = new Gift($this->config);
                        if ($status === 'paid') {
                            $gifts->markPurchased($giftId);
                        } elseif ($status === 'failed' || $status === 'refunded') {
                            $gifts->releaseIfReserved($giftId);
                        }
                    }

                    // Confirmação de pagamento por e-mail + WhatsApp (ponto 8),
                    // apenas na transição para "paid" (evita mensagens duplicadas).
                    if ($status === 'paid' && !$wasPaid) {
                        $this->sendPaymentConfirmation($purchase, $payment);
                    }
                }
            }

            $this->respond(200, 'ok');
        } catch (Throwable $e) {
            error_log('[AsaasWebhook] handle: ' . $e->getMessage());
            // 500 faz o ASAAS reenviar depois; a idempotência cobre duplicatas.
            $this->respond(500, 'error');
        }
    }

    /**
     * Extrai os dados financeiros do objeto `payment` do webhook (ponto 11).
     *
     * O ASAAS envia `value` (bruto) e `netValue` (líquido já descontadas as
     * taxas) em reais; a taxa é a diferença. As datas vêm como "Y-m-d" (ou
     * "Y-m-d H:i:s"): paymentDate/clientPaymentDate (pagamento), confirmedDate
     * (confirmação) e creditDate (liquidação/crédito na conta).
     *
     * Retorna só as chaves com valor conhecido — updateFinance usa COALESCE,
     * então nada é sobrescrito com null.
     *
     * @param array<string, mixed> $payment
     * @return array{
     *   fee_cents?:int, net_cents?:int, finance_status?:string,
     *   paid_at?:string, confirmed_at?:string, settled_at?:string, refunded_at?:string
     * }
     */
    private function extractFinance(array $payment, string $status): array
    {
        $out = [];

        $value = isset($payment['value']) ? (float) $payment['value'] : null;
        $net   = isset($payment['netValue']) ? (float) $payment['netValue'] : null;

        if ($net !== null) {
            $netCents = (int) round($net * 100);
            $out['net_cents'] = max(0, $netCents);
            if ($value !== null) {
                $out['fee_cents'] = max(0, (int) round($value * 100) - $netCents);
            }
        }

        // Datas (normalizadas para 'Y-m-d H:i:s' ou null).
        $paymentDate   = $this->normalizeDate($payment['paymentDate'] ?? ($payment['clientPaymentDate'] ?? null));
        $confirmedDate = $this->normalizeDate($payment['confirmedDate'] ?? null);
        $creditDate    = $this->normalizeDate($payment['creditDate'] ?? null);

        if ($paymentDate !== null)   { $out['paid_at'] = $paymentDate; }
        if ($confirmedDate !== null) { $out['confirmed_at'] = $confirmedDate; }
        if ($creditDate !== null)    { $out['settled_at'] = $creditDate; }

        // Status financeiro granular e marca de estorno.
        if ($status === 'paid') {
            // "received" quando já há crédito/liquidação; senão "confirmed".
            $out['finance_status'] = $creditDate !== null ? 'received' : 'confirmed';
        } elseif ($status === 'refunded') {
            $out['finance_status'] = 'refunded';
            $out['refunded_at']    = $this->normalizeDate($payment['dateCreated'] ?? null) ?? date('Y-m-d H:i:s');
        } elseif ($status === 'failed') {
            $out['finance_status'] = 'canceled';
        }

        return $out;
    }

    /**
     * Normaliza uma data vinda do ASAAS ("Y-m-d" ou "Y-m-d H:i:s") para o
     * formato de TIMESTAMP do MySQL. Retorna null se vazia/inválida.
     */
    private function normalizeDate(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Dispara a confirmação de pagamento ao presenteador por e-mail e WhatsApp
     * (ponto 8), com o resumo da compra (presente, valor, data e id da
     * transação). Delega ao Notifier, que monta o template HTML e registra cada
     * canal em communication_logs. Tolerante a falhas: nunca interrompe o
     * processamento do webhook.
     *
     * @param array<string, mixed> $purchase Linha de gift_purchases.
     * @param array<string, mixed> $payment  Objeto `payment` do webhook.
     */
    private function sendPaymentConfirmation(array $purchase, array $payment): void
    {
        try {
            $guestId = (int) ($purchase['guest_id'] ?? 0) ?: null;
            $name    = trim((string) ($purchase['buyer_name'] ?? ''));
            $email   = trim((string) ($purchase['buyer_email'] ?? ''));
            $phone   = null;

            // Nome do presente (a linha de gift_purchases não traz o join).
            $giftName = '';
            $giftId   = (int) ($purchase['gift_id'] ?? 0);
            if ($giftId > 0) {
                $gift     = (new Gift($this->config))->find($giftId);
                $giftName = trim((string) ($gift['name'] ?? ''));
            }

            // Dados de contato do convidado logado (telefone p/ WhatsApp, e um
            // e-mail de reserva caso a compra não tenha registrado o do comprador).
            if ($guestId !== null) {
                $guest = (new Guest($this->config))->find($guestId);
                if ($guest !== null) {
                    $phone = $guest['phone'] ?? null;
                    if ($email === '') {
                        $email = trim((string) ($guest['email'] ?? ''));
                    }
                    if ($name === '') {
                        $name = trim((string) ($guest['name'] ?? ''));
                    }
                }
            }

            // Data de pagamento: usa a do payload, com fallback para "agora".
            $paidRaw = $payment['paymentDate'] ?? ($payment['clientPaymentDate'] ?? ($payment['confirmedDate'] ?? null));
            $paidTs  = $paidRaw !== null ? strtotime((string) $paidRaw) : false;
            $dateStr = $paidTs !== false ? date('d/m/Y', $paidTs) : date('d/m/Y');

            (new Notifier($this->config))->sendPaymentConfirmation(
                [
                    'id'    => $guestId,
                    'name'  => $name !== '' ? $name : 'convidado(a)',
                    'email' => $email ?: null,
                    'phone' => $phone,
                ],
                [
                    'giftName'      => $giftName,
                    'amount'        => $this->formatAmount((int) ($purchase['amount_cents'] ?? 0)),
                    'date'          => $dateStr,
                    'transactionId' => trim((string) ($purchase['payment_ref'] ?? '')),
                ]
            );
        } catch (Throwable $e) {
            error_log('[AsaasWebhook] sendPaymentConfirmation: ' . $e->getMessage());
        }
    }

    /** Formata centavos como "R$ 1.234,56" (vazio quando não houver valor). */
    private function formatAmount(int $cents): string
    {
        if ($cents <= 0) {
            return '';
        }
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Valida o header asaas-access-token contra o token salvo no painel.
     * Se nenhum token foi configurado, aceita (o cliente pode optar por não
     * usar token). Comparação em tempo constante.
     */
    private function tokenIsValid(): bool
    {
        try {
            $expected = (new Setting($this->config))->get('asaas_webhook_token');
        } catch (Throwable $e) {
            $expected = null;
        }

        $expected = (string) ($expected ?? '');
        if ($expected === '') {
            return true; // sem token configurado
        }

        $received = (string) ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');
        return $received !== '' && hash_equals($expected, $received);
    }

    /** Mapeia o evento/status do ASAAS para o status interno. */
    private function mapStatus(string $event, string $paymentStatus): ?string
    {
        if (in_array($event, self::PAID_EVENTS, true)) {
            return 'paid';
        }
        if (in_array($event, self::REFUND_EVENTS, true)) {
            return 'refunded';
        }
        if (in_array($event, self::FAILED_EVENTS, true)) {
            return 'failed';
        }

        // Fallback pelo status do recurso, caso o evento não seja mapeado.
        $s = strtoupper($paymentStatus);
        if (in_array($s, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            return 'paid';
        }
        if ($s === 'REFUNDED') {
            return 'refunded';
        }

        return null; // evento irrelevante para o nosso fluxo
    }

    /** Responde com um status HTTP e um corpo curto, encerrando a execução. */
    private function respond(int $code, string $body): void
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }
}
