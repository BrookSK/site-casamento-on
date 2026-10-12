<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Painel administrativo (área protegida).
 *
 * Nesta etapa há apenas o painel inicial, que exige login. O gerenciamento
 * completo (confirmações, presentes, etc.) será implementado adiante.
 */
final class AdminController extends Controller
{
    /** Garante que só admins autenticados acessem. */
    private function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Painel inicial. */
    public function index(): void
    {
        $this->requireAuth();

        $this->viewRaw('admin/dashboard', [
            'pageTitle' => 'Painel',
            'admin'     => Auth::user(),
        ]);
    }
}
