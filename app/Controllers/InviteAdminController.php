<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\QrImage;
use App\Models\Guest;
use Throwable;

/**
 * Convites INDIVIDUAIS — criação manual (área protegida).
 *
 * O admin informa o nome do convidado; o sistema gera um token único (código
 * do QR), cria o convidado com invite_type = 'individual' e exibe o link
 * exclusivo + o QR Code para copiar/compartilhar.
 *
 * A lista administrativa completa de convidados (os dois tipos) virá numa
 * etapa posterior; aqui há apenas uma listagem enxuta dos convites individuais
 * já criados, para o admin recuperar o link/QR de cada um.
 */
final class InviteAdminController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Formulário de criação + lista dos convites individuais já gerados. */
    public function create(): void
    {
        $this->guard();

        $rows  = [];
        $error = null;
        try {
            $rows = (new Guest($this->config))->listIndividualInvites();
        } catch (Throwable $e) {
            error_log('[InviteAdmin] create list: ' . $e->getMessage());
            $error = 'Não foi possível carregar os convites individuais.';
        }

        $this->renderForm($rows, $error);
    }

    /** Processa a criação manual do convite individual. */
    public function store(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->renderForm([], 'Sessão expirada. Tente novamente.');
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->renderForm(
                $this->safeList(),
                'Informe o nome do convidado para gerar o convite.'
            );
            return;
        }

        try {
            $invite = (new Guest($this->config))->createIndividualInvite($name);
        } catch (Throwable $e) {
            error_log('[InviteAdmin] store: ' . $e->getMessage());
            $this->renderForm($this->safeList(), 'Não foi possível gerar o convite agora.');
            return;
        }

        // Guarda o token recém-criado para destacá-lo na tela após o redirect.
        Auth::start();
        $_SESSION['_invite_created'] = $invite['token'];

        $this->redirect('/admin/convites/novo');
    }

    /** Lista tolerante a falhas (para re-renderizar o formulário com erro). */
    private function safeList(): array
    {
        try {
            return (new Guest($this->config))->listIndividualInvites();
        } catch (Throwable $e) {
            error_log('[InviteAdmin] safeList: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Renderiza a tela, preparando para cada convite o link exclusivo e a URL
     * do QR Code (gerado sob demanda, tolerante a falhas).
     *
     * @param array<int, array<string,mixed>> $rows
     */
    private function renderForm(array $rows, ?string $error): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');

        // Token recém-criado (para destacar no topo da lista).
        Auth::start();
        $createdToken = $_SESSION['_invite_created'] ?? null;
        unset($_SESSION['_invite_created']);

        // Enriquece cada linha com link e QR.
        $invites = [];
        foreach ($rows as $row) {
            $token = (string) ($row['invite_token'] ?? '');
            if ($token === '') {
                continue;
            }
            $link   = $baseUrl . '/convite?token=' . $token;
            $qrPath = QrImage::generatePng($token, $link); // caminho relativo a public/ ou null
            $qrUrl  = $qrPath !== null ? ($baseUrl . '/' . $qrPath) : null;

            $invites[] = [
                'id'        => (int) ($row['id'] ?? 0),
                'name'      => (string) ($row['name'] ?? ''),
                'token'     => $token,
                'link'      => $link,
                'qr_url'    => $qrUrl,
                'confirmed' => ($row['rsvp_status'] ?? null) === 'confirmed',
                'declined'  => ($row['rsvp_status'] ?? null) === 'declined',
                'is_new'    => $token === $createdToken,
            ];
        }

        $this->viewAdmin('admin/invites/index', [
            'pageTitle'   => 'Convites individuais',
            'active'      => 'rsvps',
            'admin'       => Auth::user(),
            'invites'     => $invites,
            'error'       => $error,
            'qrAvailable' => QrImage::isAvailable(),
            'csrfField'   => Csrf::field(),
        ]);
    }
}
