<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AsaasWebhookEvent;
use App\Models\GiftPurchase;
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
                    $purchases->updateStatus((int) $purchase['id'], $status);
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
