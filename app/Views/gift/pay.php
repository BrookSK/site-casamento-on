<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\View;

/**
 * Tela de pagamento do presente.
 *
 * PIX e boleto: a cobrança já foi criada no store(); aqui exibimos QR/boleto.
 * Cartão: exibimos o FORMULÁRIO do cartão (checkout transparente); ao enviar,
 * o payCard() cria a cobrança e esta tela mostra o resultado.
 *
 * @var array                 $app
 * @var array<string, mixed>  $gift
 * @var string                $billingType  PIX | CREDIT_CARD | BOLETO
 * @var float                 $value
 * @var string|null           $invoiceUrl
 * @var string|null           $bankSlipUrl
 * @var string|null           $pixPayload
 * @var string|null           $pixQr
 * @var bool|null             $cardApproved null = ainda não processado
 * @var array|null            $cardForm     dados do comprador (p/ o form do cartão)
 * @var string|null           $cardError    null | 'invalid' | 'declined'
 */

$baseUrl   = $app['base_url'] ?? '';
$giftName  = (string) ($gift['name'] ?? 'Presente');
$valueText = 'R$ ' . number_format((float) $value, 2, ',', '.');

$cardForm  = $cardForm ?? null;
$cardError = $cardError ?? null;
?>
<section class="section section--pay" id="pagamento" data-reveal>
    <div class="container container--narrow">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Quase lá</p>
            <h2 class="section__title">Finalize o seu presente</h2>
            <p class="section__intro">
                <strong><?= View::e($giftName) ?></strong> — <?= View::e($valueText) ?>
            </p>
        </div>

        <div class="pay-card">
            <?php if ($billingType === 'PIX'): ?>
                <h3 class="pay-card__title">Pague com PIX</h3>
                <?php if ($pixQr): ?>
                    <div class="pay-card__qr">
                        <img src="data:image/png;base64,<?= View::e($pixQr) ?>"
                             alt="QR Code PIX" width="240" height="240">
                    </div>
                <?php endif; ?>

                <?php if ($pixPayload): ?>
                    <div class="pay-copy-wrap">
                        <label for="pix-code">Ou use o PIX copia e cola</label>
                        <div class="pay-copy">
                            <input type="text" id="pix-code" readonly
                                   value="<?= View::e($pixPayload) ?>">
                            <button type="button" class="btn btn--outline"
                                    data-copy-target="#pix-code">Copiar</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$pixQr && !$pixPayload): ?>
                    <p>Não foi possível gerar o QR Code agora. Tente novamente em instantes.</p>
                <?php endif; ?>

                <p class="pay-note">
                    Assim que o pagamento for identificado, a confirmação é
                    processada automaticamente. Você pode fechar esta página
                    após efetuar o pagamento.
                </p>

            <?php elseif ($billingType === 'BOLETO'): ?>
                <h3 class="pay-card__title">Pague com Boleto</h3>
                <?php $slip = $bankSlipUrl ?: $invoiceUrl; ?>
                <?php if ($slip): ?>
                    <p>Seu boleto foi gerado. Clique abaixo para visualizar, imprimir ou copiar a linha digitável.</p>
                    <p>
                        <a class="btn btn--primary" href="<?= View::e($slip) ?>"
                           target="_blank" rel="noopener">Abrir boleto</a>
                    </p>
                <?php else: ?>
                    <p>Não foi possível obter o boleto agora. Tente novamente em instantes.</p>
                <?php endif; ?>

            <?php else: /* CREDIT_CARD */ ?>
                <?php $cardApproved = $cardApproved ?? null; ?>
                <?php if ($cardApproved === true): ?>
                    <div class="pay-card__success" aria-hidden="true">&#10003;</div>
                    <h3 class="pay-card__title">Pagamento aprovado!</h3>
                    <p>Recebemos a sua contribuição com o cartão de crédito.
                       Muito obrigado pelo carinho!</p>

                <?php elseif ($cardForm !== null): ?>
                    <!-- Formulário do cartão (checkout transparente). Os dados
                         vão por HTTPS ao servidor e são repassados ao ASAAS;
                         nada é armazenado pelo site. -->
                    <h3 class="pay-card__title">Dados do cartão de crédito</h3>

                    <?php if ($cardError === 'declined'): ?>
                        <p class="pay-card__alert">
                            O pagamento foi recusado. Confira os dados ou tente
                            outro cartão.
                        </p>
                    <?php elseif ($cardError === 'invalid'): ?>
                        <p class="pay-card__alert">
                            Verifique os dados do cartão e tente novamente.
                        </p>
                    <?php endif; ?>

                    <form class="pay-cardform" method="post"
                          action="<?= View::e(View::url('/presentear/cartao', $baseUrl)) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="gift_id" value="<?= (int) ($cardForm['gift_id'] ?? 0) ?>">
                        <input type="hidden" name="buyer_name" value="<?= View::e((string) ($cardForm['buyer_name'] ?? '')) ?>">
                        <input type="hidden" name="buyer_email" value="<?= View::e((string) ($cardForm['buyer_email'] ?? '')) ?>">
                        <input type="hidden" name="buyer_phone" value="<?= View::e((string) ($cardForm['buyer_phone'] ?? '')) ?>">
                        <input type="hidden" name="buyer_cpf" value="<?= View::e((string) ($cardForm['buyer_cpf'] ?? '')) ?>">
                        <input type="hidden" name="message" value="<?= View::e((string) ($cardForm['message'] ?? '')) ?>">

                        <div class="pay-field pay-field--full">
                            <label for="cc_holder">Nome impresso no cartão</label>
                            <input type="text" id="cc_holder" name="card_holder" required
                                   autocomplete="cc-name" placeholder="Como está no cartão">
                        </div>

                        <div class="pay-field pay-field--full">
                            <label for="cc_number">Número do cartão</label>
                            <input type="text" id="cc_number" name="card_number" required
                                   inputmode="numeric" autocomplete="cc-number"
                                   maxlength="23" placeholder="0000 0000 0000 0000" data-card-number>
                        </div>

                        <div class="pay-field">
                            <label for="cc_expiry">Validade</label>
                            <input type="text" id="cc_expiry" name="card_expiry" required
                                   inputmode="numeric" autocomplete="cc-exp"
                                   maxlength="7" placeholder="MM/AA" data-card-expiry>
                        </div>
                        <div class="pay-field">
                            <label for="cc_ccv">CVV</label>
                            <input type="text" id="cc_ccv" name="card_ccv" required
                                   inputmode="numeric" autocomplete="cc-csc"
                                   maxlength="4" placeholder="000" data-card-ccv>
                        </div>

                        <div class="pay-field">
                            <label for="cc_cep">CEP</label>
                            <input type="text" id="cc_cep" name="card_cep" required
                                   inputmode="numeric" autocomplete="postal-code"
                                   maxlength="9" placeholder="00000-000" data-card-cep>
                        </div>
                        <div class="pay-field">
                            <label for="cc_addr">Nº do endereço</label>
                            <input type="text" id="cc_addr" name="card_address_number" required
                                   inputmode="numeric" placeholder="123">
                        </div>

                        <button class="btn btn--primary pay-cardform__submit" type="submit">
                            Pagar <?= View::e($valueText) ?>
                        </button>
                        <p class="pay-cardform__note">
                            Pagamento processado com segurança. Seus dados do
                            cartão não são armazenados pelo site.
                        </p>
                    </form>

                <?php elseif ($invoiceUrl): ?>
                    <!-- Fallback: conta sem checkout transparente de cartão. -->
                    <h3 class="pay-card__title">Pague com Cartão de Crédito</h3>
                    <p>Para concluir com segurança, finalize o pagamento do cartão
                       pela página protegida do ASAAS.</p>
                    <p>
                        <a class="btn btn--primary" href="<?= View::e($invoiceUrl) ?>"
                           target="_blank" rel="noopener">Finalizar pagamento</a>
                    </p>
                <?php else: ?>
                    <h3 class="pay-card__title">Cartão de Crédito</h3>
                    <p>Não foi possível concluir o pagamento por cartão agora.
                       Tente novamente ou escolha outra forma de pagamento.</p>
                <?php endif; ?>
            <?php endif; ?>

            <p class="pay-back">
                <a href="<?= View::e(View::url('/', $baseUrl)) ?>#presentes">Voltar à lista de presentes</a>
            </p>
        </div>
    </div>
