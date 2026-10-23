<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Models\Guest;
use App\Models\Rsvp;
use Throwable;

/**
 * Convite INDIVIDUAL (link + QR exclusivos) — lado público.
 *
 * Acesso por /convite?token=<32 hex>. O token identifica o convidado do
 * convite individual (invite_type = 'individual'). Exibe o MESMO formulário de
 * confirmação de presença do link público, porém SEM o campo de CPF: a
 * identificação já é feita pelo token, que viaja num campo oculto e amarra a
 * confirmação ao convidado correto.
 */
final class IndividualInviteController extends Controller
{
    /** Caminho base do convite individual (o token vai na query string). */
    public const PATH = '/convite';

    public function show(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));

        // Token ausente ou fora do formato: convite inválido.
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            $this->renderInvalid();
            return;
        }

        try {
            $guest = (new Guest($this->config))->findByIndividualToken($token);
        } catch (Throwable $e) {
            error_log('[Invite] show lookup: ' . $e->getMessage());
            $this->renderInvalid();
            return;
        }

        if ($guest === null) {
            $this->renderInvalid();
            return;
        }

        // Confirmação já registrada deste convite (se houver), para mostrar o
        // resumo com edição de acompanhantes em vez do formulário.
        $myRsvp = null;
        try {
            $myRsvp = (new Rsvp($this->config))->findLatestForGuest((int) $guest['id']);
        } catch (Throwable $e) {
            error_log('[Invite] show findLatestForGuest: ' . $e->getMessage());
        }

        // Mantém o convidado do convite individual autenticado (igual ao fluxo
        // público após confirmar), liberando presentes/conta.
        try {
            GuestAuth::login([
                'id'    => (int) $guest['id'],
                'name'  => (string) $guest['name'],
                'email' => (string) ($guest['email'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('[Invite] autologin: ' . $e->getMessage());
        }

        $content = new Content($this->config);
        $bride   = $content->text('bride', (string) ($this->config['wedding']['bride'] ?? 'Noiva'));
        $groom   = $content->text('groom', (string) ($this->config['wedding']['groom'] ?? 'Noivo'));

        $this->view('convite-individual', [
            'pageTitle'    => 'Confirmação de presença · ' . $bride . ' & ' . $groom,
            'bodyClass'    => 'page-light',
            'content'      => $content,
            'csrfField'    => Csrf::field(),
            'rsvpStatus'   => isset($_GET['rsvp']) ? (string) $_GET['rsvp'] : null,
            'currentGuest' => ['id' => (int) $guest['id'], 'name' => (string) $guest['name']],
            'myRsvp'       => $myRsvp,
            // Token do convite individual: faz o partial esconder o CPF e
            // enviar o token oculto no formulário.
            'inviteToken'  => $token,
            // Volta para esta mesma página (com o token) após enviar.
            'rsvpReturnTo' => self::PATH . '?token=' . $token,
        ]);
    }

    /** Renderiza uma página amigável quando o convite não existe/é inválido. */
    private function renderInvalid(): void
    {
        http_response_code(404);
        $content = new Content($this->config);
        $this->view('convite-invalido', [
            'pageTitle' => 'Convite não encontrado',
            'bodyClass' => 'page-light',
            'content'   => $content,
        ]);
    }
}
