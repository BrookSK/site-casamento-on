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
$router->get('/galeria', 'GalleryController@index');
// Página exclusiva de confirmação de presença (endereço usado futuramente no QR Code).
$router->get('/confirmacao-interna', 'RsvpController@internal');
$router->post('/confirmar-presenca', 'RsvpController@store');
$router->post('/confirmar-presenca/acompanhantes', 'RsvpController@updateCompanions');

// Presentes (pagamento real via ASAAS)
$router->post('/presentear', 'GiftController@store');
// Checkout transparente de cartão: recebe os dados do cartão da tela de pagamento.
$router->post('/presentear/cartao', 'GiftController@payCard');

// Webhook do ASAAS (recebe confirmações de pagamento — rota pública)
$router->post('/asaas/webhook', 'AsaasWebhookController@handle');

// ---- Conta do convidado ----
$router->get('/entrar', 'GuestAuthController@show');
$router->post('/entrar', 'GuestAuthController@login');
$router->post('/cadastrar', 'GuestAuthController@register');
$router->get('/sair', 'GuestAuthController@logout');
$router->get('/minha-conta', 'AccountController@index');

// Senha do convidado via link seguro (definição e recuperação)
$router->get('/definir-senha', 'PasswordController@showDefine');
$router->post('/definir-senha', 'PasswordController@define');
$router->get('/recuperar-senha', 'PasswordController@showRequest');
$router->post('/recuperar-senha', 'PasswordController@request');

// ---- Autenticação admin ----
$router->get('/admin/login', 'AuthController@showLogin');
$router->post('/admin/login', 'AuthController@login');
$router->get('/admin/logout', 'AuthController@logout');

// ---- Painel (todas protegidas) ----
$router->get('/admin', 'AdminController@index');

// QR Code de acesso ao site (compartilhamento)
$router->get('/admin/qrcode', 'QrCodeController@index');

// Confirmações
$router->get('/admin/confirmacoes', 'RsvpAdminController@index');
$router->get('/admin/confirmacoes/exportar/excel', 'RsvpAdminController@exportExcel');
$router->get('/admin/confirmacoes/exportar/pdf', 'RsvpAdminController@exportPdf');
$router->post('/admin/confirmacoes/remover', 'RsvpAdminController@destroy');

// Presentes
$router->get('/admin/presentes', 'GiftAdminController@index');
$router->get('/admin/presentes/compras', 'GiftAdminController@purchases');

// Financeiro (gestão das transações de presente)
$router->get('/admin/financeiro', 'FinanceController@index');
$router->get('/admin/financeiro/transacao', 'FinanceController@show');
$router->get('/admin/financeiro/exportar/excel', 'FinanceController@exportExcel');
$router->get('/admin/financeiro/exportar/pdf', 'FinanceController@exportPdf');
$router->get('/admin/presentes/novo', 'GiftAdminController@create');
$router->get('/admin/presentes/importar', 'GiftAdminController@importForm');
$router->get('/admin/presentes/importar/modelo', 'GiftAdminController@importTemplate');
$router->post('/admin/presentes/importar', 'GiftAdminController@import');
$router->get('/admin/presentes/editar', 'GiftAdminController@edit');
$router->post('/admin/presentes/salvar', 'GiftAdminController@save');
$router->post('/admin/presentes/remover', 'GiftAdminController@destroy');
$router->post('/admin/presentes/ordenar', 'GiftAdminController@reorder');

// Textos e imagens (conteúdo das telas públicas)
$router->get('/admin/textos-e-imagens', 'ContentController@index');
$router->post('/admin/textos-e-imagens/salvar', 'ContentController@save');

// Galeria de fotos (álbuns + fotos) — dentro de Conteúdo
$router->get('/admin/galeria', 'AlbumAdminController@index');
$router->get('/admin/galeria/novo', 'AlbumAdminController@create');
$router->get('/admin/galeria/editar', 'AlbumAdminController@edit');
$router->post('/admin/galeria/salvar', 'AlbumAdminController@save');
$router->post('/admin/galeria/ordenar', 'AlbumAdminController@reorder');
$router->post('/admin/galeria/remover', 'AlbumAdminController@destroy');
$router->post('/admin/galeria/fotos/upload', 'AlbumAdminController@uploadPhotos');
$router->post('/admin/galeria/fotos/remover', 'AlbumAdminController@destroyPhoto');

// Configurações (SMTP / ASAAS — base)
$router->get('/admin/configuracoes', 'SettingsController@index');
$router->post('/admin/configuracoes/smtp', 'SettingsController@saveSmtp');
$router->post('/admin/configuracoes/smtp/testar', 'SettingsController@testSmtp');
$router->post('/admin/configuracoes/asaas', 'SettingsController@saveAsaas');
$router->post('/admin/configuracoes/asaas/testar', 'SettingsController@testAsaas');
$router->post('/admin/configuracoes/webhook', 'SettingsController@saveWebhook');
$router->post('/admin/configuracoes/comunicacao', 'SettingsController@saveComm');

// Histórico de comunicações automáticas (envios e falhas)
$router->get('/admin/comunicacoes', 'SettingsController@communications');
