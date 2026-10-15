<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Asaas;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Models\Gift;
use App\Models\GiftPurchase;
use Throwable;

/**
 * Fluxo público de presentear (lista de presentes com pagamento real ASAAS).
 *
 * Fluxo:
 *   1. O visitante escolhe um presente e a forma de pagamento (PIX, cartão
 *      ou boleto) no formulário renderizado em partials/gifts.php.
 *   2. store() cria/garante um cliente no ASAAS, gera a cobrança, grava a
 *      compra como "pending" em gift_purchases e mostra a tela de pagamento.
 *   3. A confirmação do pagamento chega de forma assíncrona pelo webhook
 *      (AsaasWebhookController), que atualiza o status para "paid".
 */
final class GiftController extends Controller
{
    /** Formas de pagamento aceitas (valores aceitos pela API do ASAAS). */
    private const BILLING_TYPES = ['PIX', 'CREDIT_CARD', 'BOLETO'];

    /** Processa a escolha de um presente e inicia a cobrança. */
    public function store(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->redirectHome('presentes', 'erro-sessao');
        }

        // Ponto 6.5: exige autenticação para prosseguir ao pagamento. Barreira
        // no servidor (o aviso no front é apenas UX; aqui garante a regra mesmo
        // num POST direto). Visitante não logado volta com a mensagem de login.
        if (!GuestAuth::check()) {
            $this->redirectHome('presentes', 'login');
        }

