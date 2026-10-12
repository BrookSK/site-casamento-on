<?php

declare(strict_types=1);

/**
 * Cria um usuário administrador do painel.
 *
 * Rode pela LINHA DE COMANDO (não pela web):
 *   php database/create_admin.php "Nome" "email@exemplo.com" "SenhaForte"
 *
 * Ou sem argumentos, para informar interativamente:
 *   php database/create_admin.php
 *
 * A senha é armazenada com hash (password_hash). Nenhuma senha em texto puro
 * é gravada. Este arquivo fica FORA de public/, logo não é acessível pela web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require APP_PATH . '/Core/Database.php';
require APP_PATH . '/Models/Model.php';
require APP_PATH . '/Models/Admin.php';

use App\Models\Admin;

function prompt(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && stripos(PHP_OS, 'WIN') === false) {
        // Em sistemas Unix, oculta a digitação da senha.
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
    } else {
        $value = trim((string) fgets(STDIN));
    }
    return $value;
}

$name     = $argv[1] ?? prompt('Nome: ');
$email    = $argv[2] ?? prompt('E-mail: ');
$password = $argv[3] ?? prompt('Senha: ', true);

// Validações básicas.
if ($name === '' || $email === '' || $password === '') {
    exit("[ERRO] Nome, e-mail e senha são obrigatórios.\n");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("[ERRO] E-mail inválido.\n");
}
if (strlen($password) < 8) {
    exit("[ERRO] A senha deve ter ao menos 8 caracteres.\n");
}

try {
    $admins = new Admin([]);

    if ($admins->findByEmail($email) !== null) {
        exit("[ERRO] Já existe um administrador com esse e-mail.\n");
    }

    $id = $admins->create($name, $email, $password);
    echo "[OK] Administrador criado com sucesso (id {$id}).\n";
    echo "Acesse /admin/login para entrar.\n";
} catch (Throwable $e) {
    echo "[ERRO] " . $e->getMessage() . "\n";
    exit(1);
}
