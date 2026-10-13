<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de presentes — APENAS VISUAL.
 * Sem carrinho, sem pagamento, sem persistência.
 * Inclui um filtro (slider) por valor máximo, aplicado no front-end.
 *
 * @var array $gifts
 */

$gifts = $gifts ?? [];

// Preços em centavos (para o filtro). Só habilita o slider se houver preços > 0.
$cents = array_map(static fn ($g): int => (int) ($g['price_cents'] ?? 0), $gifts);
$cents = array_filter($cents, static fn (int $c): int => $c);
$maxCents = $cents !== [] ? max($cents) : 0;
$minCents = $cents !== [] ? min($cents) : 0;
$showFilter = $maxCents > 0 && count($gifts) > 1;
?>
<section class="section section--gifts" id="presentes" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Com carinho</p>
            <h2 class="section__title">Lista de presentes</h2>
            <p class="section__intro">
                Prévia visual da futura lista de presentes. As compras ainda
                <strong>não estão disponíveis</strong> nesta etapa.
            </p>
        </div>

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
                <?php $priceCents = (int) ($gift['price_cents'] ?? 0); ?>
                <article class="gift-card" data-gift-card data-price="<?= $priceCents ?>">
                    <div class="gift-card__icon" aria-hidden="true">&#127873;</div>
                    <h3 class="gift-card__name"><?= View::e($gift['name'] ?? 'Presente') ?></h3>
                    <p class="gift-card__price"><?= View::e($gift['price'] ?? 'R$ ---') ?></p>
                    <button class="btn btn--outline" type="button" disabled aria-disabled="true">
                        Em breve
                    </button>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($showFilter): ?>
            <p class="gift-empty" data-gift-empty hidden>Nenhum presente dentro desse valor.</p>
        <?php endif; ?>

        <p class="gift-note">
            <em>Os presentes acima são provisórios e meramente ilustrativos.</em>
        </p>
    </div>
</section>
