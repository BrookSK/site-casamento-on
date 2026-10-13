<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Contribuições/compras de presentes (admin) — status de pagamento ASAAS.
 *
 * @var array        $app
 * @var array<int, array<string, mixed>> $rows
 * @var string|null  $error
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';

$statusLabels = [
    'pending'  => ['Pendente',  'badge--reserved'],
    'paid'     => ['Pago',      'badge--purchased'],
    'failed'   => ['Falhou',    'badge--available'],
    'refunded' => ['Estornado', 'badge--available'],
];

$billingLabels = [
    'PIX'         => 'PIX',
    'CREDIT_CARD' => 'Cartão',
    'BOLETO'      => 'Boleto',
    'UNDEFINED'   => '—',
];

$money = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhuma contribuição recebida ainda.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Presente</th>
                    <th>Quem presenteou</th>
                    <th>Valor</th>
                    <th>Forma</th>
                    <th>Status</th>
                    <th>Referência</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $p): ?>
                    <?php
                    $st = (string) ($p['payment_status'] ?? 'pending');
                    [$label, $cls] = $statusLabels[$st] ?? ['—', ''];
                    $bt = (string) ($p['billing_type'] ?? 'UNDEFINED');
                    ?>
                    <tr>
                        <td><strong><?= View::e((string) ($p['gift_name'] ?? '—')) ?></strong></td>
                        <td>
                            <?= View::e((string) ($p['buyer_name'] ?? '—')) ?>
                            <?php if (!empty($p['buyer_email'])): ?>
                                <br><small><?= View::e((string) $p['buyer_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $money((int) ($p['amount_cents'] ?? 0)) ?></td>
                        <td><?= View::e($billingLabels[$bt] ?? $bt) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td><small><?= View::e((string) ($p['payment_ref'] ?? '—')) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
