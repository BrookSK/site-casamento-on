<?php

declare(strict_types=1);

use App\Core\GuestAuth;
use App\Core\View;

/**
 * @var array $app     Config da aplicação
 * @var array $wedding Dados (provisórios) do casamento
 * @var string|null $pageTitle
 */

$baseUrl   = $app['base_url'] ?? '';

// Estado da conta do convidado (para alternar "Entrar" / "Minha conta").
$guestLogged = GuestAuth::check();
$guestUser   = $guestLogged ? GuestAuth::user() : null;
$guestFirst  = $guestLogged ? trim(explode(' ', (string) ($guestUser['name'] ?? ''))[0]) : '';
$bride     = $wedding['bride'] ?? 'Noiva';
$groom     = $wedding['groom'] ?? 'Noivo';
$siteName  = $app['name'] ?? 'Casamento';
$title     = isset($pageTitle) ? $pageTitle . ' · ' . $siteName : $siteName;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Site do casamento de <?= View::e($bride) ?> e <?= View::e($groom) ?>.">
    <meta name="theme-color" content="#8a8f6f">

    <title><?= View::e($title) ?></title>

    <!-- Fontes: carregadas via <link> do Google Fonts (não é dependência de build) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= View::e(View::assetVersioned('css/style.css', $baseUrl)) ?>">
</head>
<body>

<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="site-header" id="topo" data-header>
    <div class="container site-header__inner">
        <a class="brand" href="<?= View::e(View::url('/', $baseUrl)) ?>" aria-label="Início">
            <span class="brand__names"><?= View::e($bride) ?><span class="brand__amp">&amp;</span><?= View::e($groom) ?></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" data-nav-toggle>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
            <span class="sr-only">Abrir menu</span>
        </button>

        <nav class="site-nav" id="menu-principal" aria-label="Navegação principal" data-nav>
            <ul class="site-nav__list">
                <li><a href="#historia">O casal</a></li>
                <li><a href="#evento">O evento</a></li>
                <li><a href="#presentes">Presentes</a></li>
                <li><a href="#rsvp" class="site-nav__cta">Confirmar presença</a></li>
                <?php if ($guestLogged): ?>
                    <li><a href="<?= View::e(View::url('/minha-conta', $baseUrl)) ?>" class="site-nav__account">Minha conta<?= $guestFirst !== '' ? ' (' . View::e($guestFirst) . ')' : '' ?></a></li>
                <?php else: ?>
                    <li><a href="<?= View::e(View::url('/entrar', $baseUrl)) ?>" class="site-nav__account">Entrar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="conteudo">
