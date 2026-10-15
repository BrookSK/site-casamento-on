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
    <div class="stat"><span class="stat__value"><?= (int) ($summary['pending'] ?? 0) ?></span><span class="stat__label">Sem resposta</span></div>
    <div class="stat"><span class="stat__value"><?= (int) ($summary['companions'] ?? 0) ?></span><span class="stat__label">Acompanhantes confirmados</span></div>
    <div class="stat"><span class="stat__value"><?= (int) $summary['people'] ?></span><span class="stat__label">Total estimado de pessoas</span></div>
</div>

<?php
// As exportações respeitam o filtro aplicado no painel.
$exportQs = $status !== '' ? ('?status=' . $status) : '';
?>
<!-- Exportação acima do card, alinhada à direita. -->
<div class="export-bar">
    <a class="btn-admin btn-admin--ghost btn-admin--sm"
       href="<?= View::e(View::url('/admin/confirmacoes/exportar/excel' . $exportQs, $baseUrl)) ?>">
        Exportar Excel
    </a>
    <a class="btn-admin btn-admin--ghost btn-admin--sm"
       href="<?= View::e(View::url('/admin/confirmacoes/exportar/pdf' . $exportQs, $baseUrl)) ?>">
        Exportar PDF
    </a>
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
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhuma confirmação <?= $status !== '' ? 'com esse filtro' : 'ainda' ?>.</p>
    <?php else: ?>
        <div style="overflow-x:auto;" class="admin-table--cards-wrap">
        <table class="admin-table admin-table--cards admin-table--cards-inline">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Acomp.</th>
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
                        $companionNames = array_values(array_filter(explode('||', (string) $r['companion_names'])));
                    }
                    // Dados para o modal de detalhes (sem navegar para outra tela).
                    $rsvpData = [
                        'id'         => (int) $r['id'],
                        'name'       => (string) $r['name'],
                        'email'      => (string) ($r['email'] ?? ''),
                        'phone'      => (string) ($r['phone'] ?? ''),
                        'status'     => $label,
                        'companions' => (int) $r['companions'],
                        'companion_names' => $companionNames,
                        'message'    => (string) ($r['message'] ?? ''),
                        'when'       => (string) ($r['responded_at'] ?? ''),
                    ];
                    ?>
                    <tr data-row-open>
                        <td data-label="Nome"><strong><?= View::e((string) $r['name']) ?></strong>
                            <span class="status-dot status-dot--<?= View::e((string) $r['status']) ?>" title="<?= View::e($label) ?>" aria-hidden="true"></span>
                        </td>
                        <td data-label="Status" class="cell-status-mobile-hide">
                            <span class="badge cell-badge-dot <?= $cls ?>"><?= View::e($label) ?></span>
                        </td>
                        <td data-label="Acompanhantes"><?= (int) $r['companions'] ?></td>
                        <td class="col-nowrap" data-label="Quando"><small><?= View::e((string) ($r['responded_at'] ?? '')) ?></small></td>
                        <td class="col-nowrap cell-action-mobile-hide">
                            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm"
                                    data-rsvp-open data-row-trigger
                                    data-rsvp='<?= View::e(json_encode($rsvpData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                                Detalhes
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: detalhe da confirmação -->
<div class="modal" data-rsvp-modal hidden>
    <div class="modal__backdrop" data-rsvp-close></div>
    <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="rsvp-modal-title">
        <div class="modal__head">
            <h3 class="modal__title" id="rsvp-modal-title">Confirmação</h3>
            <button type="button" class="modal__close" data-rsvp-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div class="finance-detail" data-rsvp-detail></div>
            <div class="rsvp-detail__companions" data-rsvp-companions hidden>
                <h4 class="rsvp-detail__subtitle">Acompanhantes</h4>
                <ul class="companion-list" data-rsvp-companions-list></ul>
            </div>
            <p class="finance-detail__msg" data-rsvp-msg hidden></p>
        </div>
        <div class="modal__foot">
            <form method="post" action="<?= View::e(View::url('/admin/confirmacoes/remover', $baseUrl)) ?>"
                  data-rsvp-remove-form onsubmit="return confirm('Remover esta confirmação? Esta ação não pode ser desfeita.');"
                  style="margin-right:auto;">
                <?= $csrfField ?>
                <input type="hidden" name="id" value="" data-rsvp-remove-id>
                <button type="submit" class="btn-admin btn-admin--danger btn-admin--sm">Remover convidado</button>
            </form>
            <button type="button" class="btn-admin btn-admin--sm" data-rsvp-close>Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var modal = document.querySelector('[data-rsvp-modal]');
    if (!modal) { return; }

    var detailBox = modal.querySelector('[data-rsvp-detail]');
    var compBox = modal.querySelector('[data-rsvp-companions]');
    var compList = modal.querySelector('[data-rsvp-companions-list]');
    var msgBox = modal.querySelector('[data-rsvp-msg]');
    var removeId = modal.querySelector('[data-rsvp-remove-id]');

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var row = function (label, value) {
        return '<div class="finance-detail__item"><span>' + esc(label) + '</span>'
             + '<strong>' + esc(value || '—') + '</strong></div>';
    };

    var open = function (d) {
        detailBox.innerHTML =
            row('Nome completo', d.name)
          + row('Status', d.status)
          + row('E-mail', d.email)
          + row('Telefone', d.phone)
          + row('Acompanhantes', d.companions)
          + row('Respondido em', d.when);

        // Lista de nomes dos acompanhantes (quando houver).
        var names = d.companion_names || [];
        if (names.length) {
            compList.innerHTML = names.map(function (n) { return '<li>+ ' + esc(n) + '</li>'; }).join('');
            compBox.hidden = false;
        } else {
            compBox.hidden = true;
        }

        if (d.message && d.message.trim() !== '') {
            msgBox.innerHTML = '<span style="color:var(--c-muted)">Recado:</span> ' + esc(d.message);
            msgBox.hidden = false;
        } else {
            msgBox.hidden = true;
        }

        if (removeId) { removeId.value = d.id; }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };
    var close = function () {
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-rsvp-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { open(JSON.parse(btn.getAttribute('data-rsvp'))); }
            catch (e) { /* ignora dado inválido */ }
        });
    });
    modal.querySelectorAll('[data-rsvp-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
})();
</script>
