<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\GuestAuth;
use App\Models\GiftPurchase;
use App\Models\Guest;
use Throwable;

/**
 * Área do convidado (perfil) — exige login de convidado.
 */
final class AccountController extends Controller
{
    public function index(): void
    {
        if (!GuestAuth::check()) {
            $this->redirect('/entrar');
        }

        $user      = GuestAuth::user();
        $profile   = null;
        $purchases = [];

        try {
            $profile = (new Guest($this->config))->profile((int) $user['id']);
        } catch (Throwable $e) {
            error_log('[Account] profile: ' . $e->getMessage());
        }

        // Presentes/pagamentos do convidado (vinculados por guest_id).
        try {
            $purchases = (new GiftPurchase($this->config))->listForGuest((int) $user['id']);
        } catch (Throwable $e) {
            error_log('[Account] purchases: ' . $e->getMessage());
        }

        $this->viewRaw('account/profile', [
            'pageTitle' => 'Minha conta',
            'user'      => $user,
            'profile'   => $profile,
            'purchases' => $purchases,
        ]);
    }
}
