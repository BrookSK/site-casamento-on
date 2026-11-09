<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Páginas de erro do site público (renderizadas com o layout padrão).
 */
final class ErrorController extends Controller
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404', [
            'pageTitle' => 'Página não encontrada',
            // Fundo claro: header sólido e legível desde o topo.
            'bodyClass' => 'page-light',
        ]);
    }
}
