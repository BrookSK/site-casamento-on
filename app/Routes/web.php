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
$router->post('/confirmar-presenca', 'RsvpController@store');

// ---- Autenticação admin ----
$router->get('/admin/login', 'AuthController@showLogin');
$router->post('/admin/login', 'AuthController@login');
$router->get('/admin/logout', 'AuthController@logout');

// ---- Painel (todas protegidas) ----
$router->get('/admin', 'AdminController@index');

// Confirmações
$router->get('/admin/confirmacoes', 'RsvpAdminController@index');
$router->post('/admin/confirmacoes/remover', 'RsvpAdminController@destroy');

// Presentes
$router->get('/admin/presentes', 'GiftAdminController@index');
$router->get('/admin/presentes/novo', 'GiftAdminController@create');
$router->get('/admin/presentes/editar', 'GiftAdminController@edit');
$router->post('/admin/presentes/salvar', 'GiftAdminController@save');
$router->post('/admin/presentes/remover', 'GiftAdminController@destroy');

// Configurações (SMTP / ASAAS — base)
$router->get('/admin/configuracoes', 'SettingsController@index');
$router->post('/admin/configuracoes/smtp', 'SettingsController@saveSmtp');
$router->post('/admin/configuracoes/asaas', 'SettingsController@saveAsaas');
