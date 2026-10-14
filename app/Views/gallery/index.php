<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Galeria de Fotos — página independente, organizada por álbuns.
 *
 * @var array $app
 * @var array $albums  Lista de álbuns com título e fotos resolvidas.
 */

$albums = $albums ?? [];
?>
<section class="section section--gallery-page" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Recordações</p>
            <h2 class="section__title">Galeria de Fotos <span class="heart heart--soft" aria-hidden="true">&#10084;</span></h2>
            <p class="section__intro">
                Momentos registrados ao longo da nossa jornada, organizados por ocasião.
            </p>
        </div>

        <?php if ($albums === []): ?>
            <p class="gallery-empty">
                Em breve adicionaremos as fotos dos nossos álbuns. Volte mais tarde!
            </p>
        <?php else: ?>
            <?php foreach ($albums as $album): ?>
                <div class="album" data-album>
                    <div class="album__head">
                        <h3 class="album__title"><?= View::e($album['title']) ?></h3>
                        <?php if (!empty($album['subtitle'])): ?>
                            <p class="album__subtitle"><?= View::e((string) $album['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($album['description'])): ?>
                            <p class="album__desc"><?= nl2br(View::e((string) $album['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="album__grid">
                        <?php foreach ($album['photos'] as $idx => $photo): ?>
                            <button type="button" class="album__photo"
                                    data-album-photo
                                    data-index="<?= (int) $idx ?>"
                                    data-src="<?= View::e($photo['url']) ?>"
                                    data-alt="<?= View::e($photo['alt']) ?>"
                                    aria-label="Ampliar <?= View::e($photo['alt']) ?>">
                                <img src="<?= View::e($photo['url']) ?>"
                                     alt="<?= View::e($photo['alt']) ?>" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($albums !== []): ?>
<!-- Lightbox: ampliação e navegação dentro do mesmo álbum -->
<div class="lightbox" data-lightbox hidden>
    <div class="lightbox__backdrop" data-lightbox-close></div>

    <button class="lightbox__btn lightbox__btn--close" type="button"
            data-lightbox-close aria-label="Fechar">&times;</button>

    <div class="lightbox__stage">
        <button class="lightbox__btn lightbox__btn--prev" type="button"
                data-lightbox-prev aria-label="Foto anterior">&#8249;</button>

        <figure class="lightbox__frame">
            <div class="lightbox__imgwrap">
                <img class="lightbox__img" data-lightbox-img src="" alt="">
            </div>
            <figcaption class="lightbox__caption">
                <span class="lightbox__caption-text" data-lightbox-caption></span>
                <span class="lightbox__counter" data-lightbox-counter></span>
            </figcaption>
        </figure>

        <button class="lightbox__btn lightbox__btn--next" type="button"
                data-lightbox-next aria-label="Próxima foto">&#8250;</button>
    </div>
</div>
<?php endif; ?>
