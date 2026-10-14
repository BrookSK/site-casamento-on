<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\ImageUploader;
use App\Models\Album;
use App\Models\AlbumPhoto;
use Throwable;

/**
 * Gerenciamento da Galeria de Fotos (álbuns + fotos) na área administrativa.
 *
 * Fica dentro de "Conteúdo". Permite criar álbuns (título, subtítulo e
 * descrição), reordenar/excluir, e fazer upload de fotos por álbum. As fotos
 * são padronizadas pelo ImageUploader (mesma infra dos presentes).
 */
final class AlbumAdminController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Lista de álbuns (com contagem de fotos). */
    public function index(): void
    {
        $this->guard();

        $rows  = [];
        $error = null;
        try {
            $rows = (new Album($this->config))->listWithCounts();
        } catch (Throwable $e) {
            error_log('[AlbumAdmin] index: ' . $e->getMessage());
            $error = 'Não foi possível carregar os álbuns.';
        }

        $this->viewAdmin('admin/albums/index', [
            'pageTitle' => 'Galeria de fotos',
            'active'    => 'content',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'error'     => $error,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    /** Formulário de novo álbum. */
    public function create(): void
    {
        $this->guard();
        $this->viewAdmin('admin/albums/form', [
            'pageTitle' => 'Novo álbum',
            'active'    => 'content',
            'admin'     => Auth::user(),
            'album'     => null,
            'photos'    => [],
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Formulário de edição (dados do álbum + gestão das fotos). */
    public function edit(): void
    {
        $this->guard();
        $id = (int) ($_GET['id'] ?? 0);

        $album  = null;
        $photos = [];
        try {
            $album = (new Album($this->config))->find($id);
            if ($album !== null) {
                $photos = (new AlbumPhoto($this->config))->listByAlbum($id);
            }
        } catch (Throwable $e) {
            error_log('[AlbumAdmin] edit: ' . $e->getMessage());
        }

        if ($album === null) {
            $this->setFlash('error', 'Álbum não encontrado.');
            $this->redirect('/admin/galeria');
        }

        $this->viewAdmin('admin/albums/form', [
            'pageTitle' => 'Editar álbum',
            'active'    => 'content',
            'admin'     => Auth::user(),
            'album'     => $album,
            'photos'    => $photos,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Cria ou atualiza um álbum. */
    public function save(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/galeria');
        }

        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));

        if ($title === '') {
            $this->setFlash('error', 'O título do álbum é obrigatório.');
            $this->redirect($id > 0 ? '/admin/galeria/editar?id=' . $id : '/admin/galeria/novo');
        }

        $data = [
            'title'       => $title,
            'subtitle'    => trim((string) ($_POST['subtitle'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
        ];

        try {
            $model = new Album($this->config);
            if ($id > 0) {
                $model->updateAlbum($id, $data);
                $this->setFlash('success', 'Álbum atualizado.');
                $this->redirect('/admin/galeria/editar?id=' . $id);
            } else {
                $newId = $model->createAlbum($data);
                $this->setFlash('success', 'Álbum criado. Agora adicione as fotos.');
                $this->redirect('/admin/galeria/editar?id=' . $newId);
            }
        } catch (Throwable $e) {
            error_log('[AlbumAdmin] save: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar o álbum.');
            $this->redirect('/admin/galeria');
        }
    }

    /** Exclui um álbum (as fotos vão junto, via cascata). */
    public function destroy(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/galeria');
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            $photos = new AlbumPhoto($this->config);
            // Remove os arquivos do disco antes de apagar os registros.
            foreach ($photos->listByAlbum($id) as $p) {
                ImageUploader::remove((string) $p['image']);
            }
            (new Album($this->config))->delete($id);
            $this->setFlash('success', 'Álbum removido.');
        } catch (Throwable $e) {
            error_log('[AlbumAdmin] destroy: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível remover o álbum.');
        }

        $this->redirect('/admin/galeria');
    }

    /** Upload de uma ou mais fotos para um álbum. */
    public function uploadPhotos(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/galeria');
        }

        $albumId = (int) ($_POST['album_id'] ?? 0);
        $album   = (new Album($this->config))->find($albumId);
        if ($album === null) {
            $this->setFlash('error', 'Álbum não encontrado.');
            $this->redirect('/admin/galeria');
        }

        $files = $_FILES['photos'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            $this->setFlash('error', 'Selecione ao menos uma foto.');
            $this->redirect('/admin/galeria/editar?id=' . $albumId);
        }

        $photosModel = new AlbumPhoto($this->config);
        $ok = 0;
        $fail = 0;

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            // Normaliza o item para o formato esperado pelo ImageUploader.
            $one = [
                'name'     => $files['name'][$i] ?? '',
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
            try {
                // Galeria: proporção paisagem padrão (4:3) e tamanho maior.
                $path = ImageUploader::handle($one, 'albums', 1200, 900);
                $photosModel->add($albumId, $path);
                $ok++;
            } catch (Throwable $e) {
                error_log('[AlbumAdmin] uploadPhotos: ' . $e->getMessage());
                $fail++;
            }
        }

        if ($ok > 0 && $fail === 0) {
            $this->setFlash('success', $ok . ' foto(s) adicionada(s).');
        } elseif ($ok > 0) {
            $this->setFlash('success', $ok . ' foto(s) adicionada(s); ' . $fail . ' falhou(aram).');
        } else {
            $this->setFlash('error', 'Nenhuma foto pôde ser adicionada.');
        }

        $this->redirect('/admin/galeria/editar?id=' . $albumId);
    }

    /** Remove uma foto de um álbum. */
    public function destroyPhoto(): void
    {
        $this->guard();
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/galeria');
        }

        $photoId = (int) ($_POST['photo_id'] ?? 0);
        $albumId = (int) ($_POST['album_id'] ?? 0);
        try {
            $model = new AlbumPhoto($this->config);
            $photo = $model->find($photoId);
            if ($photo !== null) {
                ImageUploader::remove((string) $photo['image']);
                $model->delete($photoId);
                $albumId = (int) $photo['album_id'];
            }
            $this->setFlash('success', 'Foto removida.');
        } catch (Throwable $e) {
            error_log('[AlbumAdmin] destroyPhoto: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível remover a foto.');
        }

        $this->redirect('/admin/galeria/editar?id=' . $albumId);
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
