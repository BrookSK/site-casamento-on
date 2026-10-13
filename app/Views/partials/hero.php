<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Hero — fotografia do casal em destaque + nomes + data.
 * Nomes e imagem editáveis via painel (Content), com fallback.
 *
 * @var array          $app
 * @var array          $wedding
 * @var \App\Core\Content $content
 */

$baseUrl   = $app['base_url'] ?? '';
$bride     = $content->text('bride', (string) ($wedding['bride'] ?? 'Noiva'));
$groom     = $content->text('groom', (string) ($wedding['groom'] ?? 'Noivo'));
$dateLabel = $content->text('date_label', (string) ($wedding['date_label'] ?? 'Data a definir'));
$dateIso   = $wedding['date_iso'] ?? '';

// Imagem de destaque (fixa: hero.jpg/.png/.webp em assets/img).
$hero    = View::image('hero', $baseUrl);
$heroImg = $hero['url'];
$hasHero = $hero['exists'];
?>
<section class="hero <?= $hasHero ? 'hero--has-image' : 'hero--placeholder' ?>" data-reveal>
    <?php if ($hasHero): ?>
        <img class="hero__bg" src="<?= View::e($heroImg) ?>"
             alt="<?= View::e($bride) ?> e <?= View::e($groom) ?> de mãos dadas em um jardim.">
    <?php else: ?>
        <div class="hero__bg hero__bg--placeholder" role="img"
             aria-label="Espaço reservado para a foto principal do casal.">
            <span class="placeholder-note">Adicione a foto de capa pelo painel</span>
        </div>
    <?php endif; ?>

    <div class="hero__overlay"></div>

    <div class="hero__content container">
        <p class="hero__eyebrow">Vamos nos casar</p>
        <h1 class="hero__names">
            <span><?= View::e($bride) ?></span>
            <span class="hero__amp">&amp;</span>
            <span><?= View::e($groom) ?></span>
        </h1>

        <p class="hero__date">
            <time<?= $dateIso !== '' ? ' datetime="' . View::e($dateIso) . '"' : '' ?>>
                <?= View::e($dateLabel) ?>
            </time>
        </p>

        <!-- Contagem regressiva: apenas visual (JS). Some se a data for placeholder inválida. -->
        <div class="countdown" data-countdown data-target="<?= View::e($dateIso) ?>" hidden>
            <div class="countdown__item"><span data-cd="days">00</span><small>dias</small></div>
            <div class="countdown__item"><span data-cd="hours">00</span><small>horas</small></div>
            <div class="countdown__item"><span data-cd="minutes">00</span><small>min</small></div>
            <div class="countdown__item"><span data-cd="seconds">00</span><small>seg</small></div>
        </div>

        <a class="btn btn--light hero__scroll" href="#historia">Conheça nossa história</a>
    </div>
</section>
