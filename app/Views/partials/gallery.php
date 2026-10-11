<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Galeria — mosaico com as fotos do ensaio.
 * Mostra placeholders elegantes enquanto as imagens não existirem.
 *
 * @var array $app
 */

$baseUrl = $app['base_url'] ?? '';

// Fotos esperadas (ver public/assets/img/README.md).
// 'name' é o nome-base (sem extensão): aceita .jpg/.png/.webp etc.
$photos = [
    ['name' => 'gallery-1', 'alt' => 'O casal de mãos dadas em um jardim com chafariz.'],
    ['name' => 'gallery-2', 'alt' => 'Retrato do casal com buquê de flores.'],
    ['name' => 'gallery-3', 'alt' => 'O casal em um abraço, com arquitetura clássica ao fundo.'],
    ['name' => 'gallery-4', 'alt' => 'O casal em trajes escuros diante de um prédio histórico.'],
    ['name' => 'gallery-5', 'alt' => 'O casal se beijando ao ar livre.'],
    ['name' => 'gallery-6', 'alt' => 'Retrato em preto e branco do casal.'],
];
?>
<section class="section section--gallery" id="galeria" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Momentos</p>
            <h2 class="section__title">Nosso ensaio</h2>
        </div>

        <div class="gallery-grid">
            <?php foreach ($photos as $i => $photo): ?>
                <?php
                $img = View::image($photo['name'], $baseUrl);
                $spanClass = ($i === 0) ? 'gallery-item--wide' : '';
                ?>
                <figure class="gallery-item <?= $spanClass ?>">
                    <?php if ($img['exists']): ?>
                        <img src="<?= View::e($img['url']) ?>" alt="<?= View::e($photo['alt']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="media-placeholder" role="img"
                             aria-label="Espaço reservado para a foto <?= View::e($photo['name']) ?>.">
                            <span class="placeholder-note"><code><?= View::e($photo['name']) ?></code></span>
                        </div>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
