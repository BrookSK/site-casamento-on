<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Login + cadastro de convidados (página pública standalone).
 *
 * @var array       $app
 * @var string      $pageTitle
 * @var string      $tab        'login' | 'register'
 * @var string|null $error
 * @var string      $csrfField
 * @var array<string,string> $old
 */

$baseUrl = $app['base_url'] ?? '';
$tab     = $tab ?? 'login';
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
        <div class="account__card">
            <a class="account__brand" href="<?= View::e(View::url('/', $baseUrl)) ?>">Voltar ao site</a>

            <div class="account__tabs" role="tablist">
                <button type="button" class="account__tab <?= $tab === 'login' ? 'is-active' : '' ?>"
                        data-account-tab="login">Entrar</button>
                <button type="button" class="account__tab <?= $tab === 'register' ? 'is-active' : '' ?>"
                        data-account-tab="register">Criar conta</button>
            </div>

            <?php if (!empty($error)): ?>
                <div class="account__alert" role="alert"><?= View::e($error) ?></div>
            <?php endif; ?>

            <!-- ===== Login ===== -->
            <form class="account__form <?= $tab === 'login' ? 'is-active' : '' ?>"
                  data-account-form="login" method="post"
                  action="<?= View::e(View::url('/entrar', $baseUrl)) ?>">
                <?= $csrfField ?>
                <div class="account-field">
                    <label for="login-id">E-mail ou telefone</label>
                    <input type="text" id="login-id" name="identifier"
                           value="<?= View::e($old['identifier'] ?? '') ?>" autocomplete="username" required>
                </div>
                <div class="account-field">
                    <label for="login-pass">Senha</label>
                    <input type="password" id="login-pass" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn--primary account__submit">Entrar</button>
            </form>

            <!-- ===== Cadastro ===== -->
            <form class="account__form <?= $tab === 'register' ? 'is-active' : '' ?>"
                  data-account-form="register" method="post"
                  action="<?= View::e(View::url('/cadastrar', $baseUrl)) ?>">
                <?= $csrfField ?>
                <div class="account-field">
                    <label for="reg-name">Nome completo</label>
                    <input type="text" id="reg-name" name="name" value="<?= View::e($old['name'] ?? '') ?>" required>
                </div>
                <div class="account-field">
                    <label for="reg-email">E-mail</label>
                    <input type="email" id="reg-email" name="email" value="<?= View::e($old['email'] ?? '') ?>" required>
                </div>
                <div class="account-field">
                    <label for="reg-phone">Telefone (com DDD)</label>
                    <input type="text" id="reg-phone" name="phone" value="<?= View::e($old['phone'] ?? '') ?>"
                           placeholder="(11) 98888-7777" inputmode="numeric" data-mask-phone required>
                </div>
                <div class="account-field">
                    <label for="reg-pass">Senha (mín. 8 caracteres)</label>
                    <input type="password" id="reg-pass" name="password" autocomplete="new-password" minlength="8" required>
                </div>
                <button type="submit" class="btn btn--primary account__submit">Criar conta</button>
            </form>
        </div>
    </main>

    <script src="<?= View::e(View::assetVersioned('js/account.js', $baseUrl)) ?>" defer></script>
</body>
</html>
