<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Templates HTML dos e-mails automáticos (ponto 7 — "Implementar os templates
 * HTML dos e-mails").
 *
 * Gera um HTML autocontido e compatível com clientes de e-mail (estilos
 * inline, tabela de layout, largura fixa). Todo conteúdo dinâmico é escapado
 * com htmlspecialchars antes de entrar no HTML.
 *
 * Os textos (cor, nomes do casal, assinatura) vêm do config/settings e são
 * passados pelo chamador (Notifier), mantendo esta classe sem acesso ao banco.
 */
final class EmailTemplate
{
    /** Cor de destaque (mesma paleta do site: verde-oliva do theme-color). */
    private const BRAND = '#8a8f6f';

    /**
     * Monta o e-mail de BOAS-VINDAS após a criação/assunção da conta.
     *
     * @param array{
     *   name: string,
     *   siteName: string,
     *   siteUrl: string,
     *   accountEmail: string,
     *   passwordLink: ?string,
     *   signature: ?string
     * } $data
     */
    public static function welcome(array $data): string
    {
        $name    = self::firstName($data['name']);
        $siteUrl = trim($data['siteUrl']);
        $email   = trim($data['accountEmail']);
        $link    = $data['passwordLink'] ?? null;

        $intro = 'Que alegria ter você com a gente! Criamos um acesso para que você '
            . 'possa confirmar presença, ver a lista de presentes e acompanhar tudo '
            . 'em um só lugar.';

        $rows = '';

        // Endereço do site (ponto 7 — "Informar o endereço do site").
        if ($siteUrl !== '') {
            $rows .= self::infoRow('Endereço do site', self::link($siteUrl, $siteUrl));
        }

        // E-mail cadastrado (ponto 7 — "e o e-mail cadastrado").
        if ($email !== '') {
            $rows .= self::infoRow('Seu e-mail de acesso', self::escape($email));
        }

        $body = '<p style="margin:0 0 16px;">Olá, <strong>' . self::escape($name) . '</strong>!</p>'
            . '<p style="margin:0 0 20px;line-height:1.6;">' . self::escape($intro) . '</p>';

        if ($rows !== '') {
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
                . 'style="border-collapse:collapse;margin:0 0 24px;">' . $rows . '</table>';
        }

        // Link seguro de definição de senha (ponto 7 — "Disponibilizar link
        // seguro para definição ou recuperação de senha").
        if ($link !== null && $link !== '') {
            $body .= '<p style="margin:0 0 16px;line-height:1.6;">Para começar, '
                . 'defina a sua senha de acesso usando o botão seguro abaixo. '
                . 'O link é pessoal e tem validade limitada.</p>'
                . self::button('Definir minha senha', $link)
                . '<p style="margin:16px 0 0;font-size:13px;color:#777;line-height:1.5;">'
                . 'Se o botão não funcionar, copie e cole este endereço no navegador:<br>'
                . self::link($link, $link, '13px') . '</p>';
        } else {
            $body .= '<p style="margin:0 0 16px;line-height:1.6;">Acesse o site e '
                . 'use a opção <strong>Entrar</strong> para gerenciar sua presença.</p>';
            if ($siteUrl !== '') {
                $body .= self::button('Acessar o site', $siteUrl);
            }
        }

        return self::wrap($data['siteName'], $body, $data['signature'] ?? null);
    }

    /**
     * Monta o e-mail de RECUPERAÇÃO de senha ("esqueci minha senha").
     *
     * @param array{
     *   name: string,
     *   siteName: string,
     *   siteUrl: string,
     *   resetLink: string,
     *   signature: ?string
     * } $data
     */
    public static function passwordReset(array $data): string
    {
        $name = self::firstName($data['name']);
        $link = trim($data['resetLink']);

        $body = '<p style="margin:0 0 16px;">Olá, <strong>' . self::escape($name) . '</strong>!</p>'
            . '<p style="margin:0 0 20px;line-height:1.6;">Recebemos um pedido para '
            . 'redefinir a senha da sua conta. Clique no botão abaixo para criar uma '
            . 'nova senha. Se não foi você, pode ignorar este e-mail — nada muda.</p>'
            . self::button('Redefinir minha senha', $link)
            . '<p style="margin:16px 0 0;font-size:13px;color:#777;line-height:1.5;">'
            . 'Se o botão não funcionar, copie e cole este endereço no navegador:<br>'
            . self::link($link, $link, '13px') . '</p>'
            . '<p style="margin:16px 0 0;font-size:13px;color:#777;">Por segurança, '
            . 'este link tem validade limitada.</p>';

        return self::wrap($data['siteName'], $body, $data['signature'] ?? null);
    }

    /**
     * Monta o e-mail de CONFIRMAÇÃO de presença (reaproveita o template).
     *
     * @param array{
     *   name: string,
     *   siteName: string,
     *   message: string,
     *   siteUrl: ?string,
     *   signature: ?string
     * } $data
     */
    public static function rsvp(array $data): string
    {
        $body = '<p style="margin:0 0 20px;line-height:1.6;">'
            . self::escape($data['message']) . '</p>';

        $siteUrl = trim((string) ($data['siteUrl'] ?? ''));
        if ($siteUrl !== '') {
            $body .= self::button('Acessar o site', $siteUrl);
        }

        return self::wrap($data['siteName'], $body, $data['signature'] ?? null);
    }

    // ----------------------------------------------------------------
    //  Blocos reutilizáveis
    // ----------------------------------------------------------------

    /** Envelope HTML comum (cabeçalho + conteúdo + rodapé). */
    private static function wrap(string $siteName, string $innerHtml, ?string $signature): string
    {
        $site = self::escape($siteName);
        $foot = $signature !== null && trim($signature) !== ''
            ? self::escape($signature)
            : $site;

        return '<!DOCTYPE html>'
            . '<html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<title>' . $site . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f3ee;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#333;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="background:#f4f3ee;padding:24px 0;"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" '
            . 'style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;'
            . 'overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
            // Cabeçalho
            . '<tr><td style="background:' . self::BRAND . ';padding:28px 32px;text-align:center;">'
            . '<span style="color:#ffffff;font-size:22px;letter-spacing:1px;">' . $site . '</span>'
            . '</td></tr>'
            // Conteúdo
            . '<tr><td style="padding:32px;font-size:16px;color:#333;">' . $innerHtml . '</td></tr>'
            // Rodapé
            . '<tr><td style="padding:20px 32px;background:#faf9f5;text-align:center;'
            . 'font-size:13px;color:#999;border-top:1px solid #eee;">'
            . 'Com carinho,<br>' . $foot
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Botão de ação (call-to-action) com estilo inline. */
    private static function button(string $label, string $url): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">'
            . '<tr><td style="border-radius:6px;background:' . self::BRAND . ';">'
            . '<a href="' . self::attr($url) . '" target="_blank" '
            . 'style="display:inline-block;padding:13px 26px;color:#ffffff;'
            . 'text-decoration:none;font-size:15px;border-radius:6px;">'
            . self::escape($label) . '</a></td></tr></table>';
    }

    /** Linha de informação rotulada (label + valor) em tabela. */
    private static function infoRow(string $label, string $valueHtml): string
    {
        return '<tr><td style="padding:8px 0;border-bottom:1px solid #f0efe8;">'
            . '<span style="display:block;font-size:12px;text-transform:uppercase;'
            . 'letter-spacing:.5px;color:#999;">' . self::escape($label) . '</span>'
            . '<span style="display:block;font-size:15px;color:#333;margin-top:2px;">'
            . $valueHtml . '</span></td></tr>';
    }

    /** Link textual seguro. */
    private static function link(string $url, string $label, string $size = '15px'): string
    {
        return '<a href="' . self::attr($url) . '" target="_blank" '
            . 'style="color:' . self::BRAND . ';font-size:' . $size . ';word-break:break-all;">'
            . self::escape($label) . '</a>';
    }

    private static function firstName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'convidado(a)';
        }
        return explode(' ', $name)[0];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escapa valor para atributo href (mesma política do escape acima). */
    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
