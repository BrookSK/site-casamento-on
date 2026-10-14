<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\FinanceExport;
use App\Models\Gift;
use App\Models\GiftPurchase;
use Throwable;

/**
 * Painel de gestão financeira dos presentes (ponto 11).
 *
 * Centraliza as transações de presente: dashboard com totais, tabela filtrável
 * (por período, convidado, presente e status), detalhe por transação e
 * exportação em Excel/PDF. Os dados exibidos vêm de gift_purchases; a captura
 * automática de taxas/datas pelo gateway é tratada em etapa posterior.
 */
final class FinanceController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Dashboard + tabela de transações (com filtros). */
    public function index(): void
    {
        $this->guard();

        $filters = $this->readFilters();

        $rows    = [];
        $summary = [];
        $gifts   = [];
        $error   = null;

        try {
            $model   = new GiftPurchase($this->config);
            $rows    = $model->listFinance($filters);
            $summary = $model->financeSummary($filters);
            $gifts   = (new Gift($this->config))->listAll(false);
        } catch (Throwable $e) {
            error_log('[Finance] index: ' . $e->getMessage());
            $error = 'Não foi possível carregar os dados financeiros.';
        }

        $this->viewAdmin('admin/finance/index', [
            'pageTitle' => 'Financeiro',
            'active'    => 'finance',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'summary'   => $summary,
            'gifts'     => $gifts,
            'filters'   => $filters,
            'error'     => $error,
            'flash'     => $this->pullFlash(),
        ]);
    }

    /** Detalhe de uma transação (valores bruto/taxas/líquido + histórico). */
    public function show(): void
    {
        $this->guard();

        $id = (int) ($_GET['id'] ?? 0);

        $purchase = null;
        $history  = [];
        $error    = null;

        try {
            $model    = new GiftPurchase($this->config);
            $purchase = $model->findFinance($id);
            if ($purchase !== null) {
                $history = $model->statusHistory($id);
            }
        } catch (Throwable $e) {
            error_log('[Finance] show: ' . $e->getMessage());
            $error = 'Não foi possível carregar a transação.';
        }

        if ($purchase === null && $error === null) {
            $this->setFlash('error', 'Transação não encontrada.');
            $this->redirect('/admin/financeiro');
        }

        $this->viewAdmin('admin/finance/show', [
            'pageTitle' => 'Transação',
            'active'    => 'finance',
            'admin'     => Auth::user(),
            'purchase'  => $purchase,
            'history'   => $history,
            'error'     => $error,
        ]);
    }

    /** Exporta o relatório financeiro (com filtros) em Excel .xlsx. */
    public function exportExcel(): void
    {
        $this->export('xlsx');
    }

    /** Exporta o relatório financeiro (com filtros) em PDF. */
    public function exportPdf(): void
    {
        $this->export('pdf');
    }

    private function export(string $format): void
    {
        $this->guard();

        $filters = $this->readFilters();

        try {
            $model   = new GiftPurchase($this->config);
            $rows    = $model->listFinance($filters);
            $summary = $model->financeSummary($filters);

            $bride = (string) ($this->config['wedding']['bride'] ?? '');
            $groom = (string) ($this->config['wedding']['groom'] ?? '');
            $couple = trim($bride . ($bride && $groom ? ' & ' : '') . $groom);

            if ($format === 'pdf') {
                FinanceExport::downloadPdf($rows, $summary, $couple);
            } else {
                FinanceExport::downloadXlsx($rows, $summary, $couple);
            }
        } catch (Throwable $e) {
            error_log('[Finance] export ' . $format . ': ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível gerar o relatório: ' . $e->getMessage());
            $this->redirect('/admin/financeiro');
        }
    }

    /**
     * Lê e normaliza os filtros da query string.
     *
     * @return array{status:string, gift_id:int, guest:string, date_from:string, date_to:string}
     */
    private function readFilters(): array
    {
        $status = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $status = '';
        }

        $dateFrom = (string) ($_GET['date_from'] ?? '');
        $dateTo   = (string) ($_GET['date_to'] ?? '');
        $validDate = static fn (string $d): string =>
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 ? $d : '';

        return [
            'status'    => $status,
            'gift_id'   => (int) ($_GET['gift_id'] ?? 0),
            'guest'     => trim((string) ($_GET['guest'] ?? '')),
            'date_from' => $validDate($dateFrom),
            'date_to'   => $validDate($dateTo),
        ];
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
