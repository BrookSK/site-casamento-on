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
$photos = [
    ['file' => 'gallery-1.jpg', 'alt' => 'O casal de mãos dadas em um jardim com chafariz.'],
    ['file' => 'gallery-2.jpg', 'alt' => 'Retrato do casal com buquê de flores.'],
    ['file' => 'gallery-3.jpg', 'alt' => 'O casal em um abraço, com arquitetura clássica ao fundo.'],
    ['file' => 'gallery-4.jpg', 'alt' => 'O casal em trajes escuros diante de um prédio histórico.'],
    ['file' => 'gallery-5.jpg', 'alt' => 'O casal se beijando ao ar livre.'],
    ['file' => 'gallery-6.jpg', 'alt' => 'Retrato em preto e branco do casal.'],
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
                $src  = View::asset('img/' . $photo['file'], $baseUrl);
                $file = PUBLIC_PATH . '/assets/img/' . $photo['file'];
                $has  = is_file($file);
                $spanClass = ($i === 0) ? 'gallery-item--wide' : '';
                ?>
                <figure class="gallery-item <?= $spanClass ?>">
                    <?php if ($has): ?>
                        <img src="<?= View::e($src) ?>" alt="<?= View::e($photo['alt']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="media-placeholder" role="img"
                             aria-label="Espaço reservado para a foto <?= View::e($photo['file']) ?>.">
                            <span class="placeholder-note"><code><?= View::e($photo['file']) ?></code></span>
                        </div>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
