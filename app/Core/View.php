<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Helpers de view usados dentro dos templates.
 *
 * Mantidos como funções estáticas simples para evitar lógica nas views.
 */
final class View
{
    /** Escapa texto para saída segura em HTML. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Monta um caminho de asset respeitando a base_url configurada.
     * Ex.: asset('css/style.css') -> /assets/css/style.css
     */
    public static function asset(string $path, string $baseUrl = ''): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/assets/' . ltrim($path, '/');
    }

    /** Monta uma URL interna respeitando a base_url. */
    public static function url(string $path, string $baseUrl = ''): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }

    /** Extensões de imagem aceitas, em ordem de preferência. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    /**
     * Resolve uma imagem a partir de um nome-base (sem extensão), procurando
     * no disco qual extensão existe de fato.
     *
     * Ex.: image('hero') tenta hero.jpg, hero.jpeg, hero.png, hero.webp...
     * e usa a primeira que existir em public/assets/img/.
     *
     * @param string $name    Nome-base do arquivo (sem extensão), ex.: "hero"
     * @param string $baseUrl base_url da aplicação
     * @return array{exists: bool, url: string, file: string} Dados da imagem
     */
    public static function image(string $name, string $baseUrl = ''): array
    {
        $name    = ltrim($name, '/');
        $dir     = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/assets/img/' : '';

        foreach (self::IMAGE_EXTENSIONS as $ext) {
            $file = $dir . $name . '.' . $ext;
            if ($dir !== '' && is_file($file)) {
                return [
                    'exists' => true,
                    'url'    => self::asset('img/' . $name . '.' . $ext, $baseUrl),
                    'file'   => $file,
                ];
            }
        }

        // Nenhuma imagem encontrada: devolve o caminho .jpg como referência
        // (usado apenas para a mensagem de placeholder).
        return [
            'exists' => false,
            'url'    => self::asset('img/' . $name . '.jpg', $baseUrl),
            'file'   => $dir . $name . '.jpg',
        ];
    }
}
