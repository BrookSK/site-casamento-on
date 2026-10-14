<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\CommunicationLog;
use App\Models\Setting;
use Throwable;

/**
 * Orquestra a COMUNICAÇÃO AUTOMÁTICA de cadastro e acesso (ponto 7).
 *
 * Responsável por:
 *   - montar as mensagens (e-mail HTML via EmailTemplate; texto do WhatsApp);
 *   - informar o endereço do site e o e-mail cadastrado;
 *   - incluir o link seguro de definição/recuperação de senha;
 *   - disparar pelos canais disponíveis (e-mail e, se configurado, WhatsApp);
 *   - registrar CADA tentativa (sucesso/falha/ignorado) em communication_logs.
 *
 * Tudo é tolerante a falhas: nenhum erro de comunicação interrompe o fluxo do
 * convidado (a confirmação já foi gravada). Os problemas vão para o log de
 * aplicação (error_log) e para a tabela communication_logs.
 */
final class Notifier
{
    private array $config;
    private Setting $settings;
    private CommunicationLog $log;

    public function __construct(array $config)
    {
        $this->config   = $config;
        $this->settings = new Setting($config);
        $this->log      = new CommunicationLog($config);
    }

    /**
     * Envia as boas-vindas após a criação/assunção da conta.
     *
     * @param array{id:int,name:string,email:?string,phone:?string} $guest
     * @param string|null $passwordLink Link seguro de definição de senha.
     */
    public function sendWelcome(array $guest, ?string $passwordLink): void
    {
        $site    = $this->siteName();
        $siteUrl = $this->siteUrl();
        $sig     = $this->signature();
        $email   = trim((string) ($guest['email'] ?? ''));
        $phone   = trim((string) ($guest['phone'] ?? ''));
        $guestId = (int) $guest['id'];

        // ----- E-mail -----
        $html = EmailTemplate::welcome([
            'name'         => (string) $guest['name'],
            'siteName'     => $site,
            'siteUrl'      => $siteUrl,
            'accountEmail' => $email,
            'passwordLink' => $passwordLink,
            'signature'    => $sig,
        ]);
        $this->emailOrSkip($guestId, 'welcome', $email, (string) $guest['name'], 'Boas-vindas — ' . $site, $html);

        // ----- WhatsApp -----
        $waText = $this->welcomeWhatsAppText((string) $guest['name'], $site, $siteUrl, $email, $passwordLink);
        $this->whatsappOrSkip($guestId, 'welcome', $phone, $waText);
    }

    /**
     * Envia o e-mail de recuperação de senha.
     *
     * @param array{id:int,name:string,email:?string,phone:?string} $guest
     */
    public function sendPasswordReset(array $guest, string $resetLink): void
    {
        $site    = $this->siteName();
        $email   = trim((string) ($guest['email'] ?? ''));
        $guestId = (int) $guest['id'];

        $html = EmailTemplate::passwordReset([
            'name'      => (string) $guest['name'],
            'siteName'  => $site,
            'siteUrl'   => $this->siteUrl(),
            'resetLink' => $resetLink,
            'signature' => $this->signature(),
        ]);

        $this->emailOrSkip($guestId, 'password_reset', $email, (string) $guest['name'], 'Redefinição de senha — ' . $site, $html);

        // WhatsApp (se configurado): envia o link de redefinição.
        $phone  = trim((string) ($guest['phone'] ?? ''));
        $waText = 'Olá! Recebemos um pedido para redefinir a sua senha em ' . $site . '. '
            . 'Use este link seguro: ' . $resetLink;
        $this->whatsappOrSkip($guestId, 'password_reset', $phone, $waText);
    }

    // ----------------------------------------------------------------
    //  Envio por canal + registro no log
    // ----------------------------------------------------------------

