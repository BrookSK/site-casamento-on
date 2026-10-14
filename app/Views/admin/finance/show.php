<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Detalhe de uma transação financeira (ponto 11): valores bruto/taxas/líquido,
 * datas, status e histórico de mudanças.
 *
 * @var array $app
 * @var array<string, mixed>|null $purchase
 * @var array<int, array<string, mixed>> $history
 * @var string|null $error
 */

$baseUrl = $app['base_url'] ?? '';
$p = $purchase ?? [];

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

$st = (string) ($p['payment_status'] ?? 'pending');
[$stLabel, $stCls] = $statusLabels[$st] ?? ['—', ''];
$bt = (string) ($p['billing_type'] ?? 'UNDEFINED');
$buyer = (string) ($p['guest_name'] ?? ($p['buyer_name'] ?? '—'));
?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-toolbar">
        <a class="btn-admin btn-admin--ghost btn-admin--sm" href="<?= View::e(View::url('/admin/financeiro', $baseUrl)) ?>">&larr; Voltar</a>
        <div class="spacer"></div>
        <span class="badge <?= $stCls ?>"><?= View::e($stLabel) ?></span>
    </div>

    <h2 style="margin-bottom:0.25rem;"><?= View::e((string) ($p['gift_name'] ?? 'Presente')) ?></h2>
    <p style="color:var(--c-muted);margin-top:0;">Transação #<?= (int) ($p['id'] ?? 0) ?></p>

    <div class="finance-detail">
        <div class="finance-detail__item"><span>Convidado/Comprador</span><strong><?= View::e($buyer !== '' ? $buyer : '—') ?></strong></div>
        <div class="finance-detail__item"><span>E-mail</span><strong><?= View::e((string) ($p['buyer_email'] ?? '—')) ?></strong></div>
        <div class="finance-detail__item"><span>Forma de pagamento</span><strong><?= View::e($billingLabels[$bt] ?? $bt) ?></strong></div>
        <div class="finance-detail__item"><span>Referência (gateway)</span><strong><?= View::e((string) ($p['payment_ref'] ?? '—')) ?></strong></div>

        <div class="finance-detail__item"><span>Valor bruto</span><strong><?= $money((int) ($p['amount_cents'] ?? 0)) ?></strong></div>
        <div class="finance-detail__item"><span>Taxas</span><strong><?= $money((int) ($p['fee_cents'] ?? 0)) ?></strong></div>
        <div class="finance-detail__item"><span>Valor líquido</span><strong><?= $money((int) ($p['net_cents'] ?? 0)) ?></strong></div>
        <div class="finance-detail__item"><span>Preço original do presente</span><strong><?= $money((int) ($p['gift_price_cents'] ?? 0)) ?></strong></div>

        <div class="finance-detail__item"><span>Criada em</span><strong><?= View::e($fmtDate($p['created_at'] ?? null)) ?></strong></div>
        <div class="finance-detail__item"><span>Pagamento</span><strong><?= View::e($fmtDate($p['paid_at'] ?? null)) ?></strong></div>
        <div class="finance-detail__item"><span>Confirmação</span><strong><?= View::e($fmtDate($p['confirmed_at'] ?? null)) ?></strong></div>
        <div class="finance-detail__item"><span>Liquidação</span><strong><?= View::e($fmtDate($p['settled_at'] ?? null)) ?></strong></div>
        <div class="finance-detail__item"><span>Estorno</span><strong><?= View::e($fmtDate($p['refunded_at'] ?? null)) ?></strong></div>
    </div>

    <?php if (!empty($p['message'])): ?>
        <p style="margin-top:1rem;"><span style="color:var(--c-muted);">Mensagem:</span> <?= nl2br(View::e((string) $p['message'])) ?></p>
    <?php endif; ?>
</div>

<!-- Histórico de status -->
<div class="admin-card">
    <h2 style="font-size:1.3rem;">Histórico de status</h2>
    <?php if ($history === []): ?>
        <p class="admin-empty">Nenhuma mudança de status registrada ainda.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr><th>Quando</th><th>De</th><th>Para</th><th>Observação</th></tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><small><?= View::e($fmtDate($h['created_at'] ?? null)) ?></small></td>
                        <td><?= View::e((string) ($h['from_status'] ?? '—')) ?></td>
                        <td><strong><?= View::e((string) ($h['to_status'] ?? '—')) ?></strong></td>
                        <td><?= View::e((string) ($h['note'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
