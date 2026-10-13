<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
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
                    $wasPaid = ($purchase['payment_status'] ?? '') === 'paid';
                    $purchases->updateStatus((int) $purchase['id'], $status);

                    // Agradece ao presenteador quando o pagamento é confirmado
                    // (apenas na transição para "paid", para não repetir e-mails).
                    if ($status === 'paid' && !$wasPaid) {
                        $this->sendThankYouEmail($purchase);
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
     * Envia um e-mail de agradecimento ao presenteador quando a cobrança é
     * confirmada. Tolerante a falhas: nunca interrompe o processamento do
     * webhook (um erro de SMTP não deve fazer o ASAAS reenviar o evento).
     *
     * @param array<string, mixed> $purchase Linha de gift_purchases.
     */
    private function sendThankYouEmail(array $purchase): void
    {
        try {
            $email = trim((string) ($purchase['buyer_email'] ?? ''));
            if ($email === '' || !Mailer::isConfigured($this->config)) {
                return;
            }

            $name   = trim((string) ($purchase['buyer_name'] ?? ''));
            $site   = (string) ($this->config['app']['name'] ?? '');
            $greet  = $name !== '' ? ('Olá ' . $name . ', ') : '';
            $amount = $this->formatAmount((int) ($purchase['amount_cents'] ?? 0));

            $html = '<p>' . htmlspecialchars($greet, ENT_QUOTES, 'UTF-8')
                . 'recebemos a sua contribuição' . ($amount !== '' ? (' de <strong>' . $amount . '</strong>') : '')
                . '. Muito obrigado pelo carinho!</p>'
                . ($site !== '' ? ('<p>Com carinho, ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '.</p>') : '');

            $mailer = Mailer::fromSettings($this->config);
            $mailer->send($email, 'Recebemos o seu presente — obrigado!', $html, $name);
        } catch (Throwable $e) {
            error_log('[AsaasWebhook] sendThankYouEmail: ' . $e->getMessage());
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
