<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autenticação baseada na sessão nativa do PHP.
 *
 * Guarda apenas o necessário na sessão (id e nome do admin). Não armazena
 * senha nem hash. Faz hardening básico: cookie httponly, secure sob HTTPS,
 * e regeneração de ID no login (evita session fixation).
 */
final class Auth
{
    private const SESSION_KEY = 'admin';

    /**
     * Inicia a sessão com parâmetros seguros (idempotente).
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,       // JS não acessa o cookie (mitiga XSS)
            'secure'   => $https,     // só trafega em HTTPS quando disponível
            'samesite' => 'Lax',      // mitiga CSRF em navegação cross-site
        ]);

        session_start();
    }

    /**
     * Registra o admin autenticado na sessão.
     *
     * @param array{id:int|string, name:string, email:string} $admin
     */
    public static function login(array $admin): void
    {
        self::start();

        // Evita session fixation: novo ID após autenticar.
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'    => (int) $admin['id'],
            'name'  => (string) $admin['name'],
            'email' => (string) $admin['email'],
        ];
    }

    /** Retorna true se há um admin autenticado. */
    public static function check(): bool
    {
        self::start();
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * Dados do admin logado (ou null).
     *
     * @return array{id:int, name:string, email:string}|null
     */
    public static function user(): ?array
    {
        self::start();
        /** @var array{id:int, name:string, email:string}|null */
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /** Encerra a sessão do admin. */
    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
