<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\QrImage;
use App\Models\Guest;
use Throwable;

/**
 * QR Code de acesso ao site (compartilhamento).
 *
 * Exibe, no painel administrativo:
 *   - o QR Code PÚBLICO único, que aponta para a página de confirmação
 *     (/confirmacao-interna). É um arquivo estático já gerado
 *     (public/assets/img/qr-confirmacao.*);
 *   - os QR Codes INDIVIDUAIS já criados (um por convidado), com link
 *     exclusivo e download. As imagens individuais são geradas sob demanda
 *     por QrImage (endroid/qr-code) e é aqui que o admin as encontra.
 */
final class QrCodeController extends Controller
{
    /** Caminho (relativo à base_url) para onde o QR aponta. */
    private const TARGET_PATH = '/confirmacao-interna';

    /** Nome-base dos arquivos de imagem em public/assets/img. */
    private const IMAGE_BASENAME = 'qr-confirmacao';

    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }

        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        $target  = $baseUrl . self::TARGET_PATH;

        // Confere no disco quais formatos existem (para exibir/baixar).
        $dir      = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/assets/img/' : '';
        $pngExists = $dir !== '' && is_file($dir . self::IMAGE_BASENAME . '.png');
        $svgExists = $dir !== '' && is_file($dir . self::IMAGE_BASENAME . '.svg');

        // Convites individuais já criados: monta link + QR de cada um.
        $individualInvites = [];
        $invitesError      = null;
        try {
            $rows = (new Guest($this->config))->listIndividualInvites(200);
            foreach ($rows as $row) {
                $token = (string) ($row['invite_token'] ?? '');
                if ($token === '') {
                    continue;
                }
                $link   = $baseUrl . '/convite?token=' . $token;
                $qrPath = QrImage::generatePng($token, $link);
                $individualInvites[] = [
                    'name'      => (string) ($row['name'] ?? ''),
                    'token'     => $token,
                    'link'      => $link,
                    'qr_url'    => $qrPath !== null ? ($baseUrl . '/' . $qrPath) : null,
                    'confirmed' => ($row['rsvp_status'] ?? null) === 'confirmed',
                    'declined'  => ($row['rsvp_status'] ?? null) === 'declined',
                ];
            }
        } catch (Throwable $e) {
            error_log('[QrCode] individual invites: ' . $e->getMessage());
            $invitesError = 'Não foi possível carregar os convites individuais.';
        }

        $this->viewAdmin('admin/qrcode/index', [
            'pageTitle'  => 'QR Code de acesso',
            // Destaca "Conteúdo" no menu: esta tela é acessada de lá (card de atalho).
            'active'     => 'content',
            'admin'      => Auth::user(),
            'targetUrl'  => $target,
            'targetPath' => self::TARGET_PATH,
            'baseUrl'    => $baseUrl,
            'imageBase'  => self::IMAGE_BASENAME,
            'pngExists'  => $pngExists,
            'svgExists'  => $svgExists,
            // Convites individuais (seção nova nesta mesma tela).
            'individualInvites' => $individualInvites,
            'invitesError'      => $invitesError,
            'qrAvailable'       => QrImage::isAvailable(),
            'createInviteUrl'   => $baseUrl . '/admin/convites/novo',
        ]);
    }
}
