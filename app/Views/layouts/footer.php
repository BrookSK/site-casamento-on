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
$year    = date('Y');

// Mesmo controle do cabeçalho: oculta o link de confirmação na navegação
// pública (a página segue acessível pela URL direta / QR Code).
$showRsvpLink = !empty($app['show_rsvp_link']);

// Música ambiente ("Die With a Smile" — Lady Gaga & Bruno Mars).
// O arquivo deve ser colocado em public/assets/audio/die-with-a-smile.mp3.
// Se não existir, o player simplesmente não é renderizado.
$audioFile = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/assets/audio/die-with-a-smile.mp3' : '';
$hasAudio  = $audioFile !== '' && is_file($audioFile);
$audioUrl  = $hasAudio ? View::asset('audio/die-with-a-smile.mp3', $baseUrl) . '?v=' . filemtime($audioFile) : '';
?>
</main>

<footer class="site-footer" id="rodape">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <p class="site-footer__names"><?= View::e($bride) ?> <span>&amp;</span> <?= View::e($groom) ?></p>
        </div>

        <nav class="site-footer__nav" aria-label="Navegação do rodapé">
            <ul>
                <li><a href="<?= View::e(View::url('/#historia', $baseUrl)) ?>">O casal</a></li>
                <li><a href="<?= View::e(View::url('/#evento', $baseUrl)) ?>">O evento</a></li>
                <li><a href="<?= View::e(View::url('/#presentes', $baseUrl)) ?>">Presentes</a></li>
                <li><a href="<?= View::e(View::url('/galeria', $baseUrl)) ?>">Galeria de Fotos</a></li>
                <?php if ($showRsvpLink): ?>
                    <li><a href="<?= View::e(View::url('/confirmacao-interna', $baseUrl)) ?>">Confirmar presença</a></li>
                <?php endif; ?>
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

<?php if ($hasAudio): ?>
<!-- Música ambiente: controle discreto flutuante -->
<div class="music-player" data-music-player hidden>
    <audio data-music-audio loop preload="auto" src="<?= View::e($audioUrl) ?>"></audio>
    <button
        class="music-player__btn"
        type="button"
        data-music-toggle
        aria-pressed="false"
        aria-label="Tocar música do casamento"
        title="Die With a Smile — Lady Gaga &amp; Bruno Mars"
    >
        <span class="music-player__icon" aria-hidden="true">
            <span class="music-player__bar"></span>
            <span class="music-player__bar"></span>
            <span class="music-player__bar"></span>
            <span class="music-player__bar"></span>
        </span>
        <span class="sr-only" data-music-label>Tocar música</span>
    </button>
</div>
<?php endif; ?>

<script src="<?= View::e(View::assetVersioned('js/main.js', $baseUrl)) ?>" defer></script>
</body>
</html>
