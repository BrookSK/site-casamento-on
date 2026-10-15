<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Painel financeiro — dashboard, filtros e tabela de transações (ponto 11).
 *
 * @var array $app
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, int> $summary
 * @var array<int, array<string, mixed>> $gifts
 * @var array<int, array<int, array<string, mixed>>> $history
 * @var array{status:string,gift_id:int,guest:string,date_from:string,date_to:string} $filters
 * @var string|null $error
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';
$summary = $summary ?? [];
$history = $history ?? [];
$filters = $filters ?? ['status' => '', 'gift_id' => 0, 'guest' => '', 'date_from' => '', 'date_to' => ''];

$money = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');

$statusLabels = [
    'pending'  => ['Pendente',  'badge--reserved'],
    'paid'     => ['Pago',      'badge--purchased'],
    'failed'   => ['Recusado',  'badge--declined'],
    'refunded' => ['Estornado', 'badge--available'],
];
$billingLabels = ['PIX' => 'PIX', 'CREDIT_CARD' => 'Cartão', 'BOLETO' => 'Boleto', 'UNDEFINED' => '—'];

$fmtDate = static function ($v): string {
    if (empty($v) || str_starts_with((string) $v, '0000')) {
        return '—';
    }
    $ts = strtotime((string) $v);
    return $ts !== false ? date('d/m/Y H:i', $ts) : (string) $v;
};

// Query string dos filtros atuais (para preservar nos botões de exportação).
$qs = http_build_query(array_filter([
    'status'    => $filters['status'],
    'gift_id'   => $filters['gift_id'] ?: null,
    'guest'     => $filters['guest'],
    'date_from' => $filters['date_from'],
    'date_to'   => $filters['date_to'],
], static fn ($v) => $v !== '' && $v !== null));
$exportQs = $qs !== '' ? ('?' . $qs) : '';
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<!-- Dashboard financeiro: valores em destaque -->
<h2 class="finance-section-title">Visão geral</h2>
<div class="fin-grid fin-grid--money">
    <div class="fin-stat fin-stat--highlight">
        <span class="fin-stat__label">Total vendido</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['sold_cents'] ?? 0)) ?></span>
    </div>
    <div class="fin-stat fin-stat--good">
        <span class="fin-stat__label">Aprovado (pago)</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['approved_cents'] ?? 0)) ?></span>
    </div>
    <div class="fin-stat fin-stat--good">
        <span class="fin-stat__label">Líquido recebido</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['net_cents'] ?? 0)) ?></span>
    </div>
    <div class="fin-stat">
        <span class="fin-stat__label">Taxas</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['fee_cents'] ?? 0)) ?></span>
    </div>
    <div class="fin-stat fin-stat--warn">
        <span class="fin-stat__label">Pendente</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['pending_cents'] ?? 0)) ?></span>
    </div>
    <div class="fin-stat fin-stat--muted">
        <span class="fin-stat__label">Estornos/reembolsos</span>
        <span class="fin-stat__value"><?= $money((int) ($summary['refunded_cents'] ?? 0)) ?></span>
    </div>
</div>

<!-- Contadores de transações (menores) -->
<div class="fin-grid fin-grid--counts">
    <div class="fin-count">
        <span class="fin-count__value"><?= (int) ($summary['tx_total'] ?? 0) ?></span>
        <span class="fin-count__label">Transações</span>
    </div>
    <div class="fin-count">
        <span class="fin-count__value"><?= (int) ($summary['tx_paid'] ?? 0) ?></span>
        <span class="fin-count__label">Pagas</span>
    </div>
    <div class="fin-count">
        <span class="fin-count__value"><?= (int) ($summary['tx_pending'] ?? 0) ?></span>
        <span class="fin-count__label">Pendentes</span>
    </div>
    <div class="fin-count">
        <span class="fin-count__value"><?= (int) ($summary['tx_refunded'] ?? 0) ?></span>
        <span class="fin-count__label">Estornadas</span>
    </div>
