<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Controlador da página inicial.
 *
 * Monta os dados (todos provenientes da config / placeholders) e renderiza
 * a view home. Nenhum acesso a banco de dados nesta etapa.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home', [
            'pageTitle' => $this->config['wedding']['bride'] . ' & ' . $this->config['wedding']['groom'],
            'gifts'     => $this->config['gifts'] ?? [],
        ]);
    }
}
