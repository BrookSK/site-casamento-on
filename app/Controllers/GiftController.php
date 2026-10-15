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
            $this->redirectHome('presentes', 'erro-sessao');
        }

        $giftId      = (int) ($_POST['gift_id'] ?? 0);
        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, self::BILLING_TYPES, true)) {
            $billingType = 'PIX';
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
            $this->redirectHome('presentes', 'erro-nome');
        }

        // Fluxo reaproveitado da confirmação de presença (ponto 6): quando o
        // visitante NÃO está logado, exigimos CPF, e-mail e telefone para criar
        // automaticamente a conta e vincular a compra. Convidado já autenticado
        // segue direto com a conta atual.
        $isLogged  = GuestAuth::check();
        $welcome   = null; // dados para enviar o link de 1ª senha (conta nova)
        $guestId   = $isLogged ? ($loggedGuest['id'] ?? null) : null;

        if (!$isLogged) {
            if ($buyerMail === '' || !filter_var($buyerMail, FILTER_VALIDATE_EMAIL)) {
                $this->redirectHome('presentes', 'erro-email');
            }
            if (preg_replace('/\D+/', '', $buyerPhone) === '') {
                $this->redirectHome('presentes', 'erro-telefone');
            }
            if (preg_replace('/\D+/', '', $buyerCpf) === '') {
                $this->redirectHome('presentes', 'erro-cpf');
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

                // Login automático: mantém a pessoa autenticada no fluxo.
                GuestAuth::login($welcome['guest']);
            } catch (\RuntimeException $e) {
                // E-mail e telefone pertencem a convidados diferentes.
                if ($e->getMessage() === 'conflict') {
                    $this->redirectHome('presentes', 'erro-conflito');
                }
                error_log('[Gift] findOrCreateForPurchase: ' . $e->getMessage());
                $this->redirectHome('presentes', 'erro');
            } catch (Throwable $e) {
                error_log('[Gift] findOrCreateForPurchase: ' . $e->getMessage());
                $this->redirectHome('presentes', 'erro');
            }
        } else {
            // Convidado logado: boleto e cartão exigem CPF/CNPJ no ASAAS.
            if (($billingType === 'BOLETO' || $billingType === 'CREDIT_CARD')
                && preg_replace('/\D+/', '', $buyerCpf) === '') {
                $this->redirectHome('presentes', 'erro-cpf');
            }
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

            // CARTÃO: não cria a cobrança agora. O presente fica reservado e
            // abrimos a tela de pagamento com o FORMULÁRIO do cartão. A cobrança
            // só é criada quando esse formulário for enviado (payCard()).
            if ($billingType === 'CREDIT_CARD') {
                // Conta nova sem senha: já envia as boas-vindas (link de senha).
                if ($welcome !== null) {
                    $this->sendWelcome($welcome);
                }
                $this->view('gift/pay', [
                    'pageTitle'   => 'Pagamento do presente',
                    'bodyClass'   => 'page-light',
                    'gift'        => $gift,
                    'billingType' => 'CREDIT_CARD',
                    'value'       => $value,
                    'cardApproved'=> null,
                    // Dados repassados ao formulário do cartão (passo seguinte).
                    'cardForm'    => [
                        'gift_id'     => $giftId,
                        'buyer_name'  => $buyerName,
                        'buyer_email' => $buyerMail,
                        'buyer_phone' => $buyerPhone,
                        'buyer_cpf'   => $buyerCpf,
                        'message'     => $message,
                    ],
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
        $buyerName  = trim((string) ($_POST['buyer_name'] ?? ''));
        $buyerMail  = trim((string) ($_POST['buyer_email'] ?? ''));
        $buyerPhone = trim((string) ($_POST['buyer_phone'] ?? ''));
        $buyerCpf   = trim((string) ($_POST['buyer_cpf'] ?? ''));
        $message    = trim((string) ($_POST['message'] ?? '')) ?: null;
        $guestId    = GuestAuth::user()['id'] ?? null;

        if ($buyerName === '' || preg_replace('/\D+/', '', $buyerCpf) === '') {
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
            $gift = $giftModel->find($giftId);
            if ($gift === null) {
                $this->redirectHome('presentes', 'erro-indisponivel');
            }

            // Garante a reserva antes de cobrar:
            //  - 1ª tentativa (veio do store): já está 'reserved' — segue.
            //  - Nova tentativa após recusa: está 'available' — reserva agora.
            //  - 'purchased' / reservado por outra pessoa: indisponível.
            $giftStatus = (string) ($gift['status'] ?? '');
            if ($giftStatus === 'available') {
                if (!$giftModel->reserveIfAvailable($giftId)) {
                    $this->redirectHome('presentes', 'erro-indisponivel');
                }
            } elseif ($giftStatus !== 'reserved') {
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
                    'phone'         => preg_replace('/\D+/', '', $buyerPhone) ?: null,
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
                    $purchases->updateStatus((int) $purchase['id'], 'paid');
                }
                $giftModel->markPurchased($giftId);
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
            // Cartão recusado: NÃO há pagamento em andamento, então o presente
            // volta a ficar DISPONÍVEL imediatamente (sem exigir ação do admin).
            // A própria pessoa pode tentar outro cartão: o reenvio reserva de
            // novo antes de cobrar. Se ela desistir, o presente já está livre.
            try {
                $giftModel->releaseIfReserved($giftId);
            } catch (Throwable $re) {
                error_log('[Gift] payCard release: ' . $re->getMessage());
            }
            $this->renderCardForm($giftId, $buyerName, $buyerMail, $buyerPhone, $buyerCpf, $message, 'declined');
            return;
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
}
