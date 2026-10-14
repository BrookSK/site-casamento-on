<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de confirmações de presença.
 *
 * @var array        $app
 * @var array<int, array<string, mixed>> $rows
 * @var array{total:int,confirmed:int,declined:int,pending:int,people:int} $summary
 * @var string       $status
 * @var string|null  $error
 * @var string       $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';

$statusLabels = [
    'confirmed' => ['Confirmado', 'badge--confirmed'],
    'declined'  => ['Não vai',    'badge--declined'],
    'pending'   => ['Pendente',   'badge--pending'],
];

$filters = [
    ''          => 'Todos',
    'confirmed' => 'Confirmados',
    'declined'  => 'Não vão',
    'pending'   => 'Pendentes',
];
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat"><span class="stat__value"><?= (int) ($summary['guests_total'] ?? 0) ?></span><span class="stat__label">Convidados cadastrados</span></div>
    <div class="stat"><span class="stat__value"><?= (int) $summary['confirmed'] ?></span><span class="stat__label">Presenças confirmadas</span></div>
    <div class="stat"><span class="stat__value"><?= (int) $summary['declined'] ?></span><span class="stat__label">Ausências</span></div>
    <div class="stat"><span class="stat__value"><?= (int) ($summary['companions'] ?? 0) ?></span><span class="stat__label">Acompanhantes confirmados</span></div>
    <div class="stat"><span class="stat__value"><?= (int) $summary['people'] ?></span><span class="stat__label">Total estimado de pessoas</span></div>
    <div class="stat"><span class="stat__value"><?= (int) $summary['total'] ?></span><span class="stat__label">Respostas recebidas</span></div>
</div>

<div class="admin-card">
    <div class="admin-toolbar">
        <div class="filters">
            <?php foreach ($filters as $key => $label): ?>
                <?php $url = $key === '' ? '/admin/confirmacoes' : '/admin/confirmacoes?status=' . $key; ?>
                <a class="chip <?= $status === $key ? 'is-active' : '' ?>"
                   href="<?= View::e(View::url($url, $baseUrl)) ?>"><?= View::e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="spacer"></div>

        <?php
        // As exportações respeitam o filtro aplicado no painel.
        $exportQs = $status !== '' ? ('?status=' . $status) : '';
        ?>
        <div class="export-actions">
            <a class="btn-admin btn-admin--ghost btn-admin--sm"
               href="<?= View::e(View::url('/admin/confirmacoes/exportar/excel' . $exportQs, $baseUrl)) ?>">
                Exportar Excel
            </a>
            <a class="btn-admin btn-admin--ghost btn-admin--sm"
               href="<?= View::e(View::url('/admin/confirmacoes/exportar/pdf' . $exportQs, $baseUrl)) ?>">
                Exportar PDF
            </a>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhuma confirmação <?= $status !== '' ? 'com esse filtro' : 'ainda' ?>.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Contato</th>
                    <th>Status</th>
                    <th>Acomp.</th>
                    <th>Mensagem</th>
                    <th>Quando</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php [$label, $cls] = $statusLabels[$r['status']] ?? ['—', '']; ?>
                    <?php
                    // Nomes dos acompanhantes vêm agregados com separador '||'.
                    $companionNames = [];
                    if (!empty($r['companion_names'])) {
                        $companionNames = array_filter(explode('||', (string) $r['companion_names']));
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?= View::e((string) $r['name']) ?></strong>
                            <?php if ($companionNames !== []): ?>
                                <ul class="companion-list">
                                    <?php foreach ($companionNames as $cn): ?>
                                        <li>+ <?= View::e((string) $cn) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['email'])): ?><?= View::e((string) $r['email']) ?><br><?php endif; ?>
                            <?php if (!empty($r['phone'])): ?><small><?= View::e((string) $r['phone']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td><?= (int) $r['companions'] ?></td>
                        <td><?= nl2br(View::e((string) ($r['message'] ?? ''))) ?></td>
                        <td><small><?= View::e((string) ($r['responded_at'] ?? '')) ?></small></td>
                        <td>
                            <form method="post" action="<?= View::e(View::url('/admin/confirmacoes/remover', $baseUrl)) ?>"
                                  onsubmit="return confirm('Remover esta confirmação?');">
                                <?= $csrfField ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn-admin btn-admin--danger btn-admin--sm">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
