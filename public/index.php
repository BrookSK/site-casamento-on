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

// Carrega o bootstrap da aplicação.
require APP_PATH . '/Core/App.php';

use App\Core\App;

$app = new App();
$app->run();
