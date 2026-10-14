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
 * @var array{status:string,gift_id:int,guest:string,date_from:string,date_to:string} $filters
 * @var string|null $error
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';
$summary = $summary ?? [];
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
<h2 class="finance-section-title">Resumo financeiro</h2>
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
            <a class="btn-admin btn-admin--ghost" href="<?= View::e(View::url('/admin/financeiro', $baseUrl)) ?>">Limpar</a>
        </div>
    </form>
</div>

<!-- Tabela de transações -->
<div class="admin-card">
    <div class="admin-toolbar">
        <h3 class="finance-card-title" style="margin:0;">Transações</h3>
        <div class="spacer"></div>
        <div class="export-actions">
            <a class="btn-admin btn-admin--ghost btn-admin--sm"
               href="<?= View::e(View::url('/admin/financeiro/exportar/excel' . $exportQs, $baseUrl)) ?>">Exportar Excel</a>
            <a class="btn-admin btn-admin--ghost btn-admin--sm"
               href="<?= View::e(View::url('/admin/financeiro/exportar/pdf' . $exportQs, $baseUrl)) ?>">Exportar PDF</a>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhuma transação encontrada com esses filtros.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Presente</th>
                    <th>Convidado/Comprador</th>
                    <th>Forma</th>
                    <th>Status</th>
                    <th>Criada em</th>
                    <th style="text-align:right;">Bruto</th>
                    <th style="text-align:right;">Taxas</th>
                    <th style="text-align:right;">Líquido</th>
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
                    <tr>
                        <td><strong><?= View::e((string) ($p['gift_name'] ?? '—')) ?></strong></td>
                        <td><?= View::e($buyer !== '' ? $buyer : '—') ?></td>
                        <td><?= View::e($billingLabels[$bt] ?? $bt) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td><small><?= View::e($fmtDate($p['created_at'] ?? null)) ?></small></td>
                        <td style="text-align:right;"><?= $money((int) ($p['amount_cents'] ?? 0)) ?></td>
                        <td style="text-align:right;"><?= $money((int) ($p['fee_cents'] ?? 0)) ?></td>
                        <td style="text-align:right;"><?= $money((int) ($p['net_cents'] ?? 0)) ?></td>
                        <td>
                            <a class="btn-admin btn-admin--ghost btn-admin--sm"
                               href="<?= View::e(View::url('/admin/financeiro/transacao?id=' . (int) $p['id'], $baseUrl)) ?>">Detalhes</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
