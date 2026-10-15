<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Histórico de comunicações automáticas (envios e falhas) — ponto 7.
 *
 * @var array                               $app
 * @var array<int, array<string, mixed>>    $logs
 * @var string|null                         $error
 */

$channelLabels = ['email' => 'E-mail', 'whatsapp' => 'WhatsApp'];
$typeLabels    = [
    'welcome'        => 'Boas-vindas',
    'password_reset' => 'Recuperação de senha',
    'rsvp'           => 'Confirmação de presença',
];
$statusLabels = ['sent' => 'Enviado', 'failed' => 'Falhou', 'skipped' => 'Ignorado'];
$statusClass  = ['sent' => 'ok', 'failed' => 'err', 'skipped' => 'muted'];
?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <h2>Comunicações automáticas</h2>
    <p>Registro das mensagens enviadas pelo site (boas-vindas, recuperação de
    senha e confirmação de presença). <em>Ignorado</em> indica que o canal não
    pôde ser usado (ex.: convidado sem e-mail, SMTP ou WhatsApp não configurado).</p>

    <?php if ($logs === []): ?>
        <p>Nenhuma comunicação registrada ainda.</p>
    <?php else: ?>
        <div class="table-wrap admin-table--cards-wrap">
        <table class="admin-table admin-table--cards">
            <thead>
                <tr>
                    <th class="cell-hide-mobile">Quando</th>
                    <th class="cell-hide-mobile">Convidado</th>
                    <th class="cell-hide-mobile">Canal</th>
                    <th>Tipo</th>
                    <th>Destino</th>
                    <th>Status</th>
                    <th class="cell-hide-mobile">Observação</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $row): ?>
                <?php
                $channel = (string) ($row['channel'] ?? '');
                $type    = (string) ($row['type'] ?? '');
                $status  = (string) ($row['status'] ?? '');
                $when    = (string) ($row['created_at'] ?? '');
                $whenFmt = $when !== '' ? date('d/m/Y H:i', strtotime($when)) : '—';
                $cls     = $statusClass[$status] ?? 'muted';
                $statusText = $statusLabels[$status] ?? $status;
                // Dados para o popup (somente leitura).
                $commData = [
                    'when'    => $whenFmt,
                    'guest'   => (string) ($row['guest_name'] ?? '—'),
                    'channel' => $channelLabels[$channel] ?? $channel,
                    'type'    => $typeLabels[$type] ?? $type,
                    'target'  => (string) ($row['recipient'] ?? '—'),
                    'status'  => $statusText,
                    'note'    => (string) ($row['error'] ?? ''),
                ];
                ?>
                <tr data-row-open>
                    <td class="cell-hide-mobile" data-label="Quando"><?= View::e($whenFmt) ?></td>
                    <td class="cell-hide-mobile" data-label="Convidado"><?= View::e((string) ($row['guest_name'] ?? '—')) ?></td>
                    <td class="cell-hide-mobile" data-label="Canal"><?= View::e($channelLabels[$channel] ?? $channel) ?></td>
                    <td data-label="Tipo"><?= View::e($typeLabels[$type] ?? $type) ?>
                        <span class="status-dot status-dot--<?= View::e($status) ?>" title="<?= View::e($statusText) ?>" aria-hidden="true"></span>
                    </td>
                    <td data-label="Destino"><?= View::e((string) ($row['recipient'] ?? '—')) ?></td>
                    <td data-label="Status">
                        <span class="badge cell-badge-dot badge--<?= View::e($cls) ?>">
                            <?= View::e($statusText) ?>
                        </span>
                    </td>
                    <td class="cell-hide-mobile" data-label="Observação"><?= View::e((string) ($row['error'] ?? '')) ?></td>
                    <td class="cell-action-mobile-hide">
                        <?php /* Gatilho do popup usado só no mobile (linha clicável). Fica oculto. */ ?>
                        <button type="button" class="is-hidden-trigger"
                                data-comm-open data-row-trigger aria-hidden="true" tabindex="-1"
                                data-comm='<?= View::e(json_encode($commData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: detalhe da comunicação (aberto ao tocar na linha no mobile) -->
<div class="modal" data-comm-modal hidden>
    <div class="modal__backdrop" data-comm-close></div>
    <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="comm-modal-title">
        <div class="modal__head">
            <h3 class="modal__title" id="comm-modal-title">Comunicação</h3>
            <button type="button" class="modal__close" data-comm-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div class="finance-detail" data-comm-detail></div>
            <p class="finance-detail__msg" data-comm-note hidden></p>
        </div>
        <div class="modal__foot">
            <button type="button" class="btn-admin btn-admin--sm" data-comm-close>Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var modal = document.querySelector('[data-comm-modal]');
    if (!modal) { return; }

    var detailBox = modal.querySelector('[data-comm-detail]');
    var noteBox   = modal.querySelector('[data-comm-note]');

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
            row('Data/hora', d.when)
          + row('Convidado', d.guest)
          + row('Canal', d.channel)
          + row('Tipo', d.type)
          + row('Destino', d.target)
          + row('Status', d.status);

        if (d.note && d.note.trim() !== '') {
            noteBox.innerHTML = '<span style="color:var(--c-muted)">Observação:</span> ' + esc(d.note);
            noteBox.hidden = false;
        } else {
            noteBox.hidden = true;
        }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };
    var close = function () {
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-comm-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { open(JSON.parse(btn.getAttribute('data-comm'))); }
            catch (e) { /* ignora dado inválido */ }
        });
    });
    modal.querySelectorAll('[data-comm-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
})();
</script>

<style>
/* Estilos mínimos para os badges de status (reaproveita a paleta do admin). */
.badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; }
.badge--ok    { background:#e6f3e6; color:#2f7d32; }
.badge--err   { background:#fdecec; color:#c0392b; }
.badge--muted { background:#eee; color:#777; }
.table-wrap { overflow-x:auto; }
</style>
