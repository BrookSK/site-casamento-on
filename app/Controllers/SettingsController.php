<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Models\Setting;
use Throwable;

/**
 * Configurações do site e das integrações (SMTP e ASAAS).
 *
 * NESTA ETAPA: apenas a BASE. As telas salvam os parâmetros no banco
 * (tabela settings). Os valores sensíveis (senha SMTP, chave ASAAS) são
 * cifrados antes de salvar. Ainda NÃO há envio de e-mail nem cobrança real.
 */
final class SettingsController extends Controller
{
    /** Chaves públicas (texto puro) por grupo. */
    private const SMTP_PLAIN    = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_security', 'smtp_from_email', 'smtp_from_name'];
    private const ASAAS_PLAIN   = ['asaas_environment', 'asaas_webhook_token'];
    private const WEBHOOK_PLAIN = ['webhook_rsvp_url', 'webhook_rsvp_enabled'];

    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    public function index(): void
    {
        $this->guard();

        $values = [];
        $error  = null;
        $hasKey = Crypto::hasKey();

        try {
            $model = new Setting($this->config);
            $keys  = array_merge(self::SMTP_PLAIN, self::ASAAS_PLAIN, self::WEBHOOK_PLAIN);
            $values = $model->getMany($keys);

            // Para os segredos, não devolvemos o valor; só indicamos se já existe.
            $values['smtp_password_set'] = $model->get('smtp_password') !== null ? '1' : '';
            $values['asaas_api_key_set'] = $model->get('asaas_api_key') !== null ? '1' : '';
        } catch (Throwable $e) {
            error_log('[Settings] ' . $e->getMessage());
            $error = 'Não foi possível carregar as configurações.';
        }

        $this->viewAdmin('admin/settings/index', [
            'pageTitle' => 'Configurações',
            'active'    => 'settings',
            'admin'     => Auth::user(),
            'values'    => $values,
            'error'     => $error,
            'hasKey'    => $hasKey,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    /** Salva as configurações de SMTP. */
    public function saveSmtp(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/configuracoes');
        }

        try {
            $model = new Setting($this->config);
            $model->setMany([
                'smtp_host'       => trim((string) ($_POST['smtp_host'] ?? '')),
                'smtp_port'       => trim((string) ($_POST['smtp_port'] ?? '')),
                'smtp_user'       => trim((string) ($_POST['smtp_user'] ?? '')),
                'smtp_security'   => in_array($_POST['smtp_security'] ?? '', ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_security'] : 'tls',
                'smtp_from_email' => trim((string) ($_POST['smtp_from_email'] ?? '')),
                'smtp_from_name'  => trim((string) ($_POST['smtp_from_name'] ?? '')),
            ]);

            // Senha: só atualiza se enviada (cifrada). Em branco mantém a atual.
            $pwd = (string) ($_POST['smtp_password'] ?? '');
            if ($pwd !== '') {
                $this->saveSecret($model, 'smtp_password', $pwd);
            }

            $this->setFlash('success', 'Configurações de SMTP salvas.');
        } catch (Throwable $e) {
            error_log('[Settings] saveSmtp: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar: ' . $e->getMessage());
        }

        $this->redirect('/admin/configuracoes');
    }

    /**
     * Envia um e-mail de teste usando as configurações de SMTP já salvas.
     * Útil para o administrador validar host/porta/credenciais no painel.
     */
    public function testSmtp(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/configuracoes');
        }

        try {
            $mailer = Mailer::fromSettings($this->config);

            // Destinatário: o campo informado no formulário ou, na falta dele,
            // o próprio e-mail remetente configurado.
            $model = new Setting($this->config);
            $to    = trim((string) ($_POST['smtp_test_to'] ?? ''));
            if ($to === '') {
                $to = trim((string) ($model->get('smtp_from_email') ?? ''));
            }
            if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                $this->setFlash('error', 'Informe um e-mail de destino válido para o teste.');
                $this->redirect('/admin/configuracoes');
            }

            $site    = (string) ($this->config['app']['name'] ?? 'o site');
            $subject = 'Teste de envio — ' . $site;
            $html    = '<p>Este é um e-mail de teste enviado pelo painel de '
                . 'configurações de <strong>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8')
                . '</strong>.</p><p>Se você recebeu esta mensagem, o SMTP está '
                . 'configurado corretamente.</p>';

            $mailer->send($to, $subject, $html);

            $this->setFlash('success', 'E-mail de teste enviado para ' . $to . '.');
        } catch (Throwable $e) {
            error_log('[Settings] testSmtp: ' . $e->getMessage());
            $this->setFlash('error', 'Falha no envio de teste: ' . $e->getMessage());
        }

        $this->redirect('/admin/configuracoes');
    }

