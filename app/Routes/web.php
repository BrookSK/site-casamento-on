<?php

declare(strict_types=1);

/**
 * Definição das rotas da aplicação.
 *
 * A variável $router é injetada por App::registerRoutes().
 *
 * @var \App\Core\Router $router
 */

// ---- Site público ----
$router->get('/', 'HomeController@index');

// ---- Administração ----
$router->get('/admin/login', 'AuthController@showLogin');
$router->post('/admin/login', 'AuthController@login');
$router->get('/admin/logout', 'AuthController@logout');
$router->get('/admin', 'AdminController@index'); // protegida (exige login)
