<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\RsvpExport;
use App\Models\Rsvp;
use Throwable;

/**
 * Gerenciamento das confirmações de presença (área protegida).
 */
final class RsvpAdminController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Lista as confirmações, com filtro opcional por status. */
    public function index(): void
    {
        $this->guard();

        $allowed = ['confirmed', 'declined', 'pending'];
        $status  = $_GET['status'] ?? '';
        $status  = in_array($status, $allowed, true) ? $status : '';

        $rows    = [];
        $summary = [
            'total' => 0, 'confirmed' => 0, 'declined' => 0, 'pending' => 0,
            'people' => 0, 'companions' => 0, 'guests_total' => 0,
        ];
        $error   = null;

        try {
            $model   = new Rsvp($this->config);
            $rows    = $model->listWithGuests($status !== '' ? $status : null);
            $summary = $model->summary();
        } catch (Throwable $e) {
            error_log('[RsvpAdmin] ' . $e->getMessage());
            $error = 'Não foi possível carregar as confirmações.';
        }

        $this->viewAdmin('admin/rsvps/index', [
            'pageTitle' => 'Confirmações',
            'active'    => 'rsvps',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'summary'   => $summary,
            'status'    => $status,
            'error'     => $error,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    /** Exporta a lista (respeitando o filtro) em Excel .xlsx. */
    public function exportExcel(): void
    {
        $this->export('xlsx');
    }

    /** Exporta a lista (respeitando o filtro) em PDF. */
    public function exportPdf(): void
    {
        $this->export('pdf');
    }

    /**
     * Lógica comum de exportação: valida acesso, lê o filtro atual, busca as
     * linhas (já ordenadas alfabeticamente no model) e delega ao RsvpExport.
     */
    private function export(string $format): void
    {
        $this->guard();

        $allowed = ['confirmed', 'declined', 'pending'];
        $status  = $_GET['status'] ?? '';
        $status  = in_array($status, $allowed, true) ? $status : '';

        try {
            $rows = (new Rsvp($this->config))->listWithGuests($status !== '' ? $status : null);

            $bride = (string) ($this->config['wedding']['bride'] ?? '');
            $groom = (string) ($this->config['wedding']['groom'] ?? '');
            $couple = trim($bride . ($bride && $groom ? ' & ' : '') . $groom);

            if ($format === 'pdf') {
                RsvpExport::downloadPdf($rows, $status, $couple);
            } else {
                RsvpExport::downloadXlsx($rows, $status, $couple);
            }
        } catch (Throwable $e) {
            error_log('[RsvpAdmin] export ' . $format . ': ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível gerar o relatório: ' . $e->getMessage());
            $this->redirect('/admin/confirmacoes' . ($status !== '' ? '?status=' . $status : ''));
        }
    }

    /** Remove uma confirmação. */
    public function destroy(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/confirmacoes');
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            (new Rsvp($this->config))->delete($id);
            $this->setFlash('success', 'Confirmação removida.');
        } catch (Throwable $e) {
            error_log('[RsvpAdmin] delete: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível remover.');
        }

        $this->redirect('/admin/confirmacoes');
    }

    // ---- Flash messages simples (via sessão) ----

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
