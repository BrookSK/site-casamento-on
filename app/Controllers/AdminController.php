<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Gift;
use App\Models\Rsvp;
use Throwable;

/**
 * Painel administrativo (área protegida) — visão geral.
 *
 * O gerenciamento de confirmações, presentes e configurações fica em
 * controllers próprios (RsvpAdminController, GiftAdminController,
 * SettingsController), todos exigindo autenticação.
 */
final class AdminController extends Controller
{
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }

        $rsvpSummary = ['total' => 0, 'confirmed' => 0, 'declined' => 0, 'pending' => 0, 'people' => 0];
        $giftCount   = 0;
        $dbError     = null;

        try {
            $rsvpSummary = (new Rsvp($this->config))->summary();
            $giftCount   = (new Gift($this->config))->countAll();
        } catch (Throwable $e) {
            error_log('[Admin] Erro ao carregar dashboard: ' . $e->getMessage());
            $dbError = 'Não foi possível carregar os dados do banco.';
        }

        $this->viewAdmin('admin/dashboard', [
            'pageTitle'   => 'Visão geral',
            'active'      => 'dashboard',
            'admin'       => Auth::user(),
            'rsvpSummary' => $rsvpSummary,
            'giftCount'   => $giftCount,
            'dbError'     => $dbError,
        ]);
    }
}
