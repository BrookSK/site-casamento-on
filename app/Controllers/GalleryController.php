<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Album;
use App\Models\AlbumPhoto;
use Throwable;

/**
 * Página pública da Galeria de Fotos, organizada por álbuns.
 *
 * Os álbuns e suas fotos são gerenciados pelo painel administrativo
 * (Conteúdo > Galeria de fotos) e persistidos no banco (tabelas albums e
 * album_photos). Esta página apenas lê e exibe.
 */
final class GalleryController extends Controller
{
    public function index(): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');

        $albums = [];
        try {
            $albumRows = (new Album($this->config))->listAll();
            if ($albumRows !== []) {
                $ids    = array_map(static fn ($a) => (int) $a['id'], $albumRows);
                $photos = (new AlbumPhoto($this->config))->listForAlbums($ids);

                foreach ($albumRows as $a) {
                    $aid    = (int) $a['id'];
                    $title  = (string) $a['title'];
                    $list   = [];
                    foreach ($photos[$aid] ?? [] as $i => $p) {
                        $list[] = [
                            'url' => $this->resolveImage((string) $p['image'], $baseUrl),
                            'alt' => $p['caption'] !== null && $p['caption'] !== ''
                                ? (string) $p['caption']
                                : ($title . ' — foto ' . ($i + 1)),
                        ];
                    }
                    // Só exibe álbuns que tenham ao menos uma foto.
                    if ($list !== []) {
                        $albums[] = [
                            'title'       => $title,
                            'subtitle'    => (string) ($a['subtitle'] ?? ''),
                            'description' => (string) ($a['description'] ?? ''),
                            'photos'      => $list,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[Gallery] index: ' . $e->getMessage());
            $albums = [];
        }

        $this->view('gallery/index', [
            'pageTitle' => 'Galeria de Fotos',
            'albums'    => $albums,
            // Página de fundo claro: header sólido desde o topo (legível).
            'bodyClass' => 'page-light',
        ]);
    }

    /** Resolve a URL de uma imagem salva (http, absoluta ou relativa a assets/img). */
    private function resolveImage(string $image, string $baseUrl): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, 'http') || str_starts_with($image, '/')) {
            return $image;
        }
        return $baseUrl . '/assets/img/' . ltrim($image, '/');
    }
}
