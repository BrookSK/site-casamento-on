<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Gift;
use Throwable;

/**
 * Gerenciamento da lista de presentes (CRUD, área protegida).
 */
final class GiftAdminController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Lista todos os presentes. */
    public function index(): void
    {
        $this->guard();

        $rows  = [];
        $error = null;
        try {
            $rows = (new Gift($this->config))->listAll();
        } catch (Throwable $e) {
            error_log('[GiftAdmin] ' . $e->getMessage());
            $error = 'Não foi possível carregar os presentes.';
        }

        $this->viewAdmin('admin/gifts/index', [
            'pageTitle' => 'Presentes',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'error'     => $error,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    /** Formulário de novo presente. */
    public function create(): void
    {
        $this->guard();
        $this->viewAdmin('admin/gifts/form', [
            'pageTitle' => 'Novo presente',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'gift'      => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Formulário de edição. */
    public function edit(): void
    {
        $this->guard();
        $id = (int) ($_GET['id'] ?? 0);

        $gift = null;
        try {
            $gift = (new Gift($this->config))->find($id);
        } catch (Throwable $e) {
            error_log('[GiftAdmin] edit: ' . $e->getMessage());
        }

        if ($gift === null) {
            $this->setFlash('error', 'Presente não encontrado.');
            $this->redirect('/admin/presentes');
        }

        $this->viewAdmin('admin/gifts/form', [
            'pageTitle' => 'Editar presente',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'gift'      => $gift,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Cria ou atualiza (um único endpoint de salvar). */
    public function save(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/presentes');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            $this->setFlash('error', 'O nome do presente é obrigatório.');
            $this->redirect($id > 0 ? '/admin/presentes/editar?id=' . $id : '/admin/presentes/novo');
        }

        // Preço: aceita "150,00" ou "150.00" e converte para centavos.
        $priceRaw   = str_replace(['.', ' '], '', (string) ($_POST['price'] ?? '0'));
        $priceRaw   = str_replace(',', '.', $priceRaw);
        $priceCents = (int) round(((float) $priceRaw) * 100);

        $allowedStatus = ['available', 'reserved', 'purchased'];
        $status = in_array($_POST['status'] ?? '', $allowedStatus, true)
            ? (string) $_POST['status'] : 'available';

        $data = [
            'name'        => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'price_cents' => max(0, $priceCents),
            'image'       => trim((string) ($_POST['image'] ?? '')) ?: null,
            'status'      => $status,
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
        ];

        try {
            $model = new Gift($this->config);
            if ($id > 0) {
                $model->updateGift($id, $data);
                $this->setFlash('success', 'Presente atualizado.');
            } else {
                $model->createGift($data);
                $this->setFlash('success', 'Presente criado.');
            }
        } catch (Throwable $e) {
            error_log('[GiftAdmin] save: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar o presente.');
        }

        $this->redirect('/admin/presentes');
    }

    /** Remove um presente. */
    public function destroy(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/presentes');
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            (new Gift($this->config))->delete($id);
            $this->setFlash('success', 'Presente removido.');
        } catch (Throwable $e) {
            error_log('[GiftAdmin] destroy: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível remover.');
        }

        $this->redirect('/admin/presentes');
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
