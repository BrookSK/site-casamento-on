<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Models\Guest;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Login e cadastro de convidados (área pública).
 */
final class GuestAuthController extends Controller
{
    /** Tela de login + cadastro. */
    public function show(): void
    {
        if (GuestAuth::check()) {
            $this->redirect('/minha-conta');
        }

        $this->renderAuth();
    }

    /** Processa o login (identificador = e-mail ou telefone). */
    public function login(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->renderAuth('login', 'Sessão expirada. Tente novamente.');
            return;
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->renderAuth('login', 'Informe seu e-mail ou telefone e a senha.');
            return;
        }

        try {
            $guest = (new Guest($this->config))->verifyLogin($identifier, $password);
        } catch (Throwable $e) {
            error_log('[GuestAuth] login: ' . $e->getMessage());
            $this->renderAuth('login', 'Não foi possível entrar agora. Tente mais tarde.');
            return;
        }

        if ($guest === null) {
            $this->renderAuth('login', 'E-mail/telefone ou senha inválidos.');
            return;
        }

        GuestAuth::login($guest);
        $this->redirect('/minha-conta');
    }

    /** Processa o cadastro. */
    public function register(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->renderAuth('register', 'Sessão expirada. Tente novamente.');
            return;
        }

        $name     = trim((string) ($_POST['name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $phone    = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // Validações.
        if ($name === '') {
            $this->renderAuth('register', 'Informe seu nome.');
            return;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->renderAuth('register', 'Informe um e-mail válido.');
            return;
        }
        $phoneNorm = Guest::normalizePhone($phone);
        if (strlen($phoneNorm) !== 11) {
            $this->renderAuth('register', 'Informe um telefone com DDD (11 dígitos).');
            return;
        }
        if (strlen($password) < 8) {
            $this->renderAuth('register', 'A senha deve ter ao menos 8 caracteres.');
            return;
        }

        try {
            $guest = (new Guest($this->config))->register([
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'password' => $password,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'exists') {
                $this->renderAuth('login', 'Você já tem uma conta. Faça login.');
                return;
            }
            if ($e->getMessage() === 'conflict') {
                $this->renderAuth('register', 'E-mail e telefone pertencem a cadastros diferentes. Verifique os dados.');
                return;
            }
            error_log('[GuestAuth] register: ' . $e->getMessage());
            $this->renderAuth('register', 'Não foi possível criar a conta agora.');
            return;
        } catch (PDOException $e) {
            // Corrida: e-mail/telefone já existente com restrição única.
            $this->renderAuth('register', 'E-mail ou telefone já cadastrado. Tente fazer login.');
            return;
        } catch (Throwable $e) {
            error_log('[GuestAuth] register: ' . $e->getMessage());
            $this->renderAuth('register', 'Não foi possível criar a conta agora.');
            return;
        }

        GuestAuth::login($guest);
        $this->redirect('/minha-conta');
    }

    public function logout(): void
    {
        GuestAuth::logout();
        $this->redirect('/');
    }

    /**
     * Renderiza a tela de login/cadastro.
     *
     * @param string      $tab   'login' | 'register' (aba inicial ativa)
     * @param string|null $error Mensagem de erro a exibir
     */
    private function renderAuth(string $tab = 'login', ?string $error = null): void
    {
        if ($error !== null) {
            http_response_code(422);
        }
        $this->viewRaw('account/login', [
            'pageTitle' => 'Entrar',
            'tab'       => $tab,
            'error'     => $error,
            'csrfField' => Csrf::field(),
            'old'       => [
                'identifier' => (string) ($_POST['identifier'] ?? ''),
                'name'       => (string) ($_POST['name'] ?? ''),
                'email'      => (string) ($_POST['email'] ?? ''),
                'phone'      => (string) ($_POST['phone'] ?? ''),
            ],
        ]);
    }
}
