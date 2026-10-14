<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Definição / recuperação de senha via link seguro (token).
 *
 * Serve três cenários controlados por $mode:
 *   - 'set'     : 1ª definição de senha (link de convite após o RSVP);
 *   - 'reset'   : redefinição via "esqueci minha senha";
 *   - 'request' : formulário para solicitar o link de recuperação.
 *
 * @var array       $app
 * @var string      $pageTitle
 * @var string      $mode       'set' | 'reset' | 'request'
 * @var string|null $error
 * @var string|null $notice
 * @var string      $csrfField
 * @var string      $token      Token (set/reset)
 * @var string      $name       Nome do convidado (set/reset)
 */

$baseUrl = $app['base_url'] ?? '';
$mode    = $mode ?? 'request';
$token   = $token ?? '';
$name    = $name ?? '';

$isDefine  = ($mode === 'set' || $mode === 'reset');
$action    = $isDefine
    ? View::url('/definir-senha', $baseUrl)
    : View::url('/recuperar-senha', $baseUrl);

$heading = match ($mode) {
    'set'   => 'Defina sua senha',
    'reset' => 'Redefina sua senha',
    default => 'Recuperar senha',
};
$intro = match ($mode) {
    'set'   => 'Crie uma senha para acessar sua conta e acompanhar tudo sobre o casamento.',
    'reset' => 'Escolha uma nova senha para a sua conta.',
    default => 'Informe o e-mail ou telefone cadastrado. Enviaremos um link seguro para você criar uma nova senha.',
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
        <div class="account__card">
            <a class="account__brand" href="<?= View::e(View::url('/', $baseUrl)) ?>">Voltar ao site</a>

            <h1 class="account__title" style="text-align:center;margin:.2rem 0 .4rem;font-family:'Cormorant Garamond',serif;"><?= View::e($heading) ?></h1>
            <?php if ($isDefine && $name !== ''): ?>
                <p style="text-align:center;margin:0 0 1rem;color:#777;">Olá, <?= View::e($name) ?>!</p>
            <?php endif; ?>
            <p style="text-align:center;margin:0 0 1.2rem;color:#777;line-height:1.5;"><?= View::e($intro) ?></p>

            <?php if (!empty($error)): ?>
                <div class="account__alert" role="alert"><?= View::e($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($notice)): ?>
                <div class="account__alert" role="status" style="background:#eef3e6;color:#4b5d2a;"><?= View::e($notice) ?></div>
            <?php endif; ?>

            <?php if ($isDefine): ?>
                <!-- Formulário de definição/redefinição de senha -->
                <form class="account__form is-active" method="post" action="<?= View::e($action) ?>">
                    <?= $csrfField ?>
                    <input type="hidden" name="mode" value="<?= View::e($mode) ?>">
                    <input type="hidden" name="token" value="<?= View::e($token) ?>">
                    <div class="account-field">
                        <label for="pass">Nova senha (mín. 8 caracteres)</label>
                        <input type="password" id="pass" name="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="account-field">
                        <label for="pass2">Confirme a senha</label>
                        <input type="password" id="pass2" name="password_confirm" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn--primary account__submit">Salvar senha</button>
                </form>
            <?php else: ?>
                <!-- Formulário de solicitação do link de recuperação -->
                <form class="account__form is-active" method="post" action="<?= View::e($action) ?>">
                    <?= $csrfField ?>
                    <div class="account-field">
                        <label for="identifier">E-mail ou telefone</label>
                        <input type="text" id="identifier" name="identifier" autocomplete="username" required>
                    </div>
                    <button type="submit" class="btn btn--primary account__submit">Enviar link seguro</button>
                </form>
            <?php endif; ?>

            <p style="text-align:center;margin:1rem 0 0;">
                <a href="<?= View::e(View::url('/entrar', $baseUrl)) ?>" style="color:#8a8f6f;">Voltar para o login</a>
            </p>
        </div>
    </main>
</body>
</html>
