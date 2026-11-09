<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Asaas;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Core\Notifier;
use App\Models\Gift;
use App\Models\GiftPurchase;
use App\Models\Guest;
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
            // Sessão expirada (aba aberta por muito tempo no celular, etc.).
            // Se o envio veio do próprio site, guarda o que foi digitado para
            // reabrir o formulário preenchido em vez de perder tudo.
            $this->back('erro-sessao', null, $this->isSameOriginPost());
        }

        $giftId      = (int) ($_POST['gift_id'] ?? 0);
        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, self::BILLING_TYPES, true)) {
            $billingType = 'PIX';
        }

        // Envio repetido do mesmo presente (toque duplo no botão, voltar e
        // reenviar, recarregar): se ESTA sessão já gerou a cobrança, mostra o
        // mesmo pagamento em vez de dizer que o presente está indisponível.
        if ($this->showExistingPayment($giftId, $billingType)) {
            return;
        }

        // Dados de quem está presenteando. Se houver convidado logado, usa-o
        // como base; os campos do formulário têm prioridade quando preenchidos.
        $loggedGuest = GuestAuth::user();
        $buyerName   = trim((string) ($_POST['buyer_name'] ?? ($loggedGuest['name'] ?? '')));
        $buyerMail   = trim((string) ($_POST['buyer_email'] ?? ($loggedGuest['email'] ?? '')));
        $buyerCpf    = trim((string) ($_POST['buyer_cpf'] ?? ''));
        $buyerPhone  = trim((string) ($_POST['buyer_phone'] ?? ''));
        $message     = trim((string) ($_POST['message'] ?? '')) ?: null;

        if ($buyerName === '') {
            $this->back('erro-nome');
        }

        // O ASAAS exige CPF/CNPJ do pagador em qualquer cobrança (PIX também),
        // então o documento é pedido e validado sempre — antes, convidado
        // logado pagando PIX seguia sem CPF e a cobrança falhava no ASAAS.
        if (!Guest::isValidCpfCnpj($buyerCpf)) {
            $this->back('erro-cpf');
        }

        // Fluxo reaproveitado da confirmação de presença (ponto 6): quando o
        // visitante NÃO está logado, exigimos e-mail e telefone para criar
        // automaticamente a conta e vincular a compra. Convidado já autenticado
        // segue direto com a conta atual.
        $isLogged  = GuestAuth::check();
        $welcome   = null; // dados para enviar o link de 1ª senha (conta nova)
        $guestId   = $isLogged ? ($loggedGuest['id'] ?? null) : null;

        if (!$isLogged) {
            if ($buyerMail === '' || !filter_var($buyerMail, FILTER_VALIDATE_EMAIL)) {
                $this->back('erro-email');
            }
            if (strlen(Asaas::nationalPhone($buyerPhone)) < 10) {
                $this->back('erro-telefone');
            }

            try {
                // Cria/assume a conta (mesma lógica do RSVP: invite_token para
                // definição de senha quando a conta é nova / sem senha).
                $welcome = (new Guest($this->config))->findOrCreateForPurchase([
                    'name'  => $buyerName,
                    'email' => $buyerMail,
                    'phone' => $buyerPhone,
                ]);
                $guestId = (int) $welcome['guest']['id'];

                // Login automático só para conta nova / ainda sem senha. Conta
                // com senha exige login de verdade: digitar o e-mail de alguém
                // no formulário não pode dar acesso à conta dessa pessoa. A
                // compra continua vinculada a ela pelo guest_id.
                if (empty($welcome['has_password'])) {
                    GuestAuth::login($welcome['guest']);
                }
                // Guarda o comprador na sessão para o passo do cartão (payCard)
                // vincular a compra mesmo sem login.
                $_SESSION['gift_buyer_guest_id'] = $guestId;
            } catch (\RuntimeException $e) {
                // E-mail e telefone pertencem a convidados diferentes.
                if ($e->getMessage() === 'conflict') {
                    $this->back('erro-conflito');
                }
                error_log('[Gift] findOrCreateForPurchase: ' . $e->getMessage());
                $this->back('erro');
            } catch (Throwable $e) {
                error_log('[Gift] findOrCreateForPurchase: ' . $e->getMessage());
                $this->back('erro');
            }
        }

        $giftModel = new Gift($this->config);

        // Marca como expiradas as cobranças pendentes abandonadas (limpeza).
        self::expireStalePayments($this->config);

        try {
            // O mesmo presente pode ser dado por várias pessoas: não há
            // reserva nem bloqueio — basta existir, não estar oculto e ter valor.
            $gift = $giftModel->findPurchasable($giftId);
            if ($gift === null) {
                $this->redirectHome('presentes', 'erro-indisponivel');
            }

            $amountCents = (int) $gift['price_cents'];
            if ($amountCents <= 0) {
                $this->redirectHome('presentes', 'erro-valor');
            }
            $value = round($amountCents / 100, 2);

            // CARTÃO: não cria a cobrança agora. Abrimos a tela de pagamento
            // com o FORMULÁRIO do cartão; a cobrança só é criada quando esse
            // formulário for enviado (payCard()).
            if ($billingType === 'CREDIT_CARD') {
                // Conta nova sem senha: já envia as boas-vindas (link de senha).
                if ($welcome !== null) {
                    $this->sendWelcome($welcome);
                }
                $cardForm = [
                    'gift_id'     => $giftId,
                    'buyer_name'  => $buyerName,
                    'buyer_email' => $buyerMail,
                    'buyer_phone' => $buyerPhone,
                    'buyer_cpf'   => $buyerCpf,
                    'message'     => $message,
                ];
                $this->rememberPayment($giftId, 'CREDIT_CARD', null, $cardForm);
                // Novo presente com cartão (pode ser o mesmo item de novo):
                // esquece a aprovação anterior para não mostrar sucesso falso.
                unset($_SESSION['gift_paid'][$giftId]);
                $this->view('gift/pay', [
                    'pageTitle'   => 'Pagamento do presente',
                    'bodyClass'   => 'page-light',
                    'gift'        => $gift,
                    'billingType' => 'CREDIT_CARD',
                    'value'       => $value,
                    'cardApproved'=> null,
                    // Dados repassados ao formulário do cartão (passo seguinte).
                    'cardForm'    => $cardForm,
                ]);
                return;
            }

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

            $paymentData = [
                'customer'          => $customerId,
                'billingType'       => $billingType,
                'value'             => $value,
                'dueDate'           => $dueDate,
                'description'       => 'Presente: ' . (string) $gift['name'],
                'externalReference' => 'gift_' . $giftId,
            ];

            $payment = $asaas->createPayment($paymentData);

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
            if ($paymentId !== '') {
                $this->rememberPayment($giftId, $billingType, $paymentId);
            }

            // 4.1) Conta recém-criada/assumida sem senha: envia as boas-vindas
            // com o link seguro de definição de senha (e-mail e, se configurado,
            // WhatsApp). Tolerante a falhas — não interrompe o pagamento.
            if ($welcome !== null) {
                $this->sendWelcome($welcome);
            }

            // 5) Mostra a tela de pagamento. O bodyClass "page-light" deixa o
            // navbar SÓLIDO (legível) durante todo o fluxo de pagamento e aplica
            // o respiro de topo para o conteúdo não ficar sob o header fixo.
            $this->view('gift/pay', [
                'pageTitle'    => 'Pagamento do presente',
                'bodyClass'    => 'page-light',
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
            $this->back('erro', self::payerErrorDetail($e));
        }
    }

    /**
     * Motivo legível de uma recusa do ASAAS por dados do pagador (HTTP 400,
     * ex.: "O CPF/CNPJ informado é inválido."). Outros erros (chave, rede,
     * banco) ficam só no log e o visitante vê a mensagem genérica.
     */
    private static function payerErrorDetail(Throwable $e): ?string
    {
        $msg = $e->getMessage();
        if ($e->getCode() !== 400 || !str_starts_with($msg, 'ASAAS: ')) {
            return null;
        }
        $detail = trim(substr($msg, 7));
        return $detail !== '' ? mb_substr($detail, 0, 240) : null;
    }

    /**
     * Guarda na sessão a cobrança aberta para um presente. Se o mesmo envio
     * chegar de novo (toque duplo, voltar/reenviar), showExistingPayment()
     * reaproveita a cobrança em vez de responder "indisponível".
     *
     * @param array<string, mixed>|null $cardForm dados do comprador (cartão)
     */
    private function rememberPayment(int $giftId, string $billingType, ?string $paymentRef, ?array $cardForm = null): void
    {
        $_SESSION['gift_pending'][$giftId] = [
            'type'     => $billingType,
            'ref'      => $paymentRef,
            'card'     => $cardForm,
            'at'       => time(),
        ];
    }

    /**
     * Mostra de novo o pagamento já gerado NESTA sessão para o presente, se
     * ainda estiver pendente e for da mesma forma de pagamento. Evita que um
     * toque duplo crie duas cobranças. Depois de pago (webhook), a mesma
     * pessoa pode presentear o mesmo item de novo normalmente.
     */
    private function showExistingPayment(int $giftId, string $billingType): bool
    {
        $entry = $_SESSION['gift_pending'][$giftId] ?? null;
        if (!is_array($entry) || ($entry['type'] ?? '') !== $billingType) {
            return false;
        }
        // Um pouco antes da expiração das pendentes (expireStalePayments, 60 min).
        if ((int) ($entry['at'] ?? 0) < time() - 55 * 60) {
            unset($_SESSION['gift_pending'][$giftId]);
            return false;
        }

        try {
            $gift = (new Gift($this->config))->find($giftId);
            if ($gift === null) {
                return false;
            }
            $value = round(((int) $gift['price_cents']) / 100, 2);

            if ($billingType === 'CREDIT_CARD') {
                if (!is_array($entry['card'] ?? null)) {
                    return false;
                }
                $this->view('gift/pay', [
                    'pageTitle'    => 'Pagamento do presente',
                    'bodyClass'    => 'page-light',
                    'gift'         => $gift,
                    'billingType'  => 'CREDIT_CARD',
                    'value'        => $value,
                    'cardApproved' => null,
                    'cardForm'     => $entry['card'],
                ]);
                return true;
            }

            $ref = (string) ($entry['ref'] ?? '');
            $purchase = $ref !== '' ? (new GiftPurchase($this->config))->findByPaymentRef($ref) : null;
            if ($purchase === null || (string) ($purchase['payment_status'] ?? '') !== 'pending') {
                return false;
            }

            $this->view('gift/pay', [
                'pageTitle'   => 'Pagamento do presente',
                'bodyClass'   => 'page-light',
                'gift'        => $gift,
                'billingType' => $billingType,
                'value'       => $value,
                'invoiceUrl'  => $purchase['invoice_url'] ?? null,
                'bankSlipUrl' => $purchase['bank_slip_url'] ?? null,
                'pixPayload'  => $purchase['pix_payload'] ?? null,
                'pixQr'       => $purchase['pix_qr_base64'] ?? null,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[Gift] showExistingPayment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processa o pagamento com CARTÃO (checkout transparente), recebendo os
     * dados do cartão do formulário exibido na tela de pagamento. Cria a
     * cobrança no ASAAS com creditCard/creditCardHolderInfo/remoteIp e mostra
     * o resultado (aprovado/recusado). Os dados do cartão não são armazenados
     * nem registrados em log.
     */
    public function payCard(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->redirectHome('presentes', 'erro-sessao');
        }

        $giftId     = (int) ($_POST['gift_id'] ?? 0);

        // Cartão já aprovado nesta sessão (envio repetido): mostra o sucesso.
        if ((int) ($_SESSION['gift_paid'][$giftId] ?? 0) > time() - 3600) {
            $paidGift = (new Gift($this->config))->find($giftId);
            if ($paidGift !== null) {
                $this->view('gift/pay', [
                    'pageTitle'    => 'Pagamento do presente',
                    'bodyClass'    => 'page-light',
                    'gift'         => $paidGift,
                    'billingType'  => 'CREDIT_CARD',
                    'value'        => round(((int) $paidGift['price_cents']) / 100, 2),
                    'cardApproved' => true,
                ]);
                return;
            }
        }

        $buyerName  = trim((string) ($_POST['buyer_name'] ?? ''));
        $buyerMail  = trim((string) ($_POST['buyer_email'] ?? ''));
        $buyerPhone = trim((string) ($_POST['buyer_phone'] ?? ''));
        $buyerCpf   = trim((string) ($_POST['buyer_cpf'] ?? ''));
        $message    = trim((string) ($_POST['message'] ?? '')) ?: null;
        // Convidado logado ou, sem login, o comprador identificado no passo
        // anterior (store) desta mesma sessão.
        $guestId    = GuestAuth::user()['id'] ?? ($_SESSION['gift_buyer_guest_id'] ?? null);
        $guestId    = $guestId !== null ? (int) $guestId : null;

        if ($buyerName === '' || !Guest::isValidCpfCnpj($buyerCpf)) {
            $this->redirectHome('presentes', 'erro-cpf');
        }

        // Valida os dados do cartão (sem logar).
        $card = $this->readCardInput();
        if ($card === null) {
            // Reabre a tela do cartão com aviso, preservando os dados do comprador.
            $this->renderCardForm($giftId, $buyerName, $buyerMail, $buyerPhone, $buyerCpf, $message, 'invalid');
            return;
        }

        $giftModel = new Gift($this->config);
        try {
            $gift = $giftModel->findPurchasable($giftId);
            if ($gift === null) {
                $this->redirectHome('presentes', 'erro-indisponivel');
            }

            $amountCents = (int) $gift['price_cents'];
            if ($amountCents <= 0) {
                $this->redirectHome('presentes', 'erro-valor');
            }
            $value = round($amountCents / 100, 2);

            $asaas = Asaas::fromSettings($this->config);

            $customerId = $asaas->createCustomer([
                'name'    => $buyerName,
                'email'   => $buyerMail ?: null,
                'phone'   => $buyerPhone ?: null,
                'cpfCnpj' => $buyerCpf ?: null,
            ]);

            $payment = $asaas->createPayment([
                'customer'          => $customerId,
                'billingType'       => 'CREDIT_CARD',
                'value'             => $value,
                'dueDate'           => date('Y-m-d'),
                'description'       => 'Presente: ' . (string) $gift['name'],
                'externalReference' => 'gift_' . $giftId,
                'creditCard'        => $card,
                'creditCardHolderInfo' => [
                    'name'          => $buyerName,
                    'email'         => $buyerMail,
                    'cpfCnpj'       => preg_replace('/\D+/', '', $buyerCpf),
                    'postalCode'    => preg_replace('/\D+/', '', (string) ($_POST['card_cep'] ?? '')),
                    'addressNumber' => trim((string) ($_POST['card_address_number'] ?? '')),
                    'phone'         => Asaas::nationalPhone($buyerPhone) ?: null,
                ],
                'remoteIp'          => $this->clientIp(),
            ]);

            $paymentId = (string) ($payment['id'] ?? '');
            $status    = strtoupper((string) ($payment['status'] ?? ''));

            // Grava a compra.
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
                'billing_type'      => 'CREDIT_CARD',
                'invoice_url'       => isset($payment['invoiceUrl']) ? (string) $payment['invoiceUrl'] : null,
                'bank_slip_url'     => null,
                'pix_payload'       => null,
                'pix_qr_base64'     => null,
                'message'           => $message,
            ]);

            $approved = in_array($status, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true);
            if ($approved && $paymentId !== '') {
                $purchase = $purchases->findByPaymentRef($paymentId);
                if ($purchase !== null) {
                    $purchaseId = (int) $purchase['id'];
                    $purchases->updateStatus($purchaseId, 'paid');
                    // Trilha de auditoria + data de pagamento (coerente com o
                    // que o webhook grava).
                    try {
                        $purchases->logStatus($purchaseId, 'pending', 'paid', 'card:checkout');
                        $purchases->updateFinance($purchaseId, [
                            'finance_status' => 'confirmed',
                            'paid_at'        => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Throwable $e) {
                        error_log('[Gift] payCard finance: ' . $e->getMessage());
                    }
                }
                // Reenvio do mesmo formulário (toque duplo) mostra o sucesso
                // de novo em vez de "presente indisponível".
                unset($_SESSION['gift_pending'][$giftId]);
                $_SESSION['gift_paid'][$giftId] = time();

                // Confirmação por e-mail + WhatsApp AGORA (cartão aprovado na
                // hora). Como já marcamos "paid", quando o webhook do ASAAS
                // chegar depois ele verá wasPaid=true e NÃO reenviará — sem
                // duplicar a mensagem. Tolerante a falhas.
                $this->notifyCardPaid($giftId, $guestId, $buyerName, $buyerMail, $buyerPhone, $amountCents, $paymentId);
            }

            $this->view('gift/pay', [
                'pageTitle'    => 'Pagamento do presente',
                'bodyClass'    => 'page-light',
                'gift'         => $gift,
                'billingType'  => 'CREDIT_CARD',
                'value'        => $value,
                'cardApproved' => $approved,
            ]);
            return;
        } catch (Throwable $e) {
            error_log('[Gift] payCard: ' . $e->getMessage());
            // Cartão recusado: reabre o formulário para tentar outro cartão.
            $this->renderCardForm($giftId, $buyerName, $buyerMail, $buyerPhone, $buyerCpf, $message, 'declined');
            return;
        }
    }

    /**
     * Dispara a confirmação de pagamento (e-mail + WhatsApp) imediatamente após
     * um cartão aprovado no checkout transparente — reutilizando o Notifier.
     * O telefone é buscado no cadastro do convidado quando não veio no form.
     * Tolerante a falhas: nunca interrompe a tela de sucesso.
     */
    private function notifyCardPaid(
        int $giftId,
        ?int $guestId,
        string $buyerName,
        string $buyerMail,
        string $buyerPhone,
        int $amountCents,
        string $paymentId
    ): void {
        try {
            $giftName = '';
            if ($giftId > 0) {
                $g = (new Gift($this->config))->find($giftId);
                $giftName = trim((string) ($g['name'] ?? ''));
            }

            // Telefone/e-mail do convidado logado como reserva (p/ WhatsApp).
            $phone = $buyerPhone;
            if ($guestId !== null) {
                $guest = (new Guest($this->config))->find($guestId);
                if ($guest !== null) {
                    if (trim($phone) === '') { $phone = (string) ($guest['phone'] ?? ''); }
                    if ($buyerMail === '')   { $buyerMail = (string) ($guest['email'] ?? ''); }
                    if ($buyerName === '')   { $buyerName = (string) ($guest['name'] ?? ''); }
                }
            }

            (new Notifier($this->config))->sendPaymentConfirmation(
                [
                    'id'    => $guestId,
                    'name'  => $buyerName !== '' ? $buyerName : 'convidado(a)',
                    'email' => $buyerMail ?: null,
                    'phone' => $phone ?: null,
                ],
                [
                    'giftName'      => $giftName,
                    'amount'        => $amountCents > 0 ? ('R$ ' . number_format($amountCents / 100, 2, ',', '.')) : '',
                    'date'          => date('d/m/Y'),
                    'transactionId' => $paymentId,
                ]
            );
        } catch (Throwable $e) {
            error_log('[Gift] notifyCardPaid: ' . $e->getMessage());
        }
    }

    /**
     * Renderiza a tela de pagamento com o FORMULÁRIO do cartão (reutilizada no
     * fluxo e nas tentativas após erro). $error: null | 'invalid' | 'declined'.
     */
    private function renderCardForm(
        int $giftId,
        string $buyerName,
        string $buyerMail,
        string $buyerPhone,
        string $buyerCpf,
        ?string $message,
        ?string $error = null
    ): void {
        $gift = (new Gift($this->config))->find($giftId);
        if ($gift === null) {
            $this->redirectHome('presentes', 'erro-indisponivel');
        }
        $value = round(((int) $gift['price_cents']) / 100, 2);

        $this->view('gift/pay', [
            'pageTitle'   => 'Pagamento do presente',
            'bodyClass'   => 'page-light',
            'gift'        => $gift,
            'billingType' => 'CREDIT_CARD',
            'value'       => $value,
            'cardApproved'=> null,
            'cardError'   => $error,
            'cardForm'    => [
                'gift_id'     => $giftId,
                'buyer_name'  => $buyerName,
                'buyer_email' => $buyerMail,
                'buyer_phone' => $buyerPhone,
                'buyer_cpf'   => $buyerCpf,
                'message'     => $message,
            ],
        ]);
    }

    /**
     * Dispara as boas-vindas quando a conta foi criada/assumida sem senha
     * durante a compra — mesmo comportamento do RSVP (ponto 7): há um
     * invite_token e montamos o link seguro de 1ª definição de senha. Para
     * contas já ativas (com senha) não há token, então nada é reenviado.
     *
     * @param array{
     *   guest:array{id:int,name:string,email:string},
     *   invite_token:?string,
     *   has_password:bool
     * } $welcome
     */
    private function sendWelcome(array $welcome): void
    {
        try {
            $guest = $welcome['guest'];
            $token = $welcome['invite_token'] ?? null;

            // Só há boas-vindas com link quando a conta ainda não tem senha.
            if (!empty($welcome['has_password']) || $token === null || $token === '') {
                return;
            }

            // Recupera o telefone (para o canal WhatsApp). Falha de leitura não
            // impede o e-mail.
            $phone = null;
            try {
                $full  = (new Guest($this->config))->find((int) $guest['id']);
                $phone = $full['phone'] ?? null;
            } catch (Throwable $e) {
                error_log('[Gift] sendWelcome phone: ' . $e->getMessage());
            }

            (new Notifier($this->config))->sendWelcome([
                'id'    => (int) $guest['id'],
                'name'  => (string) $guest['name'],
                'email' => $guest['email'] ?? null,
                'phone' => $phone,
            ], $this->passwordLink((string) $token));
        } catch (Throwable $e) {
            error_log('[Gift] sendWelcome: ' . $e->getMessage());
        }
    }

    /** Monta a URL absoluta do link seguro de definição de senha. */
    private function passwordLink(string $token): string
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }
        return $baseUrl . '/definir-senha?token=' . urlencode($token);
    }

    /**
     * Marca como expiradas as cobranças pendentes abandonadas (PIX/boleto não
     * pagos). Oportunista e tolerante a falhas: é chamado ao renderizar a
     * lista (home) e antes de uma nova compra, sem depender de um cron. Não
     * mexe na disponibilidade dos presentes — eles nunca ficam bloqueados.
     *
     * @param array<string, mixed> $config
     * @param int $maxAgeMinutes Idade máxima de uma compra pendente (minutos).
     */
    public static function expireStalePayments(array $config, int $maxAgeMinutes = 60): void
    {
        try {
            (new GiftPurchase($config))->expireStalePending($maxAgeMinutes);
        } catch (Throwable $e) {
            error_log('[Gift] expireStalePayments: ' . $e->getMessage());
        }
    }

    /**
     * Lê e valida os dados do cartão do POST, montando o objeto creditCard do
     * ASAAS. Retorna null se algum campo essencial estiver ausente/ inválido.
     * NUNCA registra os dados do cartão em log.
     *
     * @return array{holderName:string, number:string, expiryMonth:string, expiryYear:string, ccv:string}|null
     */
    private function readCardInput(): ?array
    {
        $holder = trim((string) ($_POST['card_holder'] ?? ''));
        $number = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $expiry = trim((string) ($_POST['card_expiry'] ?? '')); // MM/AA ou MM/AAAA
        $ccv    = preg_replace('/\D+/', '', (string) ($_POST['card_ccv'] ?? ''));

        if ($holder === '' || $number === '' || $ccv === '' || $expiry === '') {
            return null;
        }
        // Número: 13 a 19 dígitos. CCV: 3 ou 4.
        if (strlen($number) < 13 || strlen($number) > 19) {
            return null;
        }
        if (strlen($ccv) < 3 || strlen($ccv) > 4) {
            return null;
        }

        // Validade: aceita MM/AA ou MM/AAAA.
        if (!preg_match('#^(\d{2})\s*/\s*(\d{2}|\d{4})$#', $expiry, $m)) {
            return null;
        }
        $month = $m[1];
        $year  = strlen($m[2]) === 2 ? ('20' . $m[2]) : $m[2];
        if ((int) $month < 1 || (int) $month > 12) {
            return null;
        }

        return [
            'holderName'  => $holder,
            'number'      => $number,
            'expiryMonth' => $month,
            'expiryYear'  => $year,
            'ccv'         => $ccv,
        ];
    }

    /**
     * IP do PAGADOR (exigido pelo ASAAS no checkout transparente). Considera
     * proxies comuns (Plesk/Apache). Nunca usa o IP do servidor.
     */
    private function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            // Primeiro IP da cadeia X-Forwarded-For.
            trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]),
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        foreach ($candidates as $ip) {
            $ip = trim((string) $ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
        return '';
    }

    /** Redireciona para a home, numa âncora, com um status de retorno. */
    private function redirectHome(string $anchor, string $status): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/?gift=' . urlencode($status) . '#' . $anchor);
        exit;
    }

    /**
     * Volta para a lista com o erro E com o que a pessoa digitou guardado na
     * sessão: a home reabre o popup de pagamento já preenchido e mostra o
     * motivo dentro dele. Antes a página só "recarregava" e os dados sumiam.
     *
     * @param string|null $detail motivo específico (ex.: recusa do ASAAS)
     */
    private function back(string $status, ?string $detail = null, bool $keepInput = true): void
    {
        if ($keepInput) {
            $field = static fn (string $k, int $max): string
                => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);

            $billing = strtoupper($field('billing_type', 20));
            $_SESSION['gift_old'] = [
                'gift_id'      => (int) ($_POST['gift_id'] ?? 0),
                'buyer_name'   => $field('buyer_name', 150),
                'buyer_email'  => $field('buyer_email', 190),
                'buyer_phone'  => $field('buyer_phone', 30),
                'buyer_cpf'    => $field('buyer_cpf', 20),
                'billing_type' => in_array($billing, self::BILLING_TYPES, true) ? $billing : 'PIX',
                'message'      => $field('message', 255),
                'status'       => $status,
                'detail'       => $detail,
            ];
        }
        $this->redirectHome('presentes', $status);
    }

    /**
     * O POST veio de uma página do próprio site? (Origin/Referer com o host do
     * base_url, com ou sem www). Usado para só guardar dados de um envio com
     * sessão expirada quando ele partiu do nosso formulário.
     */
    private function isSameOriginPost(): bool
    {
        $canonical = strtolower((string) parse_url((string) ($this->config['app']['base_url'] ?? ''), PHP_URL_HOST));
        $allowed   = array_filter([
            $canonical,
            $canonical !== '' ? 'www.' . $canonical : '',
            strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]),
        ]);

        $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($source === '' || $source === 'null') {
            $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }
        $host = strtolower((string) parse_url($source, PHP_URL_HOST));

        return $host !== '' && in_array($host, $allowed, true);
    }
}
