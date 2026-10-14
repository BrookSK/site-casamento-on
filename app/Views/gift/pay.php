<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Tela de pagamento do presente (após criar a cobrança no ASAAS).
 *
 * @var array                 $app
 * @var array<string, mixed>  $gift
 * @var string                $billingType  PIX | CREDIT_CARD | BOLETO
 * @var float                 $value
 * @var string|null           $invoiceUrl
 * @var string|null           $bankSlipUrl
 * @var string|null           $pixPayload   código copia e cola do PIX
 * @var string|null           $pixQr        imagem do QR Code em base64
 */

$baseUrl   = $app['base_url'] ?? '';
$giftName  = (string) ($gift['name'] ?? 'Presente');
$valueText = 'R$ ' . number_format((float) $value, 2, ',', '.');
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
                <h3 class="pay-card__title">Pague com Cartão de Crédito</h3>
                <?php if ($invoiceUrl): ?>
                    <p>Você será direcionado para a página segura de pagamento do ASAAS para informar os dados do cartão.</p>
                    <p>
                        <a class="btn btn--primary" href="<?= View::e($invoiceUrl) ?>"
                           target="_blank" rel="noopener">Pagar com cartão</a>
                    </p>
                <?php else: ?>
                    <p>Não foi possível abrir o pagamento por cartão agora. Tente novamente em instantes.</p>
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
</script>
