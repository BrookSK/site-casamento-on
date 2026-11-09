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

    /**
     * Como asset(), mas acrescenta ?v=<timestamp> baseado na data de
     * modificação do arquivo. Isso força o navegador a baixar a versão nova
     * sempre que o CSS/JS mudar (cache busting), evitando conteúdo em cache.
     */
    public static function assetVersioned(string $path, string $baseUrl = ''): string
    {
        $url  = self::asset($path, $baseUrl);
        $file = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/assets/' . ltrim($path, '/') : '';
        $ver  = ($file !== '' && is_file($file)) ? (string) filemtime($file) : '1';
        return $url . '?v=' . $ver;
    }

    /**
     * Tags <link> do favicon (aba do navegador, atalho no celular e manifest).
     * Os arquivos ficam na raiz de public/ — o /favicon.ico também atende os
     * navegadores que o pedem direto, sem ler o <head>.
     */
    public static function favicons(string $baseUrl = ''): string
    {
        $base = rtrim($baseUrl, '/');
        $ver  = static function (string $file): string {
            $path = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/' . $file : '';
            return ($path !== '' && is_file($path)) ? '?v=' . filemtime($path) : '';
        };

        return '<link rel="icon" href="' . self::e($base . '/favicon.ico' . $ver('favicon.ico')) . '" sizes="48x48">' . "\n"
            . '    <link rel="icon" href="' . self::e($base . '/favicon.svg' . $ver('favicon.svg')) . '" type="image/svg+xml">' . "\n"
            . '    <link rel="apple-touch-icon" href="' . self::e($base . '/apple-touch-icon.png' . $ver('apple-touch-icon.png')) . '">' . "\n"
            . '    <link rel="manifest" href="' . self::e($base . '/site.webmanifest') . '">';
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
