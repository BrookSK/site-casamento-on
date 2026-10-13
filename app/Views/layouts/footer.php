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
                <li><a href="#historia">O casal</a></li>
                <li><a href="#evento">O evento</a></li>
                <li><a href="#presentes">Presentes</a></li>
                <li><a href="#rsvp">Confirmar presença</a></li>
                <li><a href="#topo">Voltar ao topo</a></li>
            </ul>
        </nav>

        <p class="site-footer__note">
            <!-- Observação provisória: conteúdo meramente visual nesta etapa -->
            Feito com carinho &middot; &copy; <?= View::e((string) $year) ?>
        </p>
    </div>
</footer>

<script src="<?= View::e(View::assetVersioned('js/main.js', $baseUrl)) ?>" defer></script>
</body>
</html>
