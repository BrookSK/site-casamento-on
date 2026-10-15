<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\EmailTemplate;
use App\Core\GuestAuth;
use App\Core\Mailer;
use App\Core\Notifier;
use App\Core\Webhook;
use App\Models\CommunicationLog;
use App\Models\Rsvp;
use App\Models\Setting;
use Throwable;

/**
 * Confirmação de presença (RSVP) — lado público.
 *
 * Formulário aberto: o convidado informa nome, contato, se vai, nº de
 * acompanhantes e uma mensagem. Grava em guests + rsvps e, em seguida, cria
 * ou assume automaticamente a CONTA do convidado, deixando-o autenticado
 * (ponto 6). Convidados existentes são reutilizados — sem duplicar e-mail.
 */
final class RsvpController extends Controller
{
    public function store(): void
    {
        // Proteção CSRF (token exibido no formulário da home).
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->redirectHome('rsvp', 'erro-sessao');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->redirectHome('rsvp', 'erro-nome');
        }

        $attending = ($_POST['attending'] ?? '') === 'yes';

        // Acompanhantes: a quantidade é derivada dos nomes informados.
        // Cada campo "Acompanhante N" deve ter um nome (não pode ficar vazio).
        $companionNames = [];
        if ($attending) {
            $raw = $_POST['companion_names'] ?? [];
            if (is_array($raw)) {
                $hasEmpty = false;
                foreach ($raw as $cn) {
                    $cn = trim((string) $cn);
                    if ($cn === '') {
                        $hasEmpty = true;
                    } else {
                        $companionNames[] = $cn;
                    }
                }
                // Se o convidado adicionou um campo e deixou em branco, exige o nome.
                if ($hasEmpty) {
                    $this->redirectHome('rsvp', 'erro-acompanhantes');
                }
            }
            $companionNames = array_slice($companionNames, 0, 20);
        }
        $companions = count($companionNames);

        $data = [
            'name'  => mb_substr($name, 0, 150),
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
        ];

        $rsvp = [
            'status'     => $attending ? 'confirmed' : 'declined',
            'companions' => $attending ? $companions : 0,
            'message'    => trim((string) ($_POST['message'] ?? '')) ?: null,
        ];

