<?php

declare(strict_types=1);

use App\Core\View;

/** @var array $app */
$baseUrl = $app['base_url'] ?? '';
?>
<section class="section section--error">
    <div class="container section__head section__head--center">
        <p class="section__eyebrow">Ops</p>
        <h1 class="section__title">Página não encontrada</h1>
        <p class="section__intro">O endereço que você tentou acessar não existe.</p>
        <a class="btn btn--primary" href="<?= View::e(View::url('/', $baseUrl)) ?>">Voltar ao início</a>
    </div>
</section>
