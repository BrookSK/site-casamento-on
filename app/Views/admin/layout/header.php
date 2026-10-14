<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Cabeçalho do layout administrativo (sidebar + topbar).
 *
 * @var array  $app
 * @var string $pageTitle
 * @var string $active     Chave do item de menu ativo
 * @var array{id:int,name:string,email:string}|null $admin
 */

$baseUrl = $app['base_url'] ?? '';
$active  = $active ?? '';
$name    = $admin['name'] ?? 'Administrador';

$nav = [
    'dashboard' => ['label' => 'Visão geral', 'url' => '/admin'],
    'rsvps'     => ['label' => 'Confirmações', 'url' => '/admin/confirmacoes'],
    'gifts'     => ['label' => 'Presentes',    'url' => '/admin/presentes'],
    'purchases' => ['label' => 'Contribuições','url' => '/admin/presentes/compras'],
    'content'   => ['label' => 'Conteúdo', 'url' => '/admin/textos-e-imagens'],
    'settings'  => ['label' => 'Configurações','url' => '/admin/configuracoes'],
    'communications' => ['label' => 'Comunicações', 'url' => '/admin/comunicacoes'],
];
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
    <link rel="stylesheet" href="<?= View::e(View::assetVersioned('css/admin.css', $baseUrl)) ?>">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">Administração</div>
        <nav class="admin-nav" aria-label="Menu do painel">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= View::e(View::url($item['url'], $baseUrl)) ?>"
                   class="<?= $active === $key ? 'is-active' : '' ?>">
                    <?= View::e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar__footer">
            <a href="<?= View::e(View::url('/', $baseUrl)) ?>" target="_blank" rel="noopener">Ver o site &rarr;</a>
        </div>
    </aside>

    <div class="admin-content">
        <header class="admin-topbar">
            <span class="admin-topbar__title"><?= View::e($pageTitle) ?></span>
            <div class="admin-topbar__right">
                <span>Olá, <?= View::e($name) ?></span>
                <a href="<?= View::e(View::url('/admin/logout', $baseUrl)) ?>">Sair</a>
            </div>
        </header>

        <main class="admin-main">
