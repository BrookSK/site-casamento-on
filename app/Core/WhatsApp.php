<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Envio de mensagens automáticas pelo WhatsApp (ponto 7).
 *
 * O envio é delegado a um WEBHOOK interno já existente (a instalação da
 * Evolution API da ON Solutions). O site NÃO fala com a Evolution diretamente:
 * ele apenas faz um POST JSON { phone, message } para a URL do webhook, que se
 * encarrega de disparar a mensagem. A autenticação é o token embutido na
 * própria URL (não há header). O endpoint responde HTTP 2xx em caso de sucesso.
 *
 * A URL usada é a MESMA do webhook de confirmações (chave webhook_rsvp_url),
 * por decisão de arquitetura — um único ponto de integração com a ON. Mantém
 * compatibilidade com a chave antiga (whatsapp_api_url) como fallback.
 *
 * O envio é tolerante a falhas (nunca interrompe o fluxo do convidado):
 * registra no log e devolve o resultado.
 */
final class WhatsApp
{
    private const TIMEOUT = 6;

    private string $apiUrl;
    private string $defaultCountry; // DDI padrão (ex.: 55 para Brasil)

    private function __construct(string $apiUrl, string $defaultCountry)
    {
        $this->apiUrl         = $apiUrl;
        $this->defaultCountry = $defaultCountry;
    }

    /** Monta a partir das configurações salvas. */
    public static function fromSettings(array $config): self
    {
        $settings = new Setting($config);
        $values   = $settings->getMany([
            'webhook_rsvp_url', 'whatsapp_api_url', 'whatsapp_default_country',
        ]);

        // Fonte única da URL: o webhook da ON (webhook_rsvp_url). Mantém o
        // fallback para a chave antiga, para não quebrar instalações legadas.
        $apiUrl = trim((string) ($values['webhook_rsvp_url'] ?? ''));
        if ($apiUrl === '') {
            $apiUrl = trim((string) ($values['whatsapp_api_url'] ?? ''));
        }

        $country = preg_replace('/\D+/', '', (string) ($values['whatsapp_default_country'] ?? '55')) ?: '55';

        return new self($apiUrl, $country);
    }

    /** Indica se há um webhook de WhatsApp configurado para envio automático. */
    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && filter_var($this->apiUrl, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Envia uma mensagem de texto para um telefone.
     *
     * @param string $phone   Telefone (qualquer formato; é normalizado).
     * @param string $message Texto da mensagem.
     * @return array{ok:bool, message:string}
     */
    public function send(string $phone, string $message): array
    {
        $to = $this->normalize($phone);
        if ($to === '') {
            return ['ok' => false, 'message' => 'Telefone inválido para envio no WhatsApp.'];
        }
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Webhook de WhatsApp não configurado.'];
        }

        $payload = ['phone' => $to, 'message' => $message];

        try {
            $ok = $this->post($this->apiUrl, $payload);
            return $ok
                ? ['ok' => true, 'message' => 'Mensagem enviada ao WhatsApp.']
                : ['ok' => false, 'message' => 'O webhook de WhatsApp respondeu com erro.'];
        } catch (Throwable $e) {
            error_log('[WhatsApp] send: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gera um link wa.me para abertura manual da conversa já com o texto.
     * Útil quando não há API configurada (disparo assistido pelo casal).
     */
    public function link(string $phone, string $message): string
    {
        $to = $this->normalize($phone);
        if ($to === '') {
            return '';
        }
        return 'https://wa.me/' . $to . '?text=' . rawurlencode($message);
    }

    /**
     * Normaliza o telefone para o formato internacional só com dígitos,
     * aplicando o DDI padrão quando o número não o inclui.
     */
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        // Já tem DDI (ex.: 55 + 11 dígitos = 13)? Mantém.
        if (strlen($digits) >= 12 && str_starts_with($digits, $this->defaultCountry)) {
            return $digits;
        }
        // 10/11 dígitos (DDD + número) → prefixa o DDI padrão.
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return $this->defaultCountry . $digits;
        }
        return $digits;
    }

    /**
     * POST JSON (cURL com fallback para stream) para o webhook de WhatsApp.
     * A autenticação é o token embutido na própria URL; não há header de auth.
     *
     * @param array<string,mixed> $payload
     */
    private function post(string $url, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $headers = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $resp   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err    = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $err !== '') {
                error_log('[WhatsApp] cURL: ' . $err);
                return false;
            }
            return $status >= 200 && $status < 300;
        }

        // Fallback via stream.
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $json,
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            return false;
        }
        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                break;
            }
        }
        return $status >= 200 && $status < 300;
    }
}
