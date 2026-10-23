<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Geração de QR Code a partir de uma URL.
 *
 * Usa a biblioteca endroid/qr-code (declarada no composer.json) quando o
 * vendor está instalado. É TOLERANTE: se a lib não estiver disponível ou a
 * geração falhar, retorna null — a tela que chama deve exibir o link/token
 * mesmo assim, sem quebrar. A imagem é um PNG gravado em
 * public/assets/img/invites/, servido como arquivo estático.
 */
final class QrImage
{
    /** Subpasta (relativa a public/assets/img) onde os QRs são gravados. */
    private const SUBDIR = 'invites';

    /**
     * Verifica se a biblioteca de geração está disponível (vendor instalado).
     */
    public static function isAvailable(): bool
    {
        return class_exists(\Endroid\QrCode\QrCode::class);
    }

    /**
     * Gera (ou reaproveita) o PNG do QR para a URL informada, nomeado pelo
     * $token. Retorna o caminho relativo a public/ (ex.: assets/img/invites/
     * <token>.png) para montar a URL, ou null se não foi possível gerar.
     */
    public static function generatePng(string $token, string $url): ?string
    {
        // Sanitiza o nome do arquivo: só aceita o formato de token esperado.
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }

        if (!defined('PUBLIC_PATH')) {
            return null;
        }

        $dir = PUBLIC_PATH . '/assets/img/' . self::SUBDIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $relative = 'assets/img/' . self::SUBDIR . '/' . $token . '.png';
        $absolute = $dir . '/' . $token . '.png';

        // Reaproveita se já existe (o token é imutável).
        if (is_file($absolute)) {
            return $relative;
        }

        if (!self::isAvailable()) {
            return null;
        }

        try {
            // API compatível com endroid/qr-code 4.x (construtor + writer).
            $qr = \Endroid\QrCode\QrCode::create($url)
                ->setSize(320)
                ->setMargin(16);

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qr);
            $result->saveToFile($absolute);

            return is_file($absolute) ? $relative : null;
        } catch (\Throwable $e) {
            error_log('[QrImage] generatePng: ' . $e->getMessage());
            return null;
        }
    }
}
