<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Tela de login do administrador.
 *
 * @var array       $app
 * @var string      $pageTitle
 * @var string|null $error
 * @var string      $csrfField  HTML do campo CSRF
 */

$baseUrl = $app['base_url'] ?? '';
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
    <main class="login">
        <div class="login__card">
            <div class="login__brand">
                <h1>Administração</h1>
                <p>Acesso restrito</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert" role="alert"><?= View::e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= View::e(View::url('/admin/login', $baseUrl)) ?>" novalidate>
                <?= $csrfField /* HTML já escapado em Csrf::field() */ ?>

                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autocomplete="username"
                           required autofocus>
                </div>

                <div class="field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-block">Entrar</button>
            </form>
        </div>
    </main>
</body>
</html>
