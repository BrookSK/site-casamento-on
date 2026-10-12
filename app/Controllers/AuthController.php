<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Admin;
use Throwable;

/**
 * Login e logout do administrador.
 *
 * Segurança: senha via password_verify, CSRF nos formulários, mensagens de
 * erro genéricas e regeneração de sessão no login (ver Auth).
 */
final class AuthController extends Controller
{
    /** Exibe o formulário de login (ou redireciona se já logado). */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/admin');
        }

        $this->viewRaw('admin/login', [
            'pageTitle' => 'Entrar',
            'error'     => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Processa o POST do login. */
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/admin');
        }

        $token = $_POST[Csrf::fieldName()] ?? null;
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            $this->renderLoginError('Sessão expirada. Tente novamente.');
            return;
        }

        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->renderLoginError('Informe e-mail e senha.');
            return;
        }

        try {
            $admin = (new Admin($this->config))->verifyCredentials($email, $password);
        } catch (Throwable $e) {
            error_log('[Auth] Erro ao verificar credenciais: ' . $e->getMessage());
            $this->renderLoginError('Não foi possível entrar agora. Tente mais tarde.');
            return;
        }

        if ($admin === null) {
            // Mensagem genérica: não revela se o e-mail existe.
            $this->renderLoginError('E-mail ou senha inválidos.');
            return;
        }

        Auth::login($admin);
        $this->redirect('/admin');
    }

    /** Encerra a sessão. */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/admin/login');
    }

    private function renderLoginError(string $message): void
    {
        http_response_code(422);
        $this->viewRaw('admin/login', [
            'pageTitle' => 'Entrar',
            'error'     => $message,
            'csrfField' => Csrf::field(),
        ]);
    }
}