</div>

<!-- Filtros -->
<div class="admin-card">
    <h3 class="finance-card-title">Filtrar transações</h3>
    <form class="finance-filters" method="get" action="<?= View::e(View::url('/admin/financeiro', $baseUrl)) ?>">
        <div class="field">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
                <option value="">Todos</option>
                <?php foreach (['pending' => 'Pendente', 'paid' => 'Pago', 'failed' => 'Recusado', 'refunded' => 'Estornado'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= View::e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-gift">Presente</label>
            <select id="f-gift" name="gift_id">
                <option value="0">Todos</option>
                <?php foreach ($gifts as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= (int) $filters['gift_id'] === (int) $g['id'] ? 'selected' : '' ?>>
                        <?= View::e((string) $g['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-guest">Convidado/Comprador</label>
            <input type="text" id="f-guest" name="guest" value="<?= View::e($filters['guest']) ?>" placeholder="Nome">
        </div>
        <div class="field">
            <label for="f-from">De</label>
            <input type="date" id="f-from" name="date_from" value="<?= View::e($filters['date_from']) ?>">
        </div>
        <div class="field">
            <label for="f-to">Até</label>
            <input type="date" id="f-to" name="date_to" value="<?= View::e($filters['date_to']) ?>">
        </div>
        <div class="finance-filters__actions">
            <button type="submit" class="btn-admin">Filtrar</button>
            <a class="finance-filters__clear" href="<?= View::e(View::url('/admin/financeiro', $baseUrl)) ?>">Limpar filtros</a>
        </div>
    </form>
</div>

<!-- Exportação acima do card, alinhada à direita (mesmo padrão de Confirmações). -->
<div class="export-bar">
    <a class="btn-admin btn-admin--ghost btn-admin--sm"
       href="<?= View::e(View::url('/admin/financeiro/exportar/excel' . $exportQs, $baseUrl)) ?>">Exportar Excel</a>
    <a class="btn-admin btn-admin--ghost btn-admin--sm"
       href="<?= View::e(View::url('/admin/financeiro/exportar/pdf' . $exportQs, $baseUrl)) ?>">Exportar PDF</a>
</div>

<!-- Tabela de transações -->
<div class="admin-card">
    <div class="admin-toolbar">
        <h3 class="finance-card-title" style="margin:0;">Transações</h3>
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhuma transação encontrada com esses filtros.</p>
    <?php else: ?>
        <div style="overflow-x:auto;" class="admin-table--cards-wrap">
        <table class="admin-table admin-table--finance admin-table--cards">
            <thead>
                <tr>
                    <th>Presente</th>
                    <th>Convidado/Comprador</th>
                    <th>Forma</th>
                    <th>Status</th>
                    <th class="cell-hide-mobile">Criada em</th>
                    <th class="col-money cell-hide-mobile">Bruto</th>
                    <th class="col-money cell-hide-mobile">Taxas</th>
                    <th class="col-money cell-hide-mobile">Líquido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $p): ?>
                    <?php
                    $st = (string) ($p['payment_status'] ?? 'pending');
                    [$label, $cls] = $statusLabels[$st] ?? ['—', ''];
                    $bt = (string) ($p['billing_type'] ?? 'UNDEFINED');
                    $buyer = (string) ($p['guest_name'] ?? ($p['buyer_name'] ?? '—'));
                    ?>
                    <tr data-row-open>
                        <td data-label="Presente"><strong><?= View::e((string) ($p['gift_name'] ?? '—')) ?></strong>
                            <span class="status-dot status-dot--<?= View::e($st) ?>" title="<?= View::e($label) ?>" aria-hidden="true"></span>
                        </td>
                        <td data-label="Comprador"><?= View::e($buyer !== '' ? $buyer : '—') ?></td>
                        <td data-label="Forma"><?= View::e($billingLabels[$bt] ?? $bt) ?></td>
                        <td data-label="Status"><span class="badge cell-badge-dot <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td class="col-nowrap cell-hide-mobile" data-label="Criada em"><small><?= View::e($fmtDate($p['created_at'] ?? null)) ?></small></td>
                        <td class="col-money cell-hide-mobile" data-label="Bruto"><?= $money((int) ($p['amount_cents'] ?? 0)) ?></td>
                        <td class="col-money cell-hide-mobile" data-label="Taxas"><?= $money((int) ($p['fee_cents'] ?? 0)) ?></td>
                        <td class="col-money col-money--net cell-hide-mobile" data-label="Líquido"><?= $money((int) ($p['net_cents'] ?? 0)) ?></td>
                        <td class="col-nowrap cell-action-mobile-hide">
                            <?php
                            // Monta os dados da transação para o modal (sem nova
                            // navegação). O histórico vem agrupado por id.
                            $pid = (int) ($p['id'] ?? 0);
                            $modalData = [
                                'id'           => $pid,
                                'gift'         => (string) ($p['gift_name'] ?? '—'),
                                'buyer'        => $buyer !== '' ? $buyer : '—',
                                'email'        => (string) ($p['buyer_email'] ?? ''),
                                'billing'      => $billingLabels[$bt] ?? $bt,
                                'status'       => $label,
                                'status_cls'   => $cls,
                                'ref'          => (string) ($p['payment_ref'] ?? ''),
                                'amount'       => $money((int) ($p['amount_cents'] ?? 0)),
                                'fee'          => $money((int) ($p['fee_cents'] ?? 0)),
                                'net'          => $money((int) ($p['net_cents'] ?? 0)),
                                'gift_price'   => $money((int) ($p['gift_price_cents'] ?? 0)),
                                'created_at'   => $fmtDate($p['created_at'] ?? null),
                                'paid_at'      => $fmtDate($p['paid_at'] ?? null),
                                'confirmed_at' => $fmtDate($p['confirmed_at'] ?? null),
                                'settled_at'   => $fmtDate($p['settled_at'] ?? null),
                                'refunded_at'  => $fmtDate($p['refunded_at'] ?? null),
                                'message'      => (string) ($p['message'] ?? ''),
                                'history'      => array_map(static function ($h) use ($fmtDate) {
                                    return [
                                        'when' => $fmtDate($h['created_at'] ?? null),
                                        'from' => (string) ($h['from_status'] ?? '—'),
                                        'to'   => (string) ($h['to_status'] ?? '—'),
                                        'note' => (string) ($h['note'] ?? ''),
                                    ];
                                }, $history[$pid] ?? []),
                            ];
                            ?>
                            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm"
                                    data-fin-open data-row-trigger
                                    data-fin='<?= View::e(json_encode($modalData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
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

<!-- Modal: detalhe da transação -->
<div class="modal" data-fin-modal hidden>
    <div class="modal__backdrop" data-fin-close></div>
    <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="fin-modal-title">
        <div class="modal__head">
            <h3 class="modal__title" id="fin-modal-title">Transação</h3>
            <button type="button" class="modal__close" data-fin-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div class="finance-detail" data-fin-detail></div>
            <p class="finance-detail__msg" data-fin-msg hidden></p>
        </div>
        <div class="modal__foot">
            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm" data-fin-history-open>
                Ver histórico
            </button>
            <button type="button" class="btn-admin btn-admin--sm" data-fin-close>Fechar</button>
        </div>
    </div>
</div>

<!-- Modal: histórico de status -->
<div class="modal" data-fin-history-modal hidden>
    <div class="modal__backdrop" data-fin-history-close></div>
    <div class="modal__box modal__box--sm" role="dialog" aria-modal="true" aria-labelledby="fin-hist-title">
        <div class="modal__head">
            <h3 class="modal__title" id="fin-hist-title">Histórico de status</h3>
            <button type="button" class="modal__close" data-fin-history-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div data-fin-history></div>
        </div>
        <div class="modal__foot">
            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm" data-fin-history-close>Voltar</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var modal = document.querySelector('[data-fin-modal]');
    var histModal = document.querySelector('[data-fin-history-modal]');
    if (!modal) { return; }

    var detailBox = modal.querySelector('[data-fin-detail]');
    var msgBox = modal.querySelector('[data-fin-msg]');
    var histBox = histModal ? histModal.querySelector('[data-fin-history]') : null;
    var current = null; // dados da transação aberta

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    var row = function (label, value) {
        return '<div class="finance-detail__item"><span>' + esc(label) + '</span>'
             + '<strong>' + esc(value || '—') + '</strong></div>';
    };

    var openDetail = function (data) {
        current = data;
        modal.querySelector('[data-fin-detail]').innerHTML =
            row('Presente', data.gift)
          + row('Convidado/Comprador', data.buyer)
          + row('E-mail', data.email)
          + row('Forma de pagamento', data.billing)
          + row('Status', data.status)
          + row('Referência (gateway)', data.ref)
          + row('Valor bruto', data.amount)
          + row('Taxas', data.fee)
          + row('Valor líquido', data.net)
          + row('Preço original', data.gift_price)
          + row('Criada em', data.created_at)
          + row('Pagamento', data.paid_at)
          + row('Confirmação', data.confirmed_at)
          + row('Liquidação', data.settled_at)
          + row('Estorno', data.refunded_at);

        if (msgBox) {
            if (data.message && data.message.trim() !== '') {
                msgBox.innerHTML = '<span style="color:var(--c-muted)">Mensagem:</span> ' + esc(data.message);
                msgBox.hidden = false;
            } else {
                msgBox.hidden = true;
            }
        }
        modal.querySelector('[data-fin-history-open]').style.display =
            (data.history && data.history.length) ? '' : 'none';

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    var closeDetail = function () {
        modal.hidden = true;
        if (!histModal || histModal.hidden) { document.body.style.overflow = ''; }
    };

    var openHistory = function () {
        if (!histModal || !histBox || !current) { return; }
        var items = current.history || [];
        if (!items.length) {
            histBox.innerHTML = '<p class="admin-empty">Nenhuma mudança de status registrada.</p>';
        } else {
            var html = '<table class="admin-table"><thead><tr>'
                     + '<th>Quando</th><th>De</th><th>Para</th><th>Observação</th>'
                     + '</tr></thead><tbody>';
            items.forEach(function (h) {
                html += '<tr><td class="col-nowrap"><small>' + esc(h.when) + '</small></td>'
                     +  '<td>' + esc(h.from) + '</td>'
                     +  '<td><strong>' + esc(h.to) + '</strong></td>'
                     +  '<td>' + esc(h.note) + '</td></tr>';
            });
            html += '</tbody></table>';
            histBox.innerHTML = html;
        }
        histModal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    var closeHistory = function () {
        if (histModal) { histModal.hidden = true; }
        if (modal.hidden) { document.body.style.overflow = ''; }
    };

    document.querySelectorAll('[data-fin-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openDetail(JSON.parse(btn.getAttribute('data-fin'))); }
            catch (e) { /* dado inválido: ignora */ }
        });
    });
    modal.querySelectorAll('[data-fin-close]').forEach(function (el) {
        el.addEventListener('click', closeDetail);
    });
    var histOpenBtn = modal.querySelector('[data-fin-history-open]');
    if (histOpenBtn) { histOpenBtn.addEventListener('click', openHistory); }
    if (histModal) {
        histModal.querySelectorAll('[data-fin-history-close]').forEach(function (el) {
            el.addEventListener('click', closeHistory);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        if (histModal && !histModal.hidden) { closeHistory(); }
        else if (!modal.hidden) { closeDetail(); }
    });
})();
</script>
