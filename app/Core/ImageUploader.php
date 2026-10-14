<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Upload e padronização de imagens (presentes, álbuns, etc.).
 *
 * Recebe um arquivo enviado via formulário (entrada de $_FILES), valida o tipo
 * REAL (não só a extensão), recorta para uma proporção padrão e redimensiona
 * para uma largura máxima, recompactando para um arquivo leve. Assim todos os
 * cards exibem imagens com as mesmas dimensões visuais, sem distorção.
 *
 * Usa a extensão GD (comum em hospedagens PHP). Se o GD não estiver disponível,
 * grava o arquivo original (sem recorte), degradando com elegância.
 */
final class ImageUploader
{
    /** Tipos MIME aceitos. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Tamanho máximo do upload (5 MB). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Processa um upload e devolve o caminho relativo salvo (ex.:
     * "gifts/abc123.jpg"), pronto para guardar no banco e servir pela view.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     *        Item de $_FILES['campo'].
     * @param string $subdir   Subpasta dentro de assets/img (ex.: "gifts").
     * @param int    $targetW  Largura final padrão (px).
     * @param int    $targetH  Altura final padrão (px). Define a proporção.
     *
     * @return string Caminho relativo a assets/img (com a subpasta).
     * @throws RuntimeException em erro de validação ou gravação.
     */
    public static function handle(array $file, string $subdir = 'gifts', int $targetW = 800, int $targetH = 600): string
    {
        self::assertUploadOk($file);

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Arquivo de upload inválido.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('A imagem excede o tamanho máximo de 5 MB.');
        }

        $mime = self::detectMime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Formato não suportado. Envie JPG, PNG ou WebP.');
        }

        $dir = self::ensureDir($subdir);
        $ext = 'jpg'; // saída padronizada em JPEG (leve e universal)
        $filename = self::uniqueName($ext);
        $destAbs  = $dir . '/' . $filename;

        $ok = self::processWithGd($tmp, $destAbs, $mime, $targetW, $targetH);
        if (!$ok) {
            // Sem GD (ou falha no processamento): grava o original, preservando
            // a extensão real para que o navegador o interprete corretamente.
            $origExt  = self::ALLOWED[$mime];
            $filename = self::uniqueName($origExt);
            $destAbs  = $dir . '/' . $filename;
            if (!@move_uploaded_file($tmp, $destAbs)) {
                throw new RuntimeException('Não foi possível salvar a imagem enviada.');
            }
        }

        return $subdir . '/' . $filename;
    }

    /** Remove uma imagem previamente salva (caminho relativo a assets/img). */
    public static function remove(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || !defined('PUBLIC_PATH')) {
            return;
        }
        // Só permite remover dentro de assets/img (evita path traversal).
        if (str_contains($relativePath, '..')) {
            return;
        }
        $abs = PUBLIC_PATH . '/assets/img/' . $relativePath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    // ----------------------------------------------------------------
    //  Internos
    // ----------------------------------------------------------------

    /** Valida o código de erro do upload do PHP. */
    private static function assertUploadOk(array $file): void
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Nenhuma imagem foi enviada.');
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('A imagem é maior do que o permitido pelo servidor.');
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no envio da imagem (código ' . $err . ').');
        }
    }

    /** Detecta o MIME real a partir do conteúdo do arquivo. */
    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }
        // Fallback via getimagesize (também lê o conteúdo, não a extensão).
        $info = @getimagesize($path);
        return is_array($info) && isset($info['mime']) ? (string) $info['mime'] : '';
    }

    /** Garante a existência da subpasta de destino e devolve o caminho absoluto. */
    private static function ensureDir(string $subdir): string
    {
        if (!defined('PUBLIC_PATH')) {
            throw new RuntimeException('PUBLIC_PATH não definido.');
        }
        $subdir = trim($subdir, '/');
        if ($subdir === '' || !preg_match('/^[a-z0-9_\-]+$/i', $subdir)) {
            throw new RuntimeException('Subpasta de upload inválida.');
        }
        $dir = PUBLIC_PATH . '/assets/img/' . $subdir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta de imagens.');
        }
        return $dir;
    }

    /** Gera um nome de arquivo único e seguro. */
    private static function uniqueName(string $ext): string
    {
        try {
            $rand = bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $rand = (string) mt_rand();
        }
        return date('Ymd') . '_' . $rand . '.' . $ext;
    }

    /**
     * Recorta (center-crop) para a proporção alvo e redimensiona, salvando como
     * JPEG. Retorna false quando o GD não está disponível/suportado.
     */
    private static function processWithGd(string $src, string $destAbs, string $mime, int $targetW, int $targetH): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false; // GD indisponível
        }

        $image = self::loadGd($src, $mime);
        if ($image === null) {
            return false;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($image);
            return false;
        }

        // Não amplia além do original: ajusta o alvo se a imagem for menor.
        $outW = min($targetW, $srcW);
        $outH = (int) round($outW * $targetH / $targetW);
        if ($outH > $srcH) {
            $outH = $srcH;
            $outW = (int) round($outH * $targetW / $targetH);
        }

        // Área de recorte central mantendo a proporção alvo.
        $targetRatio = $targetW / $targetH;
        $srcRatio    = $srcW / $srcH;
        if ($srcRatio > $targetRatio) {
            // Mais larga: recorta as laterais.
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Mais alta: recorta topo/base.
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($outW, $outH);
        // Fundo branco (caso de PNG/WebP com transparência convertidos p/ JPEG).
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $outW, $outH, $white);

        imagecopyresampled(
            $dst, $image,
            0, 0, $cropX, $cropY,
            $outW, $outH, $cropW, $cropH
        );

        $ok = imagejpeg($dst, $destAbs, 82); // qualidade 82: bom equilíbrio

        imagedestroy($image);
        imagedestroy($dst);

        return $ok;
    }

    /** Carrega a imagem no GD conforme o MIME. @return \GdImage|resource|null */
    private static function loadGd(string $src, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($src) ?: null : null;
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($src) ?: null : null;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) ?: null : null;
            default:
                return null;
        }
    }
}
