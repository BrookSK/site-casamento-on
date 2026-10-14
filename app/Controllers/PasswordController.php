<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Core\Notifier;
use App\Models\Guest;
use Throwable;

/**
 * Fluxo de senha do convidado via LINK SEGURO (ponto 7):
 *
 *   - GET  /definir-senha?token=...   → formulário (invite OU reset).
 *   - POST /definir-senha             → grava a senha e faz login.
 *   - GET  /recuperar-senha           → formulário "esqueci minha senha".
 *   - POST /recuperar-senha           → gera token e envia o link por e-mail/WhatsApp.
 *
 * O token do link é validado contra o banco (existência + validade). Após
 * definir a senha, ambos os tokens (convite e recuperação) são invalidados.
 */
final class PasswordController extends Controller
{
    /** Exibe o formulário de definição/redefinição a partir do token do link. */
    public function showDefine(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            $this->renderDefineInvalid();
            return;
        }

        try {
            $model = new Guest($this->config);
            // Tenta como token de convite (1ª senha) e depois como reset.
            $guest = $model->findByInviteToken($token);
            $mode  = 'set';
            if ($guest === null) {
                $guest = $model->findByResetToken($token);
                $mode  = 'reset';
            }
        } catch (Throwable $e) {
            error_log('[Password] showDefine: ' . $e->getMessage());
            $this->renderDefineInvalid();
            return;
        }

        if ($guest === null) {
            $this->renderDefineInvalid();
            return;
        }

        $this->viewRaw('account/password', [
            'pageTitle' => 'Definir senha',
            'mode'      => $mode,
            'token'     => $token,
            'name'      => (string) ($guest['name'] ?? ''),
            'error'     => null,
            'notice'    => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Processa a definição/redefinição da senha. */
    public function define(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->renderDefineInvalid('Sessão expirada. Abra o link novamente.');
            return;
        }

        $token    = trim((string) ($_POST['token'] ?? ''));
        $mode     = ($_POST['mode'] ?? '') === 'reset' ? 'reset' : 'set';
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if ($token === '') {
            $this->renderDefineInvalid();
            return;
        }

        try {
            $model = new Guest($this->config);
            $guest = $mode === 'reset'
                ? $model->findByResetToken($token)
                : $model->findByInviteToken($token);

            // Compatibilidade: se não achou no modo informado, tenta o outro.
            if ($guest === null) {
                $guest = $model->findByInviteToken($token) ?? $model->findByResetToken($token);
            }
        } catch (Throwable $e) {
            error_log('[Password] define lookup: ' . $e->getMessage());
            $this->renderDefineInvalid();
            return;
        }

        if ($guest === null) {
            $this->renderDefineInvalid();
            return;
        }

        // Validações da senha.
        if (strlen($password) < 8) {
            $this->renderDefineForm($mode, $token, (string) $guest['name'], 'A senha deve ter ao menos 8 caracteres.');
            return;
        }
        if ($password !== $confirm) {
            $this->renderDefineForm($mode, $token, (string) $guest['name'], 'As senhas não conferem.');
            return;
        }

        try {
            $model->setPassword((int) $guest['id'], $password);
        } catch (Throwable $e) {
            error_log('[Password] define save: ' . $e->getMessage());
            $this->renderDefineForm($mode, $token, (string) $guest['name'], 'Não foi possível salvar a senha agora.');
            return;
        }

        // Login automático após definir a senha.
        try {
            GuestAuth::login([
                'id'    => (int) $guest['id'],
                'name'  => (string) $guest['name'],
                'email' => (string) ($guest['email'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('[Password] autologin: ' . $e->getMessage());
        }

        $this->redirect('/minha-conta');
    }

    /** Exibe o formulário "esqueci minha senha". */
    public function showRequest(): void
    {
        $this->viewRaw('account/password', [
            'pageTitle' => 'Recuperar senha',
            'mode'      => 'request',
            'token'     => '',
            'name'      => '',
            'error'     => null,
            'notice'    => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Processa a solicitação do link de recuperação. */
    public function request(): void
    {
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->renderRequest('Sessão expirada. Tente novamente.');
            return;
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        // Mensagem genérica (não revela se a conta existe) — evita enumeração.
        $notice = 'Se encontrarmos uma conta com esses dados, enviaremos um link '
            . 'seguro para redefinir a senha.';

        if ($identifier === '') {
            $this->renderRequest('Informe o e-mail ou telefone cadastrado.');
            return;
        }

        try {
            $model = new Guest($this->config);
            $guest = $model->findByIdentifier($identifier);

            if ($guest !== null) {
                $token = $model->startPasswordReset((int) $guest['id'], 2);
                $link  = $this->passwordLink($token);
                (new Notifier($this->config))->sendPasswordReset([
                    'id'    => (int) $guest['id'],
                    'name'  => (string) $guest['name'],
                    'email' => $guest['email'] ?? null,
                    'phone' => $guest['phone'] ?? null,
                ], $link);
            }
        } catch (Throwable $e) {
            error_log('[Password] request: ' . $e->getMessage());
            // Ainda assim mostra a mensagem genérica.
        }

        $this->renderRequest(null, $notice);
    }

    /** Monta a URL absoluta do link de definição/recuperação de senha. */
    private function passwordLink(string $token): string
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }
        return $baseUrl . '/definir-senha?token=' . urlencode($token);
    }

    // ---- Renderização auxiliar ----

    private function renderDefineForm(string $mode, string $token, string $name, string $error): void
    {
        http_response_code(422);
        $this->viewRaw('account/password', [
            'pageTitle' => 'Definir senha',
            'mode'      => $mode === 'reset' ? 'reset' : 'set',
            'token'     => $token,
            'name'      => $name,
            'error'     => $error,
            'notice'    => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    private function renderDefineInvalid(?string $error = null): void
    {
        http_response_code(400);
        $this->viewRaw('account/password', [
            'pageTitle' => 'Link inválido',
            'mode'      => 'request',
            'token'     => '',
            'name'      => '',
            'error'     => $error ?? 'Este link é inválido ou expirou. Solicite um novo abaixo.',
            'notice'    => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    private function renderRequest(?string $error = null, ?string $notice = null): void
    {
        if ($error !== null) {
            http_response_code(422);
        }
        $this->viewRaw('account/password', [
            'pageTitle' => 'Recuperar senha',
            'mode'      => 'request',
            'token'     => '',
            'name'      => '',
            'error'     => $error,
            'notice'    => $notice,
            'csrfField' => Csrf::field(),
        ]);
    }
}
