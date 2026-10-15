<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Perfil do convidado (área protegida).
 *
 * @var array                       $app
 * @var array{id:int,name:string,email:string} $user
 * @var array<string,mixed>|null    $profile
 * @var array<int,array<string,mixed>> $purchases
 */

$baseUrl   = $app['base_url'] ?? '';
$name      = $user['name'] ?? 'Convidado';
$purchases = $purchases ?? [];

$statusLabels = [
    'confirmed' => 'Presença confirmada',
    'declined'  => 'Você informou que não poderá ir',
    'pending'   => 'Confirmação pendente',
];
$rsvpStatus = $profile['rsvp_status'] ?? null;

// Rótulos/estilos do status de pagamento dos presentes (área do convidado).
$payLabels = [
    'pending'  => ['Pagamento pendente', 'pay'],
    'paid'     => ['Pago',               'ok'],
    'refunded' => ['Estornado',          'muted'],
];
$billingLabels = ['PIX' => 'PIX', 'CREDIT_CARD' => 'Cartão', 'BOLETO' => 'Boleto', 'UNDEFINED' => '—'];
$money = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
$fmtDate = static function ($v): string {
    if (empty($v) || str_starts_with((string) $v, '0000')) {
        return '—';
    }
    $ts = strtotime((string) $v);
    return $ts !== false ? date('d/m/Y H:i', $ts) : (string) $v;
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::e(View::assetVersioned('css/style.css', $baseUrl)) ?>">
</head>
<body class="account-page">
    <main class="account">
        <div class="account__card account__card--wide">
            <div class="account__profile-head">
                <p class="account__eyebrow">Minha conta</p>
                <h1 class="account__title">Olá, <?= View::e($name) ?></h1>
            </div>

            <div class="account__info">
                <div class="account__info-row">
                    <span>E-mail</span>
                    <strong><?= View::e($profile['email'] ?? ($user['email'] ?? '—')) ?></strong>
                </div>
                <div class="account__info-row">
                    <span>Telefone</span>
                    <strong><?= View::e($profile['phone'] ?? '—') ?></strong>
                </div>
                <div class="account__info-row">
                    <span>Confirmação de presença</span>
                    <strong>
                        <?= $rsvpStatus !== null
                            ? View::e($statusLabels[$rsvpStatus] ?? '—')
                            : 'Ainda não confirmada' ?>
                    </strong>
                </div>
            </div>

            <section class="account__gifts">
                <h2 class="account__section-title">Meus presentes</h2>

                <?php if ($purchases === []): ?>
                    <div class="account__note">
                        Você ainda não presenteou. Quando enviar um presente, ele
                        aparecerá aqui com o status do pagamento.
                    </div>
                <?php else: ?>
                    <ul class="account-gift-list">
                        <?php foreach ($purchases as $p): ?>
                            <?php
                            $st = (string) ($p['payment_status'] ?? 'pending');
                            [$plabel, $pcls] = $payLabels[$st] ?? ['—', 'muted'];
                            $bt = (string) ($p['billing_type'] ?? 'UNDEFINED');
                            $giftName = (string) ($p['gift_name'] ?? 'Presente');
                            ?>
                            <li class="account-gift">
                                <div class="account-gift__main">
                                    <strong class="account-gift__name"><?= View::e($giftName) ?></strong>
                                    <span class="account-gift__meta">
                                        <?= View::e($billingLabels[$bt] ?? $bt) ?>
                                        &middot; <?= View::e($fmtDate($p['created_at'] ?? null)) ?>
                                    </span>
                                </div>
                                <div class="account-gift__side">
                                    <span class="account-gift__value"><?= $money((int) ($p['amount_cents'] ?? 0)) ?></span>
                                    <span class="account-badge account-badge--<?= View::e($pcls) ?>"><?= View::e($plabel) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <div class="account__actions">
                <a class="btn btn--light" href="<?= View::e(View::url('/', $baseUrl)) ?>">Voltar ao site</a>
                <a class="btn btn--primary" href="<?= View::e(View::url('/sair', $baseUrl)) ?>">Sair</a>
            </div>
        </div>
    </main>
</body>
</html>
