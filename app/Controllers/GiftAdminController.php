<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\ImageUploader;
use App\Models\Gift;
use App\Models\GiftPurchase;
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

    /** Lista as contribuições/compras recebidas (status de pagamento). */
    public function purchases(): void
    {
        $this->guard();

        $rows  = [];
        $error = null;
        try {
            $rows = (new GiftPurchase($this->config))->listWithGift();
        } catch (Throwable $e) {
            error_log('[GiftAdmin] purchases: ' . $e->getMessage());
            $error = 'Não foi possível carregar as contribuições.';
        }

        $this->viewAdmin('admin/gifts/purchases', [
            'pageTitle' => 'Contribuições',
            'active'    => 'purchases',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'error'     => $error,
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

        $model = new Gift($this->config);

        // Imagem atual (quando editando), para preservar/substituir/remover.
        $currentImage = null;
        if ($id > 0) {
            $existing = $model->find($id);
            $currentImage = $existing !== null ? ($existing['image'] ?? null) : null;
        }
        $image = $currentImage;

        // 1) Upload de arquivo (prioritário): padroniza e salva a nova imagem.
        $uploaded = $_FILES['image_file'] ?? null;
        if (is_array($uploaded) && (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = ImageUploader::handle($uploaded, 'gifts');
                // Remove a antiga só depois de a nova ter sido salva com sucesso.
                if ($currentImage) {
                    ImageUploader::remove((string) $currentImage);
                }
                $image = $newPath;
            } catch (Throwable $e) {
                error_log('[GiftAdmin] upload: ' . $e->getMessage());
                $this->setFlash('error', 'Imagem: ' . $e->getMessage());
                $this->redirect($id > 0 ? '/admin/presentes/editar?id=' . $id : '/admin/presentes/novo');
            }
        } elseif (($_POST['remove_image'] ?? '') === '1') {
            // 2) Remoção explícita da imagem (checkbox "remover imagem").
            if ($currentImage) {
                ImageUploader::remove((string) $currentImage);
            }
            $image = null;
        } else {
            // 3) Sem upload: permite informar/editar um nome de arquivo manual
            //    (compatibilidade com o campo de texto antigo).
            $manual = trim((string) ($_POST['image'] ?? ''));
            if ($manual !== '') {
                $image = $manual;
            }
        }

        $data = [
            'name'        => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'price_cents' => max(0, $priceCents),
            'image'       => $image ?: null,
            'status'      => $status,
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
        ];

        try {
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
            $model = new Gift($this->config);

            // Preserva o histórico de transações: não permite excluir um
            // presente que já tenha compras (exceto tentativas falhas).
            $relevant = (new GiftPurchase($this->config))->countRelevantByGift($id);
            if ($relevant > 0) {
                $this->setFlash(
                    'error',
                    'Este presente possui compras registradas e não pode ser excluído '
                    . '(para preservar o histórico financeiro). Marque-o como '
                    . '“Presenteado” se não quiser mais exibi-lo.'
                );
                $this->redirect('/admin/presentes');
            }

            // Remove a imagem do disco antes de apagar o registro.
            $gift = $model->find($id);
            $image = $gift !== null ? (string) ($gift['image'] ?? '') : '';

            $model->delete($id);

            if ($image !== '') {
                ImageUploader::remove($image);
            }
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
