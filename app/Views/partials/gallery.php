<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Galeria — mosaico com as fotos do ensaio (imagens fixas em assets/img).
 *
 * @var array $app
 */

$baseUrl = $app['base_url'] ?? '';

// Fotos do ensaio (nome de arquivo fixo: gallery-N.jpg/.png/.webp).
$photos = [
    ['name' => 'gallery-1', 'alt' => 'O casal em um abraço, com arquitetura clássica ao fundo.'],
    ['name' => 'gallery-2', 'alt' => 'O casal em trajes escuros diante de um prédio histórico.'],
    ['name' => 'gallery-3', 'alt' => 'O casal se beijando ao ar livre com buquê de flores.'],
    ['name' => 'gallery-4', 'alt' => 'Retrato em preto e branco do casal.'],
];
?>
<section class="section section--gallery" id="galeria" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Momentos</p>
            <h2 class="section__title">Nosso ensaio</h2>
        </div>

        <?php
        // Monta apenas as fotos existentes para o carrossel. Se nenhuma existir,
        // mantém os placeholders em grade para orientar o cadastro das imagens.
        $resolved = [];
        foreach ($photos as $photo) {
            $img = View::image($photo['name'], $baseUrl);
            $resolved[] = ['img' => $img, 'alt' => $photo['alt'], 'name' => $photo['name']];
        }
        $hasAny = false;
        foreach ($resolved as $r) {
            if ($r['img']['exists']) { $hasAny = true; break; }
        }
        ?>

        <?php if ($hasAny): ?>
            <div class="gallery-carousel" data-carousel>
                <div class="gallery-carousel__viewport" data-carousel-viewport>
                    <ul class="gallery-carousel__track" data-carousel-track>
                        <?php foreach ($resolved as $r): ?>
                            <?php if ($r['img']['exists']): ?>
                                <li class="gallery-carousel__slide">
                                    <figure class="gallery-item">
                                        <img src="<?= View::e($r['img']['url']) ?>"
                                             alt="<?= View::e($r['alt']) ?>" loading="lazy">
                                    </figure>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($resolved as $r): ?>
                    <figure class="gallery-item">
                        <div class="media-placeholder" role="img"
                             aria-label="Espaço reservado para uma foto do ensaio.">
                            <span class="placeholder-note"><code><?= View::e($r['name']) ?></code></span>
                        </div>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
