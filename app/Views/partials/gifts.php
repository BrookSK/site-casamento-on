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
            <div class="gift-filterbar" data-gift-filter
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
                // Presente já escolhido (pago) ou reservado durante um pagamento.
                $taken      = (bool) ($gift['taken'] ?? false);
                $image      = trim((string) ($gift['image'] ?? ''));
                // Só pode comprar se há pagamento ativo, id/preço válidos E não
                // estiver indisponível (reservado/comprado).
                $canBuy     = $paymentsEnabled && $giftId > 0 && $priceCents > 0 && !$taken;
                // Resolve a URL da imagem (aceita caminho absoluto, http ou nome
                // relativo em assets/img).
                $imageUrl = '';
                if ($image !== '') {
                    $imageUrl = (str_starts_with($image, 'http') || str_starts_with($image, '/'))
                        ? $image
                        : rtrim($baseUrl, '/') . '/assets/img/' . ltrim($image, '/');
                }
                ?>
                <article class="gift-card<?= $taken ? ' gift-card--taken' : '' ?>"
                         data-gift-card data-price="<?= $priceCents ?>"
                         <?= $taken ? 'data-taken="1"' : '' ?>>
                    <?php if ($taken): ?>
                        <span class="gift-card__ribbon">Presente já escolhido</span>
                    <?php endif; ?>

                    <div class="gift-card__media">
                        <?php if ($imageUrl !== ''): ?>
                            <img class="gift-card__img" src="<?= View::e($imageUrl) ?>"
                                 alt="<?= View::e($gift['name'] ?? 'Presente') ?>" loading="lazy">
                        <?php else: ?>
                            <div class="gift-card__icon" aria-hidden="true">&#127873;</div>
                        <?php endif; ?>
                    </div>

                    <h3 class="gift-card__name"><?= View::e($gift['name'] ?? 'Presente') ?></h3>
                    <p class="gift-card__price"><?= View::e($gift['price'] ?? 'R$ ---') ?></p>

                    <?php if ($taken): ?>
                        <button class="btn btn--outline" type="button" disabled aria-disabled="true">
                            Presente já escolhido
                        </button>
                    <?php elseif ($canBuy): ?>
                        <button class="btn btn--outline" type="button"
                                data-gift-open
                                data-gift-id="<?= $giftId ?>"
                                data-gift-name="<?= View::e($gift['name'] ?? 'Presente') ?>"
                                data-gift-price="<?= View::e($gift['price'] ?? '') ?>">
                            Presentear
                        </button>
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
<!-- Modal de contribuição: um só, reutilizado para qualquer presente. -->
<div class="gift-modal" data-gift-modal hidden>
    <div class="gift-modal__overlay" data-gift-close></div>
    <div class="gift-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gift-modal-title">
        <button class="gift-modal__close" type="button" data-gift-close aria-label="Fechar">&times;</button>

        <div class="gift-modal__aside">
            <div class="gift-modal__head">
                <p class="gift-modal__eyebrow">Presentear</p>
                <h3 class="gift-modal__title" id="gift-modal-title" data-gift-modal-name>Presente</h3>
                <p class="gift-modal__price" data-gift-modal-price>R$ ---</p>
            </div>
            <p class="gift-modal__intro">
                Preencha os dados ao lado para contribuir. O pagamento é
                processado com segurança &mdash; você escolhe entre PIX, cartão
                de crédito ou boleto.
            </p>
        </div>

        <form class="gift-modal__form" method="post"
              action="<?= View::e(View::url('/presentear', $baseUrl)) ?>">
            <?= $csrfField ?>
            <input type="hidden" name="gift_id" value="" data-gift-modal-id>

            <div class="field field--full">
                <label for="gm_name">Seu nome</label>
                <input type="text" id="gm_name" name="buyer_name"
                       value="<?= View::e((string) ($currentGuest['name'] ?? '')) ?>" required>
            </div>

            <div class="field">
                <label for="gm_email">E-mail (opcional)</label>
                <input type="email" id="gm_email" name="buyer_email"
                       value="<?= View::e((string) ($currentGuest['email'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="gm_billing">Forma de pagamento</label>
                <select id="gm_billing" name="billing_type" data-gift-modal-billing>
                    <option value="PIX">PIX (aprovação na hora)</option>
                    <option value="CREDIT_CARD">Cartão de crédito</option>
                    <option value="BOLETO">Boleto bancário</option>
                </select>
            </div>

            <div class="field field--full" data-gift-modal-cpf hidden>
                <label for="gm_cpf">CPF/CNPJ</label>
                <input type="text" id="gm_cpf" name="buyer_cpf" inputmode="numeric"
                       placeholder="Somente números">
                <small class="field__hint">Necessário para gerar o boleto.</small>
            </div>

            <div class="field field--full">
                <label for="gm_msg">Mensagem (opcional)</label>
                <input type="text" id="gm_msg" name="message" maxlength="255"
                       placeholder="Uma mensagem carinhosa para o casal">
            </div>

            <button class="btn btn--primary gift-modal__submit" type="submit">
                Continuar para o pagamento
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var modal   = document.querySelector('[data-gift-modal]');
    if (!modal) { return; }
    var nameEl  = modal.querySelector('[data-gift-modal-name]');
    var priceEl = modal.querySelector('[data-gift-modal-price]');
    var idEl    = modal.querySelector('[data-gift-modal-id]');
    var billing = modal.querySelector('[data-gift-modal-billing]');
    var cpf     = modal.querySelector('[data-gift-modal-cpf]');

    var openModal = function (btn) {
        idEl.value = btn.getAttribute('data-gift-id') || '';
        nameEl.textContent = btn.getAttribute('data-gift-name') || 'Presente';
        priceEl.textContent = btn.getAttribute('data-gift-price') || '';
        if (cpf) { cpf.hidden = billing.value !== 'BOLETO'; }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };
    var closeModal = function () {
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-gift-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });
    modal.querySelectorAll('[data-gift-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
    });
    if (billing && cpf) {
        billing.addEventListener('change', function () {
            cpf.hidden = billing.value !== 'BOLETO';
        });
    }
})();
</script>
<?php endif; ?>
