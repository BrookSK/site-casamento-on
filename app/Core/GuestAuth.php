<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autenticação de CONVIDADOS, baseada na sessão nativa do PHP.
 *
 * Usa uma chave de sessão SEPARADA da do admin (App\Core\Auth), de modo que
 * um convidado logado nunca tenha acesso de administrador e vice-versa.
 * Reaproveita Auth::start() para manter os mesmos parâmetros seguros de cookie.
 */
final class GuestAuth
{
    private const SESSION_KEY = 'guest';

    /** Registra o convidado autenticado (regenera o ID da sessão). */
    public static function login(array $guest): void
    {
        Auth::start();
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'    => (int) $guest['id'],
            'name'  => (string) $guest['name'],
            'email' => (string) ($guest['email'] ?? ''),
        ];
    }

    public static function check(): bool
    {
        Auth::start();
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /** @return array{id:int, name:string, email:string}|null */
    public static function user(): ?array
    {
        Auth::start();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /** Encerra apenas a sessão do convidado (não mexe na do admin). */
    public static function logout(): void
    {
        Auth::start();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }
}
