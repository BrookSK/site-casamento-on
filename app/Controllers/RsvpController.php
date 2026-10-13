<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Core\Webhook;
use App\Models\Rsvp;
use App\Models\Setting;
use PDOException;
use Throwable;

/**
 * Confirmação de presença (RSVP) — lado público.
 *
 * Formulário aberto: o convidado informa nome, contato, se vai, nº de
 * acompanhantes e uma mensagem. Grava em guests + rsvps. Sem login
 * (login de convidado fica para etapa futura).
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
            (new Rsvp($this->config))->createWithGuest($data, $rsvp, $companionNames);
        } catch (PDOException $e) {
            // 23000 / 1062 = violação de chave única (e-mail já cadastrado).
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                $this->redirectHome('rsvp', 'erro-email');
            }
            error_log('[Rsvp] store (pdo): ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        } catch (Throwable $e) {
            error_log('[Rsvp] store: ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        }

        // Confirmação já gravada: notifica o webhook (se configurado). Falhas
        // aqui NÃO afetam o convidado — apenas são registradas no log.
        $this->notifyWebhook($data, $rsvp['status']);

        // Envia o e-mail de confirmação ao convidado (se houver e-mail e o SMTP
        // estiver configurado). Também é tolerante a falhas: só registra no log.
        $this->sendConfirmationEmail($data, $rsvp['status']);

        $this->redirectHome('rsvp', 'ok');
    }

    /**
     * Envia o e-mail de confirmação de presença ao convidado.
     *
     * @param array{name:string, email:?string, phone:?string} $guest
     * @param string $status 'confirmed' ou 'declined'
     */
    private function sendConfirmationEmail(array $guest, string $status): void
    {
        try {
            $email = trim((string) ($guest['email'] ?? ''));
            if ($email === '' || !Mailer::isConfigured($this->config)) {
                return;
            }

            $mailer  = Mailer::fromSettings($this->config);
            $subject = $status === 'confirmed'
                ? 'Presença confirmada'
                : 'Resposta recebida';

            $mailer->send($email, $subject, $this->buildEmailBody($guest['name'], $status), $guest['name']);
        } catch (Throwable $e) {
            error_log('[Rsvp] sendConfirmationEmail: ' . $e->getMessage());
        }
    }

    /** Monta o corpo HTML do e-mail a partir da mensagem pré-pronta. */
    private function buildEmailBody(string $name, string $status): string
    {
        $message = $this->buildMessage($name, $status);
        return '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
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
        if ($status === 'confirmed') {
            return 'Olá ' . $name . ', recebemos a sua confirmação de presença. '
                . 'Ficamos muito felizes em ter você conosco!';
        }

        return 'Olá ' . $name . ', recebemos a sua resposta. '
            . 'Sentiremos a sua falta, mas agradecemos por avisar!';
    }

    /** Redireciona para a home, numa âncora, com um status de retorno. */
    private function redirectHome(string $anchor, string $status): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/?rsvp=' . urlencode($status) . '#' . $anchor);
        exit;
    }
}
