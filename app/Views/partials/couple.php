<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Apresentação do casal — breve introdução / história.
 * Conteúdo editável via painel (Content), com fallback para os textos padrão.
 *
 * @var array          $app
 * @var array          $wedding
 * @var \App\Core\Content $content
 */

$baseUrl = $app['base_url'] ?? '';
$bride   = $content->text('bride', (string) ($wedding['bride'] ?? 'Noiva'));
$groom   = $content->text('groom', (string) ($wedding['groom'] ?? 'Noivo'));

$couple = View::image('couple-1', $baseUrl);

$historyTitle = $content->text('history_title', 'Dois caminhos, uma só direção');
$historyText  = $content->text('history_text', '');

$facts = [
    [$content->text('fact1_label', 'Onde tudo começou'), $content->text('fact1_value', 'A definir')],
    [$content->text('fact2_label', 'O pedido'),          $content->text('fact2_value', 'A definir')],
    [$content->text('fact3_label', 'Nosso lema'),        $content->text('fact3_value', 'A definir')],
];
?>
<section class="section section--couple" id="historia" data-reveal>
    <div class="container couple">
        <div class="couple__media">
            <?php if ($couple['exists']): ?>
                <img src="<?= View::e($couple['url']) ?>"
                     alt="Retrato de <?= View::e($bride) ?> e <?= View::e($groom) ?>."
                     loading="lazy">
            <?php else: ?>
                <div class="media-placeholder media-placeholder--portrait" role="img"
                     aria-label="Espaço reservado para uma foto do casal.">
                    <span class="placeholder-note">Adicione a foto pelo painel</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="couple__text">
            <p class="section__eyebrow">Nossa história</p>
            <h2 class="section__title"><?= View::e($historyTitle) ?></h2>

            <?php if ($historyText !== ''): ?>
                <p class="couple__lead"><?= nl2br(View::e($historyText)) ?></p>
            <?php else: ?>
                <p class="couple__lead">
                    Em breve, esta seção contará como <?= View::e($bride) ?> e
                    <?= View::e($groom) ?> se conheceram e decidiram seguir juntos.
                </p>
            <?php endif; ?>

            <ul class="couple__facts">
                <?php foreach ($facts as [$label, $value]): ?>
                    <li><strong><?= View::e($label) ?></strong><span><?= View::e($value) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
