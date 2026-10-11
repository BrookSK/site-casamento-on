<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Apresentação do casal — breve introdução / história.
 *
 * @var array $app
 * @var array $wedding
 */

$baseUrl = $app['base_url'] ?? '';
$bride   = $wedding['bride'] ?? 'Noiva';
$groom   = $wedding['groom'] ?? 'Noivo';

$couple    = View::image('couple-1', $baseUrl);
$coupleImg = $couple['url'];
$hasCouple = $couple['exists'];
?>
<section class="section section--couple" id="historia" data-reveal>
    <div class="container couple">
        <div class="couple__media">
            <?php if ($hasCouple): ?>
                <img src="<?= View::e($coupleImg) ?>"
                     alt="Retrato de <?= View::e($bride) ?> e <?= View::e($groom) ?>."
                     loading="lazy" width="700" height="900">
            <?php else: ?>
                <div class="media-placeholder media-placeholder--portrait" role="img"
                     aria-label="Espaço reservado para uma foto do casal (couple-1.jpg).">
                    <span class="placeholder-note">Adicione <code>couple-1.jpg</code></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="couple__text">
            <p class="section__eyebrow">Nossa história</p>
            <h2 class="section__title">Dois caminhos, uma só direção</h2>

            <!-- TEXTO PROVISÓRIO: substituir pela história real do casal. -->
            <p class="couple__lead">
                Este é um texto provisório. Em breve, esta seção contará como
                <?= View::e($bride) ?> e <?= View::e($groom) ?> se conheceram e
                decidiram seguir juntos.
            </p>
            <p>
                <em>(Conteúdo meramente ilustrativo &mdash; nenhuma informação real
                foi inventada. Será preenchido posteriormente.)</em>
            </p>

            <ul class="couple__facts">
                <li><strong>Onde tudo começou</strong><span>A definir</span></li>
                <li><strong>O pedido</strong><span>A definir</span></li>
                <li><strong>Nosso lema</strong><span>A definir</span></li>
            </ul>
        </div>
    </div>
</section>
