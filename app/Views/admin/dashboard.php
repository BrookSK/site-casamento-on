<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Painel administrativo (área protegida).
 *
 * @var array  $app
 * @var string $pageTitle
 * @var array{id:int,name:string,email:string}|null $admin
 */

$baseUrl = $app['base_url'] ?? '';
$name    = $admin['name'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= View::e($pageTitle) ?> · Administração</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::e(View::asset('css/admin.css', $baseUrl)) ?>">
</head>
<body>
    <header class="admin-topbar">
        <span class="admin-topbar__brand">Administração</span>
        <div class="admin-topbar__right">
            <span>Olá, <?= View::e($name) ?></span>
            <a href="<?= View::e(View::url('/admin/logout', $baseUrl)) ?>">Sair</a>
        </div>
    </header>

    <main class="admin-main">
        <div class="admin-card">
            <h2>Bem-vindo(a) ao painel</h2>
            <p>Você está autenticado(a) na área administrativa.</p>

            <div class="admin-note">
                Esta é a base do painel. O gerenciamento de confirmações de
                presença, lista de presentes e demais recursos será adicionado
                nas próximas etapas.
            </div>
        </div>
    </main>
</body>
</html>