    /** Tenta enviar o e-mail; registra sent/failed/skipped. */
    private function emailOrSkip(int $guestId, string $type, string $email, string $toName, string $subject, string $html): void
    {
        if ($email === '' || !Mailer::isConfigured($this->config)) {
            $this->log->record([
                'guest_id'  => $guestId ?: null,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => $type,
                'recipient' => $email ?: null,
                'status'    => CommunicationLog::STATUS_SKIPPED,
                'error'     => $email === '' ? 'Convidado sem e-mail.' : 'SMTP não configurado.',
            ]);
            return;
        }

        try {
            Mailer::fromSettings($this->config)->send($email, $subject, $html, $toName);
            $this->log->record([
                'guest_id'  => $guestId ?: null,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => $type,
                'recipient' => $email,
                'status'    => CommunicationLog::STATUS_SENT,
            ]);
        } catch (Throwable $e) {
            error_log('[Notifier] email ' . $type . ': ' . $e->getMessage());
            $this->log->record([
                'guest_id'  => $guestId ?: null,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => $type,
                'recipient' => $email,
                'status'    => CommunicationLog::STATUS_FAILED,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /** Tenta enviar o WhatsApp; registra sent/failed/skipped. */
    private function whatsappOrSkip(int $guestId, string $type, string $phone, string $message): void
    {
        if ($phone === '') {
            $this->log->record([
                'guest_id' => $guestId ?: null,
                'channel'  => CommunicationLog::CHANNEL_WHATSAPP,
                'type'     => $type,
                'status'   => CommunicationLog::STATUS_SKIPPED,
                'error'    => 'Convidado sem telefone.',
            ]);
            return;
        }

        try {
            $wa = WhatsApp::fromSettings($this->config);
            if (!$wa->isConfigured()) {
                $this->log->record([
                    'guest_id'  => $guestId ?: null,
                    'channel'   => CommunicationLog::CHANNEL_WHATSAPP,
                    'type'      => $type,
                    'recipient' => $wa->normalize($phone) ?: $phone,
                    'status'    => CommunicationLog::STATUS_SKIPPED,
                    'error'     => 'API de WhatsApp não configurada.',
                ]);
                return;
            }

            $result = $wa->send($phone, $message);
            $this->log->record([
                'guest_id'  => $guestId ?: null,
                'channel'   => CommunicationLog::CHANNEL_WHATSAPP,
                'type'      => $type,
                'recipient' => $wa->normalize($phone),
                'status'    => $result['ok'] ? CommunicationLog::STATUS_SENT : CommunicationLog::STATUS_FAILED,
                'error'     => $result['ok'] ? null : $result['message'],
            ]);
        } catch (Throwable $e) {
            error_log('[Notifier] whatsapp ' . $type . ': ' . $e->getMessage());
            $this->log->record([
                'guest_id'  => $guestId ?: null,
                'channel'   => CommunicationLog::CHANNEL_WHATSAPP,
                'type'      => $type,
                'recipient' => $phone,
                'status'    => CommunicationLog::STATUS_FAILED,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    //  Textos e dados auxiliares
    // ----------------------------------------------------------------

    /** Texto da mensagem de boas-vindas para o WhatsApp (texto puro). */
    private function welcomeWhatsAppText(string $name, string $site, string $siteUrl, string $email, ?string $passwordLink): string
    {
        $first = trim(explode(' ', trim($name))[0] ?: 'convidado(a)');
        $lines = ['Olá, ' . $first . '! Que alegria ter você com a gente no ' . $site . '. '];
        if ($siteUrl !== '') {
            $lines[] = 'Acesse o site: ' . $siteUrl;
        }
        if ($email !== '') {
            $lines[] = 'Seu e-mail de acesso: ' . $email;
        }
        if ($passwordLink !== null && $passwordLink !== '') {
            $lines[] = 'Defina sua senha de forma segura: ' . $passwordLink;
        }
        return implode("\n", $lines);
    }

    private function siteName(): string
    {
        return (string) ($this->config['app']['name'] ?? 'Nosso casamento');
    }

    private function siteUrl(): string
    {
        $url = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        if ($url !== '') {
            return $url;
        }
        // Deriva do host da requisição em dev local.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return $host !== '' ? $scheme . '://' . $host : '';
    }

    /** Assinatura do rodapé (ex.: nomes do casal), configurável. */
    private function signature(): ?string
    {
        try {
            $sig = trim((string) ($this->settings->get('comm_signature') ?? ''));
            if ($sig !== '') {
                return $sig;
            }
        } catch (Throwable $e) {
            // ignora: usa fallback
        }
        $bride = trim((string) ($this->config['wedding']['bride'] ?? ''));
        $groom = trim((string) ($this->config['wedding']['groom'] ?? ''));
        if ($bride !== '' && $groom !== '') {
            return $bride . ' & ' . $groom;
        }
        return null;
    }
}