        $giftId      = (int) ($_POST['gift_id'] ?? 0);
        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, self::BILLING_TYPES, true)) {
            $billingType = 'PIX';
        }

        // Dados de quem está presenteando. Se houver convidado logado, usa-o.
        $guest     = GuestAuth::user();
        $guestId   = $guest['id'] ?? null;
        $buyerName = trim((string) ($_POST['buyer_name'] ?? ($guest['name'] ?? '')));
        $buyerMail = trim((string) ($_POST['buyer_email'] ?? ($guest['email'] ?? '')));
        $buyerCpf  = trim((string) ($_POST['buyer_cpf'] ?? ''));
        $buyerPhone= trim((string) ($_POST['buyer_phone'] ?? ''));
        $message   = trim((string) ($_POST['message'] ?? '')) ?: null;

        if ($buyerName === '') {
            $this->redirectHome('presentes', 'erro-nome');
        }
        // Boleto exige CPF/CNPJ do pagador no ASAAS.
        if ($billingType === 'BOLETO' && preg_replace('/\D+/', '', $buyerCpf) === '') {
            $this->redirectHome('presentes', 'erro-cpf');
        }

        $giftModel = new Gift($this->config);
        $reserved  = false;

        // Libera reservas vencidas antes de avaliar a disponibilidade, para que
        // um presente abandonado por outro visitante volte a ser comprável.
        self::releaseExpiredReservations($this->config);

        try {
            $gift = $giftModel->findAvailable($giftId);
            if ($gift === null) {
                $this->redirectHome('presentes', 'erro-indisponivel');
            }

            $amountCents = (int) $gift['price_cents'];
            if ($amountCents <= 0) {
                $this->redirectHome('presentes', 'erro-valor');
            }
            $value = round($amountCents / 100, 2);

            // Reserva temporária ATÔMICA do presente antes de abrir a cobrança
            // (ponto 9). Se outro presenteador reservou/comprou no intervalo, o
            // UPDATE condicional não afeta a linha e abortamos como indisponível,
            // impedindo compras duplicadas simultâneas.
            if (!$giftModel->reserveIfAvailable($giftId)) {
                $this->redirectHome('presentes', 'erro-indisponivel');
            }
            $reserved = true;

            $asaas = Asaas::fromSettings($this->config);

            // 1) Cliente no ASAAS.
            $customerId = $asaas->createCustomer([
                'name'    => $buyerName,
                'email'   => $buyerMail ?: null,
                'phone'   => $buyerPhone ?: null,
                'cpfCnpj' => $buyerCpf ?: null,
            ]);

            // 2) Cobrança (vencimento: hoje para PIX, +3 dias para boleto/cartão).
            $dueDays = $billingType === 'PIX' ? 0 : 3;
            $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));

            $payment = $asaas->createPayment([
                'customer'          => $customerId,
                'billingType'       => $billingType,
                'value'             => $value,
                'dueDate'           => $dueDate,
                'description'       => 'Presente: ' . (string) $gift['name'],
                'externalReference' => 'gift_' . $giftId,
            ]);

            $paymentId   = (string) ($payment['id'] ?? '');
            $invoiceUrl  = isset($payment['invoiceUrl']) ? (string) $payment['invoiceUrl'] : null;
            $bankSlipUrl = isset($payment['bankSlipUrl']) ? (string) $payment['bankSlipUrl'] : null;

            // 3) Para PIX, recupera o QR Code (imagem + copia e cola).
            $pixPayload = null;
            $pixQr      = null;
            if ($billingType === 'PIX' && $paymentId !== '') {
                try {
                    $qr         = $asaas->getPixQrCode($paymentId);
                    $pixPayload = $qr['payload'];
                    $pixQr      = $qr['encodedImage'];
                } catch (Throwable $e) {
                    error_log('[Gift] pixQrCode: ' . $e->getMessage());
                }
            }

            // 4) Grava a compra como pendente.
            $purchases = new GiftPurchase($this->config);
            $purchases->createPending([
                'gift_id'           => $giftId,
                'guest_id'          => $guestId,
                'buyer_name'        => $buyerName,
                'buyer_email'       => $buyerMail ?: null,
                'amount_cents'      => $amountCents,
                'gift_price_cents'  => $amountCents,
                'payment_ref'       => $paymentId ?: null,
                'asaas_customer_id' => $customerId,
                'billing_type'      => $billingType,
                'invoice_url'       => $invoiceUrl,
                'bank_slip_url'     => $bankSlipUrl,
                'pix_payload'       => $pixPayload,
                'pix_qr_base64'     => $pixQr,
                'message'           => $message,
            ]);

            // 5) Mostra a tela de pagamento.
            $this->view('gift/pay', [
                'pageTitle'    => 'Pagamento do presente',
                'gift'         => $gift,
                'billingType'  => $billingType,
                'value'        => $value,
                'invoiceUrl'   => $invoiceUrl,
                'bankSlipUrl'  => $bankSlipUrl,
                'pixPayload'   => $pixPayload,
                'pixQr'        => $pixQr,
            ]);
            return;
        } catch (Throwable $e) {
            error_log('[Gift] store: ' . $e->getMessage());
            // Libera a reserva feita acima quando a cobrança não chegou a ser
            // concluída, para o presente não ficar preso em 'reserved'.
            if ($reserved) {
                try {
                    $giftModel->releaseIfReserved($giftId);
                } catch (Throwable $re) {
                    error_log('[Gift] release on error: ' . $re->getMessage());
                }
            }
            $this->redirectHome('presentes', 'erro');
        }
    }

    /**
     * Expira as reservas presas em pagamentos pendentes antigos e devolve os
     * presentes à disponibilidade (ponto 9). Oportunista e tolerante a falhas:
     * é chamado ao renderizar a lista (home) e antes de uma nova compra, de
     * modo que a limpeza acontece sem depender de um cron.
     *
     * @param array<string, mixed> $config
     * @param int $maxAgeMinutes Idade máxima de uma compra pendente (minutos).
     */
    public static function releaseExpiredReservations(array $config, int $maxAgeMinutes = 60): void
    {
        try {
            $purchases = new GiftPurchase($config);
            $giftIds   = $purchases->expireStalePending($maxAgeMinutes);
            if ($giftIds === []) {
                return;
            }
            $gifts = new Gift($config);
            foreach ($giftIds as $giftId) {
                $gifts->releaseIfNoActivePurchase((int) $giftId);
            }
        } catch (Throwable $e) {
            error_log('[Gift] releaseExpiredReservations: ' . $e->getMessage());
        }
    }

    /** Redireciona para a home, numa âncora, com um status de retorno. */
    private function redirectHome(string $anchor, string $status): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/?gift=' . urlencode($status) . '#' . $anchor);
        exit;
    }
}
