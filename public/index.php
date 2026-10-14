<?php

declare(strict_types=1);

/**
 * Front controller — ÚNICO ponto de entrada da aplicação.
 *
 * Toda requisição HTTP passa por aqui. O diretório app/ fica fora da raiz
 * pública (public/) por segurança.
 */

// Caminhos base.
define('PUBLIC_PATH', __DIR__);
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Autoload do Composer (dependências de terceiros: PhpSpreadsheet, mPDF).
// Carregado só quando presente — assim o site continua funcionando mesmo
// antes de rodar `composer install` (apenas a exportação exigirá o vendor).
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

// Carrega o bootstrap da aplicação.
require APP_PATH . '/Core/App.php';

use App\Core\App;

$app = new App();
$app->run();
