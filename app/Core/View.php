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
}
