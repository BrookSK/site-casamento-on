<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de presentes.
 *
 * Quando o ASAAS está configurado ($paymentsEnabled), cada presente mostra um
 * formulário de contribuição com escolha da forma de pagamento (PIX, cartão ou
 * boleto). Caso contrário, mantém a prévia visual com botões desativados.
 *
 * Inclui um filtro (slider) por valor máximo, aplicado no front-end.
 *
 * @var array        $gifts
 * @var bool         $paymentsEnabled
 * @var string       $csrfField
 * @var array|null   $currentGuest
 * @var string|null  $giftStatus
 * @var array        $app
 */

$gifts           = $gifts ?? [];
$paymentsEnabled = $paymentsEnabled ?? false;
$csrfField       = $csrfField ?? '';
$currentGuest    = $currentGuest ?? null;
$giftStatus      = $giftStatus ?? null;
$baseUrl         = $app['base_url'] ?? '';

// Preços em centavos (para o filtro). Só habilita o slider se houver preços > 0.
$cents = array_map(static fn ($g): int => (int) ($g['price_cents'] ?? 0), $gifts);
$cents = array_filter($cents, static fn (int $c): int => $c);
$maxCents = $cents !== [] ? max($cents) : 0;
$minCents = $cents !== [] ? min($cents) : 0;
$showFilter = $maxCents > 0 && count($gifts) > 1;

// Mensagens de retorno do fluxo de pagamento.
$giftMessages = [
    'erro'            => 'Não foi possível iniciar o pagamento. Tente novamente.',
    'erro-sessao'     => 'Sua sessão expirou. Recarregue a página e tente novamente.',
    'erro-nome'       => 'Informe o seu nome para presentear.',
    'erro-cpf'        => 'Para boleto, informe um CPF/CNPJ válido.',
    'erro-indisponivel' => 'Este presente não está mais disponível.',
    'erro-valor'      => 'Este presente ainda não tem um valor definido.',
];
$giftMsg = $giftStatus !== null ? ($giftMessages[$giftStatus] ?? null) : null;
?>
<section class="section section--gifts" id="presentes" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Com carinho</p>
            <h2 class="section__title">Lista de presentes</h2>
            <?php if ($paymentsEnabled): ?>
                <p class="section__intro">
                    Escolha um presente e contribua com carinho. O pagamento é
                    processado com segurança (PIX, cartão ou boleto).
                </p>
            <?php else: ?>
                <p class="section__intro">
                    Prévia visual da futura lista de presentes. As compras ainda
                    <strong>não estão disponíveis</strong> nesta etapa.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($giftMsg !== null): ?>
            <p class="flash flash--error" style="text-align:center"><?= View::e($giftMsg) ?></p>
        <?php endif; ?>

        <?php if ($showFilter): ?>
            <div class="gift-filter" data-gift-filter
                 data-min="<?= (int) $minCents ?>" data-max="<?= (int) $maxCents ?>">
                <div class="gift-filter__head">
                    <label for="gift-range">Escolha pelo seu orçamento</label>
                    <span class="gift-filter__value" data-gift-filter-label>
                        Até R$ <?= number_format($maxCents / 100, 2, ',', '.') ?>
                    </span>
                </div>
                <input type="range" id="gift-range"
                       min="<?= (int) $minCents ?>" max="<?= (int) $maxCents ?>"
                       value="<?= (int) $maxCents ?>" step="1000"
                       data-gift-range
                       aria-label="Valor máximo do presente">
                <div class="gift-filter__scale">
                    <span>R$ <?= number_format($minCents / 100, 2, ',', '.') ?></span>
                    <span>R$ <?= number_format($maxCents / 100, 2, ',', '.') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="gift-grid" data-gift-grid>
            <?php foreach ($gifts as $gift): ?>
                <?php
                $priceCents = (int) ($gift['price_cents'] ?? 0);
                $giftId     = (int) ($gift['id'] ?? 0);
                $canBuy     = $paymentsEnabled && $giftId > 0 && $priceCents > 0;
                ?>
                <article class="gift-card" data-gift-card data-price="<?= $priceCents ?>">
                    <div class="gift-card__icon" aria-hidden="true">&#127873;</div>
                    <h3 class="gift-card__name"><?= View::e($gift['name'] ?? 'Presente') ?></h3>
                    <p class="gift-card__price"><?= View::e($gift['price'] ?? 'R$ ---') ?></p>

                    <?php if ($canBuy): ?>
                        <button class="btn btn--outline" type="button"
                                data-gift-toggle="gift-form-<?= $giftId ?>">
                            Presentear
                        </button>

                        <form class="gift-form" id="gift-form-<?= $giftId ?>" hidden
                              method="post"
                              action="<?= View::e(View::url('/presentear', $baseUrl)) ?>">
                            <?= $csrfField ?>
                            <input type="hidden" name="gift_id" value="<?= $giftId ?>">

                            <div class="field">
                                <label for="buyer_name_<?= $giftId ?>">Seu nome</label>
                                <input type="text" id="buyer_name_<?= $giftId ?>" name="buyer_name"
                                       value="<?= View::e((string) ($currentGuest['name'] ?? '')) ?>" required>
                            </div>

                            <div class="field">
                                <label for="buyer_email_<?= $giftId ?>">E-mail (opcional)</label>
                                <input type="email" id="buyer_email_<?= $giftId ?>" name="buyer_email"
                                       value="<?= View::e((string) ($currentGuest['email'] ?? '')) ?>">
                            </div>

                            <div class="field">
                                <label for="billing_<?= $giftId ?>">Forma de pagamento</label>
                                <select id="billing_<?= $giftId ?>" name="billing_type"
                                        data-gift-billing="<?= $giftId ?>">
                                    <option value="PIX">PIX</option>
                                    <option value="CREDIT_CARD">Cartão de crédito</option>
                                    <option value="BOLETO">Boleto</option>
                                </select>
                            </div>

                            <div class="field" data-gift-cpf="<?= $giftId ?>" hidden>
                                <label for="buyer_cpf_<?= $giftId ?>">CPF/CNPJ (necessário para boleto)</label>
                                <input type="text" id="buyer_cpf_<?= $giftId ?>" name="buyer_cpf"
                                       inputmode="numeric" placeholder="Somente números">
                            </div>

                            <div class="field">
                                <label for="buyer_msg_<?= $giftId ?>">Mensagem (opcional)</label>
                                <input type="text" id="buyer_msg_<?= $giftId ?>" name="message"
                                       maxlength="255" placeholder="Uma mensagem para o casal">
                            </div>

                            <button class="btn btn--primary" type="submit">Continuar para o pagamento</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn--outline" type="button" disabled aria-disabled="true">
                            Em breve
                        </button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($showFilter): ?>
            <p class="gift-empty" data-gift-empty hidden>Nenhum presente dentro desse valor.</p>
        <?php endif; ?>

        <?php if (!$paymentsEnabled): ?>
            <p class="gift-note">
                <em>Os presentes acima são provisórios e meramente ilustrativos.</em>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php if ($paymentsEnabled): ?>
<script>
// Mostra/oculta o formulário de cada presente e o campo de CPF (só p/ boleto).
document.querySelectorAll('[data-gift-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-gift-toggle');
        var form = document.getElementById(id);
        if (form) { form.hidden = !form.hidden; }
    });
});
document.querySelectorAll('[data-gift-billing]').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var id = sel.getAttribute('data-gift-billing');
        var cpf = document.querySelector('[data-gift-cpf="' + id + '"]');
        if (cpf) { cpf.hidden = sel.value !== 'BOLETO'; }
    });
});
</script>
<?php endif; ?>
