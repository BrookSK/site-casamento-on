<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Proteção CSRF via token na sessão.
 *
 * Gera um token por sessão e valida no POST com comparação em tempo
 * constante (hash_equals), evitando ataques de timing.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    /** Retorna o token atual, criando-o se necessário. */
    public static function token(): string
    {
        Auth::start();

        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /** Campo <input> oculto pronto para colar no formulário. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::KEY . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Valida o token recebido (ex.: $_POST['_csrf_token']). */
    public static function validate(?string $token): bool
    {
        Auth::start();

        $expected = $_SESSION[self::KEY] ?? '';

        return is_string($token)
            && $expected !== ''
            && hash_equals($expected, $token);
    }

    /** Nome do campo esperado no POST. */
    public static function fieldName(): string
    {
        return self::KEY;
    }
}
