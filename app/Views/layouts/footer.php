<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array $app
 * @var array $wedding
 */

$baseUrl = $app['base_url'] ?? '';
$bride   = $wedding['bride'] ?? 'Noiva';
$groom   = $wedding['groom'] ?? 'Noivo';
$hashtag = $wedding['hashtag'] ?? '';
$year    = date('Y');
?>
</main>

<footer class="site-footer" id="rodape">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <p class="site-footer__names"><?= View::e($bride) ?> <span>&amp;</span> <?= View::e($groom) ?></p>
            <?php if ($hashtag !== ''): ?>
                <p class="site-footer__hashtag"><?= View::e($hashtag) ?></p>
            <?php endif; ?>
        </div>

        <nav class="site-footer__nav" aria-label="Navegação do rodapé">
            <ul>
                <li><a href="<?= View::e(View::url('/#historia', $baseUrl)) ?>">O casal</a></li>
                <li><a href="<?= View::e(View::url('/#evento', $baseUrl)) ?>">O evento</a></li>
                <li><a href="<?= View::e(View::url('/#presentes', $baseUrl)) ?>">Presentes</a></li>
                <li><a href="<?= View::e(View::url('/galeria', $baseUrl)) ?>">Galeria de Fotos</a></li>
                <li><a href="<?= View::e(View::url('/#rsvp', $baseUrl)) ?>">Confirmar presença</a></li>
                <li><a href="#topo">Voltar ao topo</a></li>
            </ul>
        </nav>

        <p class="site-footer__admin">
            <a href="<?= View::e(View::url('/admin/login', $baseUrl)) ?>">Área administrativa</a>
        </p>

        <p class="site-footer__note">
            Feito com carinho &middot; &copy; <?= View::e((string) $year) ?>
        </p>
    </div>
</footer>

<script src="<?= View::e(View::assetVersioned('js/main.js', $baseUrl)) ?>" defer></script>
</body>
</html>
