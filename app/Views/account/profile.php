<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Perfil do convidado (área protegida).
 *
 * @var array                       $app
 * @var array{id:int,name:string,email:string} $user
 * @var array<string,mixed>|null    $profile
 */

$baseUrl = $app['base_url'] ?? '';
$name    = $user['name'] ?? 'Convidado';

$statusLabels = [
    'confirmed' => 'Presença confirmada',
    'declined'  => 'Você informou que não poderá ir',
    'pending'   => 'Confirmação pendente',
];
$rsvpStatus = $profile['rsvp_status'] ?? null;
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

            <div class="account__note">
                Em breve você poderá acompanhar seus presentes e pagamentos por aqui.
            </div>

            <div class="account__actions">
                <a class="btn btn--light" href="<?= View::e(View::url('/', $baseUrl)) ?>">Voltar ao site</a>
                <a class="btn btn--primary" href="<?= View::e(View::url('/sair', $baseUrl)) ?>">Sair</a>
            </div>
        </div>
    </main>
</body>
</html>
