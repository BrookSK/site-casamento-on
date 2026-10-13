<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;

/**
 * Página independente da Galeria de Fotos, organizada por álbuns.
 *
 * Nesta etapa (sem banco), os álbuns e suas fotos são resolvidos por
 * convenção de nome de arquivo em public/assets/img:
 *
 *   album-<slug>-1, album-<slug>-2, ... (jpg/jpeg/png/webp/avif)
 *
 * Para adicionar fotos a um álbum, basta subir os arquivos seguindo a
 * numeração sequencial. Para criar um novo álbum, acrescente uma entrada
 * em self::ALBUMS com um título e um slug novos.
 */
final class GalleryController extends Controller
{
    /**
     * Definição dos álbuns (ordem de exibição). Cada álbum tem um título
     * visível e um slug usado no nome dos arquivos de imagem.
     *
     * @var array<int, array{title:string, slug:string}>
     */
    private const ALBUMS = [
        ['title' => 'Nosso Pré-Casamento',  'slug' => 'pre-casamento'],
        ['title' => 'Cerimônia de Casamento', 'slug' => 'cerimonia'],
        ['title' => 'Festa de Casamento',    'slug' => 'festa'],
        ['title' => 'Banda e Atrações',      'slug' => 'banda'],
        ['title' => 'Momentos Especiais',    'slug' => 'momentos'],
    ];

    /** Máximo de fotos procuradas por álbum (numeração 1..N). */
    private const MAX_PHOTOS_PER_ALBUM = 60;

    public function index(): void
    {
        $baseUrl = (string) ($this->config['app']['base_url'] ?? '');

        $albums = [];
        foreach (self::ALBUMS as $album) {
            $photos = $this->resolveAlbumPhotos($album['slug'], $album['title'], $baseUrl);
            // Só inclui álbuns que tenham ao menos uma foto no disco.
            if ($photos !== []) {
                $albums[] = [
                    'title'  => $album['title'],
                    'slug'   => $album['slug'],
                    'photos' => $photos,
                ];
            }
        }

        $this->view('gallery/index', [
            'pageTitle' => 'Galeria de Fotos',
            'albums'    => $albums,
        ]);
    }

    /**
     * Resolve as fotos existentes de um álbum (numeração sequencial).
     * Para na primeira lacuna para manter a ordem previsível.
     *
     * @return array<int, array{url:string, alt:string}>
     */
    private function resolveAlbumPhotos(string $slug, string $title, string $baseUrl): array
    {
        $photos = [];
        for ($i = 1; $i <= self::MAX_PHOTOS_PER_ALBUM; $i++) {
            $img = View::image('album-' . $slug . '-' . $i, $baseUrl);
            if (!$img['exists']) {
                break; // sem a próxima foto numerada: encerra o álbum
            }
            $photos[] = [
                'url' => $img['url'],
                'alt' => $title . ' — foto ' . $i,
            ];
        }
        return $photos;
    }
}