</section>

<script>
// Copiar o código PIX para a área de transferência.
document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var sel = btn.getAttribute('data-copy-target');
        var el = sel ? document.querySelector(sel) : null;
        if (!el) { return; }
        el.select();
        el.setSelectionRange(0, 99999);
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(el.value);
            } else {
                document.execCommand('copy');
            }
            var original = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(function () { btn.textContent = original; }, 2000);
        } catch (e) { /* silencioso */ }
    });
});

// Máscaras dos campos do cartão (formulário de pagamento por cartão).
(function () {
    var onlyDigits = function (s) { return (s || '').replace(/\D/g, ''); };
    var num = document.querySelector('[data-card-number]');
    if (num) {
        num.addEventListener('input', function () {
            var d = onlyDigits(num.value).slice(0, 19);
            num.value = d.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        });
    }
    var exp = document.querySelector('[data-card-expiry]');
    if (exp) {
        exp.addEventListener('input', function () {
            var d = onlyDigits(exp.value).slice(0, 4);
            exp.value = d.length >= 3 ? (d.slice(0, 2) + '/' + d.slice(2)) : d;
        });
    }
    var ccv = document.querySelector('[data-card-ccv]');
    if (ccv) {
        ccv.addEventListener('input', function () {
            ccv.value = onlyDigits(ccv.value).slice(0, 4);
        });
    }
    var cep = document.querySelector('[data-card-cep]');
    if (cep) {
        cep.addEventListener('input', function () {
            var d = onlyDigits(cep.value).slice(0, 8);
            cep.value = d.length >= 6 ? (d.slice(0, 5) + '-' + d.slice(5)) : d;
        });
    }
})();
</script>
