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

// Composição de fotos sobrepostas (estilo fotos impressas espalhadas).
// Nomes fixos story-1..story-4 em public/assets/img. Fallback: couple-1.
$storyPhotos = [
    ['name' => 'story-1', 'alt' => 'Momento especial de ' . $bride . ' e ' . $groom . '.'],
    ['name' => 'story-2', 'alt' => 'Retrato de ' . $bride . '.'],
    ['name' => 'story-3', 'alt' => 'Retrato de ' . $groom . '.'],
    ['name' => 'story-4', 'alt' => 'Uma paisagem marcante para o casal.'],
];
$storyResolved = [];
$hasStory = false;
foreach ($storyPhotos as $sp) {
    $img = View::image($sp['name'], $baseUrl);
    if ($img['exists']) { $hasStory = true; }
    $storyResolved[] = ['img' => $img, 'alt' => $sp['alt'], 'name' => $sp['name']];
}

// Fallback: foto única antiga, caso nenhuma story-N exista ainda.
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
            <?php if ($hasStory): ?>
                <!-- Fotos físicas sobrepostas (borda, rotação e sombra via CSS).
                     Destaque no hover (desktop) e no toque (mobile) via JS. -->
                <div class="photo-stack" data-photo-stack>
                    <?php foreach ($storyResolved as $i => $r): ?>
                        <figure class="photo-stack__item photo-stack__item--<?= $i + 1 ?>"
                                data-photo-stack-item tabindex="0">
                            <?php if ($r['img']['exists']): ?>
                                <img src="<?= View::e($r['img']['url']) ?>"
                                     alt="<?= View::e($r['alt']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="photo-stack__placeholder" role="img"
                                      aria-label="Espaço reservado para uma foto do casal.">
                                    <code><?= View::e($r['name']) ?></code>
                                </span>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($couple['exists']): ?>
                <img src="<?= View::e($couple['url']) ?>"
                     alt="Retrato de <?= View::e($bride) ?> e <?= View::e($groom) ?>."
                     loading="lazy">
            <?php else: ?>
                <div class="media-placeholder media-placeholder--portrait" role="img"
                     aria-label="Espaço reservado para uma foto do casal.">
                    <span class="placeholder-note">Adicione a foto pelo painel (story-1 a story-4)</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="couple__text">
            <p class="section__eyebrow">Nossa história <span class="heart heart--soft" aria-hidden="true">&#10084;</span></p>
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
                    <li>
                        <strong class="couple__facts-title"><?= View::e($label) ?></strong>
                        <span class="couple__facts-desc"><?= View::e($value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
