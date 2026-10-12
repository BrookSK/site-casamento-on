<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Painel — visão geral.
 *
 * @var array                                   $app
 * @var array{total:int,confirmed:int,declined:int,pending:int,people:int} $rsvpSummary
 * @var int                                     $giftCount
 * @var string|null                             $dbError
 */

$baseUrl = $app['base_url'] ?? '';
?>
<?php if (!empty($dbError)): ?>
    <div class="flash flash--error"><?= View::e($dbError) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat__value"><?= (int) $rsvpSummary['confirmed'] ?></span>
        <span class="stat__label">Confirmados</span>
    </div>
    <div class="stat">
        <span class="stat__value"><?= (int) $rsvpSummary['people'] ?></span>
        <span class="stat__label">Pessoas (c/ acompanhantes)</span>
    </div>
    <div class="stat">
        <span class="stat__value"><?= (int) $rsvpSummary['declined'] ?></span>
        <span class="stat__label">Não poderão ir</span>
    </div>
    <div class="stat">
        <span class="stat__value"><?= (int) $giftCount ?></span>
        <span class="stat__label">Presentes cadastrados</span>
    </div>
</div>

<div class="admin-card">
    <h2>Bem-vindo(a) ao painel</h2>
    <p>Use o menu lateral para gerenciar as confirmações de presença, a lista de
    presentes e as configurações do site.</p>

    <div class="admin-toolbar" style="margin-top:1.5rem;">
        <a class="btn-admin" href="<?= View::e(View::url('/admin/confirmacoes', $baseUrl)) ?>">Ver confirmações</a>
        <a class="btn-admin btn-admin--ghost" href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Gerenciar presentes</a>
    </div>
</div>
