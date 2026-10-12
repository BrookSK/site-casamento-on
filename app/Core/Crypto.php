<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Criptografia simétrica para segredos guardados no banco (ex.: senha SMTP,
 * chave do ASAAS). Usa AES-256-GCM (autenticado).
 *
 * A chave vem de app/Config/app_key.php (fora do Git). Se o arquivo não
 * existir, as funções lançam exceção — evita salvar segredo sem proteção.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    /** Lê a chave binária de 32 bytes a partir da config. */
    private static function key(): string
    {
        $file = APP_PATH . '/Config/app_key.php';
        if (!is_file($file)) {
            throw new RuntimeException(
                'Chave de aplicação ausente. Gere app/Config/app_key.php '
                . '(veja database/generate_key.php).'
            );
        }

        $key = require $file;
        $bin = base64_decode((string) $key, true);

        if ($bin === false || strlen($bin) !== 32) {
            throw new RuntimeException('Chave de aplicação inválida (esperado 32 bytes base64).');
        }

        return $bin;
    }

    /** Cifra um texto; retorna string base64 (iv + tag + ciphertext). */
    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $iv  = random_bytes(12); // GCM recomenda 12 bytes
        $tag = '';

        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao cifrar o valor.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /** Decifra um valor gerado por encrypt(). Retorna null se inválido. */
    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) { // 12 iv + 16 tag + >=1
            return null;
        }

        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /** Indica se a chave de aplicação está configurada. */
    public static function hasKey(): bool
    {
        return is_file(APP_PATH . '/Config/app_key.php');
    }
}