    /** Salva as configurações do ASAAS. */
    public function saveAsaas(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/configuracoes');
        }

        try {
            $model = new Setting($this->config);
            $env = in_array($_POST['asaas_environment'] ?? '', ['sandbox', 'production'], true)
                ? (string) $_POST['asaas_environment'] : 'sandbox';
            $model->set('asaas_environment', $env);

            // Token do webhook (texto puro): valida o header asaas-access-token
            // enviado pelo ASAAS. É sempre gravado — deixar em branco limpa o
            // token (o webhook passa a ser aceito sem validação de token).
            $model->set('asaas_webhook_token', trim((string) ($_POST['asaas_webhook_token'] ?? '')));

            $key = (string) ($_POST['asaas_api_key'] ?? '');
            if ($key !== '') {
                $this->saveSecret($model, 'asaas_api_key', $key);
            }

            $this->setFlash('success', 'Configurações do ASAAS salvas.');
        } catch (Throwable $e) {
            error_log('[Settings] saveAsaas: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar: ' . $e->getMessage());
        }

        $this->redirect('/admin/configuracoes');
    }

    /** Testa a conexão com o ASAAS usando a chave já salva. */
    public function testAsaas(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/configuracoes');
        }

        try {
            $asaas  = \App\Core\Asaas::fromSettings($this->config);
            $result = $asaas->ping();
            $this->setFlash($result['ok'] ? 'success' : 'error', $result['message']);
        } catch (Throwable $e) {
            error_log('[Settings] testAsaas: ' . $e->getMessage());
            $this->setFlash('error', $e->getMessage());
        }

        $this->redirect('/admin/configuracoes');
    }

    /** Salva as configurações do Webhook de confirmações (RSVP). */
    public function saveWebhook(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/configuracoes');
        }

        try {
            $model = new Setting($this->config);

            $url     = trim((string) ($_POST['webhook_rsvp_url'] ?? ''));
            $enabled = isset($_POST['webhook_rsvp_enabled']) ? '1' : '';

            // Valida a URL quando informada (apenas http/https). Em branco é
            // permitido: significa "sem webhook configurado".
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->setFlash('error', 'Informe uma URL de webhook válida (http/https).');
                $this->redirect('/admin/configuracoes');
            }

            $model->setMany([
                'webhook_rsvp_url'     => $url,
                'webhook_rsvp_enabled' => $enabled,
            ]);

            $this->setFlash('success', 'Configurações do webhook salvas.');
        } catch (Throwable $e) {
            error_log('[Settings] saveWebhook: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar: ' . $e->getMessage());
        }

        $this->redirect('/admin/configuracoes');
    }

    /** Cifra e salva um segredo (exige a chave de aplicação). */
    private function saveSecret(Setting $model, string $key, string $plain): void
    {
        $model->set($key, Crypto::encrypt($plain));
    }

    // ---- Flash ----
    private function setFlash(string $type, string $message): void
    {
        Auth::start();
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type:string,message:string}|null */
    private function pullFlash(): ?array
    {
        Auth::start();
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $flash;
    }
}