        try {
            // Grava a confirmação e cria/assume a conta do convidado. O
            // convidado existente é reutilizado (não há mais erro de e-mail
            // duplicado — o registro anterior é aproveitado).
            $result = (new Rsvp($this->config))->createWithGuest($data, $rsvp, $companionNames);
        } catch (Throwable $e) {
            error_log('[Rsvp] store: ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        }

        // Login automático: mantém o convidado autenticado após confirmar,
        // liberando o acesso à lista de presentes e aos pagamentos (ponto 6).
        try {
            GuestAuth::login($result['guest']);
        } catch (Throwable $e) {
            // Falha ao autenticar não impede a confirmação já gravada.
            error_log('[Rsvp] autologin: ' . $e->getMessage());
        }

        // Confirmação já gravada: notifica o webhook (se configurado). Falhas
        // aqui NÃO afetam o convidado — apenas são registradas no log.
        $this->notifyWebhook($data, $rsvp['status']);

        // Comunicação automática de cadastro/acesso (ponto 7). Quando a conta
        // é nova / ainda sem senha, há um invite_token: enviamos as boas-vindas
        // com o endereço do site, o e-mail cadastrado e o link seguro de
        // definição de senha (e-mail e, se configurado, WhatsApp). Tolerante a
        // falhas: cada tentativa é registrada em communication_logs.
        $this->sendWelcome($result);

        // Envia o e-mail de confirmação ao convidado (se houver e-mail e o SMTP
        // estiver configurado). Também é tolerante a falhas: só registra no log.
        $this->sendConfirmationEmail($result['guest'], $data, $rsvp['status']);

        $this->redirectHome('rsvp', 'ok');
    }

    /**
     * Dispara as boas-vindas (ponto 7) quando a conta foi criada/assumida sem
     * senha — nesse caso há um invite_token e montamos o link seguro de
     * definição de senha. Para contas já ativas (com senha) não há token e,
     * portanto, não reenviamos o link.
     *
     * @param array{
     *   rsvp_id:int,
     *   guest:array{id:int,name:string,email:string},
     *   invite_token:?string,
     *   has_password:bool
     * } $result
     */
    private function sendWelcome(array $result): void
    {
        try {
            $guest = $result['guest'];
            $token = $result['invite_token'] ?? null;

            // Só há boas-vindas com link quando a conta ainda não tem senha.
            if (!empty($result['has_password']) || $token === null || $token === '') {
                return;
            }

            // Recupera o telefone (o retorno do RSVP não o traz) para o canal
            // WhatsApp. Falha de leitura não impede o e-mail.
            $phone = null;
            try {
                $full  = (new \App\Models\Guest($this->config))->find((int) $guest['id']);
                $phone = $full['phone'] ?? null;
            } catch (Throwable $e) {
                error_log('[Rsvp] sendWelcome phone: ' . $e->getMessage());
            }

            (new Notifier($this->config))->sendWelcome([
                'id'    => (int) $guest['id'],
                'name'  => (string) $guest['name'],
                'email' => $guest['email'] ?? null,
                'phone' => $phone,
            ], $this->passwordLink((string) $token));
        } catch (Throwable $e) {
            error_log('[Rsvp] sendWelcome: ' . $e->getMessage());
        }
    }

    /** Monta a URL absoluta do link seguro de definição de senha. */
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

    /**
     * Envia o e-mail de confirmação de presença ao convidado, usando o
     * template HTML, e registra o resultado em communication_logs (ponto 7).
     *
     * @param array{id:int,name:string,email:string} $guest
     * @param array{name:string, email:?string, phone:?string} $data
     * @param string $status 'confirmed' ou 'declined'
     */
    private function sendConfirmationEmail(array $guest, array $data, string $status): void
    {
        $log   = new CommunicationLog($this->config);
        $email = trim((string) ($data['email'] ?? ''));
        $gid   = (int) ($guest['id'] ?? 0) ?: null;

        if ($email === '' || !Mailer::isConfigured($this->config)) {
            $log->record([
                'guest_id'  => $gid,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => 'rsvp',
                'recipient' => $email ?: null,
                'status'    => CommunicationLog::STATUS_SKIPPED,
                'error'     => $email === '' ? 'Convidado sem e-mail.' : 'SMTP não configurado.',
            ]);
            return;
        }

        try {
            $site    = (string) ($this->config['app']['name'] ?? 'Nosso casamento');
            $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
            $subject = $status === 'confirmed' ? 'Presença confirmada' : 'Resposta recebida';

            $html = EmailTemplate::rsvp([
                'name'      => (string) $data['name'],
                'siteName'  => $site,
                'message'   => $this->buildMessage((string) $data['name'], $status),
                'siteUrl'   => $baseUrl !== '' ? $baseUrl : null,
                'signature' => null,
            ]);

            Mailer::fromSettings($this->config)->send($email, $subject, $html, (string) $data['name']);

            $log->record([
                'guest_id'  => $gid,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => 'rsvp',
                'recipient' => $email,
                'status'    => CommunicationLog::STATUS_SENT,
            ]);
        } catch (Throwable $e) {
            error_log('[Rsvp] sendConfirmationEmail: ' . $e->getMessage());
            $log->record([
                'guest_id'  => $gid,
                'channel'   => CommunicationLog::CHANNEL_EMAIL,
                'type'      => 'rsvp',
                'recipient' => $email,
                'status'    => CommunicationLog::STATUS_FAILED,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispara o webhook de confirmação, se estiver habilitado e com URL válida.
     *
     * @param array{name:string, email:?string, phone:?string} $guest
     * @param string $status 'confirmed' ou 'declined'
     */
    private function notifyWebhook(array $guest, string $status): void
    {
        try {
            $settings = new Setting($this->config);
            $pairs    = $settings->getMany(['webhook_rsvp_url', 'webhook_rsvp_enabled']);

            $url     = trim((string) ($pairs['webhook_rsvp_url'] ?? ''));
            $enabled = (string) ($pairs['webhook_rsvp_enabled'] ?? '') !== '';
            if (!$enabled || $url === '') {
                return;
            }

            Webhook::send($url, [
                'name'    => $guest['name'],
                'email'   => $guest['email'],
                'phone'   => $guest['phone'],
                'status'  => $status,
                'message' => $this->buildMessage($guest['name'], $status),
            ]);
        } catch (Throwable $e) {
            error_log('[Rsvp] notifyWebhook: ' . $e->getMessage());
        }
    }

    /** Monta a mensagem pré-pronta conforme o status da confirmação. */
    private function buildMessage(string $name, string $status): string
    {
        $first = trim(explode(' ', trim($name))[0] ?: 'convidado(a)');

        if ($status === 'confirmed') {
            return 'Olá, ' . $first . '! ♡ Recebemos sua confirmação de presença e '
                . 'ficamos muito felizes em saber que você estará com a gente nesse '
                . 'dia tão especial! Mal podemos esperar para celebrar esse momento '
                . 'ao seu lado.';
        }

        return 'Olá, ' . $first . '! ♡ Recebemos sua resposta e agradecemos muito '
            . 'por nos avisar. Sentiremos sua falta nesse dia tão especial, mas '
            . 'esperamos que possamos compartilhar outros momentos felizes juntos!';
    }

    /**
     * Atualiza APENAS a lista de acompanhantes da confirmação do convidado
     * logado. O status (vai/não vai) não é alterado por aqui. Exige login e
     * valida que o RSVP pertence ao convidado (dentro do Model).
     */
    public function updateCompanions(): void
    {
        if (!GuestAuth::check()) {
            $this->redirectHome('rsvp', 'erro-sessao');
        }

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->redirectHome('rsvp', 'erro-sessao');
        }

        $guest  = GuestAuth::user();
        $rsvpId = (int) ($_POST['rsvp_id'] ?? 0);

        // Coleta e valida os nomes (não permite campo em branco).
        $names = [];
        $raw = $_POST['companion_names'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $cn) {
                $cn = trim((string) $cn);
                if ($cn === '') {
                    $this->redirectHome('rsvp', 'erro-acompanhantes');
                }
                $names[] = $cn;
            }
        }

        try {
            $ok = (new Rsvp($this->config))->replaceCompanions($rsvpId, (int) $guest['id'], $names);
            if (!$ok) {
                $this->redirectHome('rsvp', 'erro');
            }
        } catch (Throwable $e) {
            error_log('[Rsvp] updateCompanions: ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        }

        $this->redirectHome('rsvp', 'acompanhantes-ok');
    }

    /** Redireciona para a home, numa âncora, com um status de retorno. */
    private function redirectHome(string $anchor, string $status): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/?rsvp=' . urlencode($status) . '#' . $anchor);
        exit;
    }
}
