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
        <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Quando</th>
                    <th>Convidado</th>
                    <th>Canal</th>
                    <th>Tipo</th>
                    <th>Destino</th>
                    <th>Status</th>
                    <th>Observação</th>
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
                ?>
                <tr>
                    <td><?= View::e($whenFmt) ?></td>
                    <td><?= View::e((string) ($row['guest_name'] ?? '—')) ?></td>
                    <td><?= View::e($channelLabels[$channel] ?? $channel) ?></td>
                    <td><?= View::e($typeLabels[$type] ?? $type) ?></td>
                    <td><?= View::e((string) ($row['recipient'] ?? '—')) ?></td>
                    <td>
                        <span class="badge badge--<?= View::e($cls) ?>">
                            <?= View::e($statusLabels[$status] ?? $status) ?>
                        </span>
                    </td>
                    <td><?= View::e((string) ($row['error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<style>
/* Estilos mínimos para os badges de status (reaproveita a paleta do admin). */
.badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; }
.badge--ok    { background:#e6f3e6; color:#2f7d32; }
.badge--err   { background:#fdecec; color:#c0392b; }
.badge--muted { background:#eee; color:#777; }
.table-wrap { overflow-x:auto; }
</style>
