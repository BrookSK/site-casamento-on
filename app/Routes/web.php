<?php

declare(strict_types=1);

/**
 * Definição das rotas da aplicação.
 *
 * A variável $router é injetada por App::registerRoutes().
 * Nesta etapa existe apenas a página inicial.
 *
 * @var \App\Core\Router $router
 */

$router->get('/', 'HomeController@index');
