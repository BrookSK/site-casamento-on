<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Envio de webhooks (POST JSON) para uma URL configurada.
 *
 * Usado para notificar sistemas externos (ex.: automações/bots) quando um
 * convidado envia a confirmação de presença. É deliberadamente tolerante a
 * falhas: um erro de rede ou uma resposta de erro NUNCA deve interromper o
 * fluxo que o chamou (a confirmação do convidado já foi gravada). Em caso de
 * problema, apenas registra no log e devolve false.
 */
final class Webhook
{
    /** Tempo máximo (segundos) para conectar e completar a requisição. */
    private const TIMEOUT = 5;

    /**
     * Dispara um POST JSON para a URL informada.
     *
     * @param string               $url     URL de destino (http/https).
     * @param array<string, mixed> $payload Dados a serem enviados como JSON.
     * @return bool true se o envio ocorreu e o destino respondeu 2xx.
     */
    public static function send(string $url, array $payload): bool
    {
        $url = trim($url);
        if ($url === '' || !self::isValidUrl($url)) {
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log('[Webhook] Falha ao serializar payload JSON.');
            return false;
        }

        try {
            if (function_exists('curl_init')) {
                return self::sendCurl($url, $json);
            }
            return self::sendStream($url, $json);
        } catch (Throwable $e) {
            error_log('[Webhook] send: ' . $e->getMessage());
            return false;
        }
    }

    /** Aceita apenas URLs http/https bem formadas. */
    private static function isValidUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    /** Envio via cURL (preferencial). */
    private static function sendCurl(string $url, string $json): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false || $err !== '') {
            error_log('[Webhook] cURL: ' . $err);
            return false;
        }

        if ($status < 200 || $status >= 300) {
            error_log('[Webhook] Destino respondeu HTTP ' . $status);
            return false;
        }

        return true;
    }

    /** Envio via stream (fallback quando a extensão cURL não está disponível). */
    private static function sendStream(string $url, string $json): bool
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json; charset=utf-8\r\n"
                    . "Accept: application/json\r\n",
                'content'       => $json,
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('[Webhook] stream: falha ao enviar para ' . $url);
            return false;
        }

        // $http_response_header é populado pelo wrapper HTTP.
        $status = self::statusFromHeaders($http_response_header ?? []);
        if ($status < 200 || $status >= 300) {
            error_log('[Webhook] Destino respondeu HTTP ' . $status);
            return false;
        }

        return true;
    }

    /**
     * Extrai o código HTTP da primeira linha de status dos cabeçalhos.
     *
     * @param array<int, string> $headers
     */
    private static function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
