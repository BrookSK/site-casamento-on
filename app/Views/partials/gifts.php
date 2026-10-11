<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de presentes — APENAS VISUAL.
 * Sem carrinho, sem pagamento, sem persistência.
 *
 * @var array $gifts
 */

$gifts = $gifts ?? [];
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

        <div class="gift-grid">
            <?php foreach ($gifts as $gift): ?>
                <article class="gift-card">
                    <div class="gift-card__icon" aria-hidden="true">&#127873;</div>
                    <h3 class="gift-card__name"><?= View::e($gift['name'] ?? 'Presente') ?></h3>
                    <p class="gift-card__price"><?= View::e($gift['price'] ?? 'R$ ---') ?></p>
                    <button class="btn btn--outline" type="button" disabled aria-disabled="true">
                        Em breve
                    </button>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="gift-note">
            <em>Os presentes acima são provisórios e meramente ilustrativos.</em>
        </p>
    </div>
</section>
