<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use RuntimeException;
use Throwable;

/**
 * Cliente SMTP (envio de e-mail) sem dependências externas.
 *
 * Lê as configurações de SMTP salvas na tabela settings (host, porta,
 * usuário, segurança e remetente) e a senha cifrada, e envia mensagens
 * HTML por SMTP falando o protocolo diretamente sobre um socket — no mesmo
 * estilo "sem bibliotecas de terceiros" do restante do projeto (ver
 * Core/Asaas.php e Core/Webhook.php, que usam cURL/streams na mão).
 *
 * Segurança suportada:
 *   - 'ssl'  : conexão TLS implícita (porta típica 465);
 *   - 'tls'  : conexão em texto e STARTTLS (porta típica 587);
 *   - 'none' : sem criptografia (não recomendado; só redes confiáveis).
 *
 * Autenticação: AUTH LOGIN (usuário/senha), quando houver usuário configurado.
 *
 * Em caso de erro de rede/protocolo lança RuntimeException com mensagem
 * legível. Os fluxos que apenas "notificam" (RSVP, confirmação de pagamento)
 * devem capturar e registrar no log, nunca interromper a ação do usuário.
 */
final class Mailer
{
    /** Tempo máximo (segundos) para conectar e para cada leitura/escrita. */
    private const TIMEOUT = 15;

    private string $host;
    private int    $port;
    private string $security;   // tls | ssl | none
    private string $user;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    private function __construct(
        string $host,
        int $port,
        string $security,
        string $user,
        string $password,
        string $fromEmail,
        string $fromName
    ) {
        $this->host      = $host;
        $this->port      = $port;
        $this->security  = $security;
        $this->user      = $user;
        $this->password  = $password;
        $this->fromEmail = $fromEmail;
        $this->fromName  = $fromName;
    }

    /**
     * Monta o cliente a partir das configurações salvas.
     *
     * @throws RuntimeException quando o SMTP não está configurado o suficiente
     *                          ou a senha não pode ser decifrada.
     */
    public static function fromSettings(array $config): self
    {
        $settings = new Setting($config);
        $values   = $settings->getMany([
            'smtp_host', 'smtp_port', 'smtp_user',
            'smtp_security', 'smtp_from_email', 'smtp_from_name',
        ]);

        $host = trim((string) ($values['smtp_host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('O servidor SMTP ainda não foi configurado.');
        }

        $security = (string) ($values['smtp_security'] ?? 'tls');
        if (!in_array($security, ['tls', 'ssl', 'none'], true)) {
            $security = 'tls';
        }

        // Porta: usa a configurada ou um padrão coerente com a segurança.
        $port = (int) ($values['smtp_port'] ?? 0);
        if ($port <= 0) {
            $port = $security === 'ssl' ? 465 : 587;
        }

        $fromEmail = trim((string) ($values['smtp_from_email'] ?? ''));
        if ($fromEmail === '') {
            throw new RuntimeException('O e-mail remetente (From) ainda não foi configurado.');
        }

        $user = trim((string) ($values['smtp_user'] ?? ''));

        // Senha (cifrada). Pode estar vazia quando o servidor não exige auth.
        $password = '';
        $enc = $settings->get('smtp_password');
        if ($enc !== null && $enc !== '') {
            $plain = Crypto::decrypt($enc);
            if ($plain === null) {
                throw new RuntimeException('Não foi possível ler a senha SMTP (verifique a chave de aplicação).');
            }
            $password = $plain;
        }

        $fromName = trim((string) ($values['smtp_from_name'] ?? ''));

        return new self($host, $port, $security, $user, $password, $fromEmail, $fromName);
    }

    /** Indica se o SMTP está pronto para uso (configuração mínima presente). */
    public static function isConfigured(array $config): bool
    {
        try {
            self::fromSettings($config);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Envia uma mensagem HTML.
     *
     * @param string $toEmail  Destinatário.
     * @param string $subject  Assunto.
     * @param string $html     Corpo em HTML.
     * @param string $toName   Nome do destinatário (opcional).
     *
     * @throws RuntimeException em erro de conexão/protocolo.
     */
    public function send(string $toEmail, string $subject, string $html, string $toName = ''): void
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Endereço de destinatário inválido.');
        }

        $socket = $this->open();
        try {
            $this->handshakeAndAuth($socket);
            $this->sendEnvelope($socket, $toEmail, $toName, $subject, $html);
            // Encerra educadamente (ignora falhas no QUIT).
            try {
                $this->command($socket, 'QUIT', [221]);
            } catch (Throwable $e) {
                // sem problema: a mensagem já foi aceita.
            }
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Testa a configuração: conecta, (opcionalmente) autentica e encerra, sem
     * enviar mensagem. Usado pelo botão "Testar envio" no painel.
     *
     * @return array{ok:bool, message:string}
     */
    public function ping(): array
    {
        try {
            $socket = $this->open();
            try {
                $this->handshakeAndAuth($socket);
                try {
                    $this->command($socket, 'QUIT', [221]);
                } catch (Throwable $e) {
                    // ok
                }
            } finally {
                if (is_resource($socket)) {
                    fclose($socket);
                }
            }
            return ['ok' => true, 'message' => 'Conexão SMTP validada com sucesso.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    //  Protocolo SMTP (socket na mão)
    // ----------------------------------------------------------------

    /** @return resource */
    private function open()
    {
        $transport = $this->security === 'ssl' ? 'ssl://' : '';
        $errno  = 0;
        $errstr = '';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new RuntimeException(
                'Falha ao conectar no servidor SMTP (' . $this->host . ':' . $this->port . '): '
                . ($errstr !== '' ? $errstr : ('erro ' . $errno)) . '.'
            );
        }

        stream_set_timeout($socket, self::TIMEOUT);

        // Saudação inicial do servidor (220).
        $this->expect($socket, [220]);

        return $socket;
    }

    /**
     * EHLO, STARTTLS (quando aplicável) e autenticação.
     *
     * @param resource $socket
     */
    private function handshakeAndAuth($socket): void
    {
        $ehloHost = $this->ehloHostname();

        $this->command($socket, 'EHLO ' . $ehloHost, [250]);

        if ($this->security === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);

            $ok = @stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            );
            if ($ok !== true) {
                throw new RuntimeException('Falha ao estabelecer o TLS (STARTTLS) com o servidor SMTP.');
            }

            // Após o STARTTLS é obrigatório reemitir o EHLO.
            $this->command($socket, 'EHLO ' . $ehloHost, [250]);
        }

        // Autentica apenas quando há usuário configurado.
        if ($this->user !== '') {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->user), [334]);
            $this->command($socket, base64_encode($this->password), [235]);
        }
    }

    /**
     * MAIL FROM / RCPT TO / DATA + corpo.
     *
     * @param resource $socket
     */
    private function sendEnvelope($socket, string $toEmail, string $toName, string $subject, string $html): void
    {
        $this->command($socket, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
        $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        $this->command($socket, 'DATA', [354]);

        $message = $this->buildMime($toEmail, $toName, $subject, $html);

        // Encerra o DATA com <CRLF>.<CRLF>; o servidor confirma com 250.
        $this->write($socket, $message . "\r\n.\r\n");
        $this->expect($socket, [250]);
    }

    /**
     * Monta a mensagem MIME (cabeçalhos + corpo HTML, UTF-8).
     */
    private function buildMime(string $toEmail, string $toName, string $subject, string $html): string
    {
        $fromHeader = $this->formatAddress($this->fromEmail, $this->fromName);
        $toHeader   = $this->formatAddress($toEmail, $toName);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->ehloHostname() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        // Protege contra "." no início de linha (dot-stuffing do SMTP).
        $body = preg_replace('/^\./m', '..', $this->normalizeEol($html));

        return implode("\r\n", $headers) . "\r\n\r\n" . (string) $body;
    }

    /** Formata "Nome <email>" com o nome codificado quando necessário. */
    private function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    /** Codifica um cabeçalho em UTF-8 (RFC 2047) quando tiver não-ASCII. */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /** Normaliza quebras de linha para CRLF. */
    private function normalizeEol(string $text): string
    {
        return (string) preg_replace('/\r\n|\r|\n/', "\r\n", $text);
    }

    /** Hostname usado no EHLO/Message-ID (nome do servidor web ou um padrão). */
    private function ehloHostname(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '');
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        $host = trim((string) $host);
        if ($host === '' || preg_match('/^[A-Za-z0-9.\-]+$/', $host) !== 1) {
            // Deriva do e-mail remetente como fallback.
            $parts = explode('@', $this->fromEmail);
            $host  = $parts[1] ?? 'localhost';
        }
        return $host;
    }

    /**
     * Envia um comando e valida o código de resposta esperado.
     *
     * @param resource          $socket
     * @param array<int,int>    $expected Códigos aceitos.
     */
    private function command($socket, string $command, array $expected): string
    {
        $this->write($socket, $command . "\r\n");
        return $this->expect($socket, $expected);
    }

    /**
     * Lê a resposta do servidor (suporta respostas multi-linha) e valida o
     * código inicial contra os esperados.
     *
     * @param resource       $socket
     * @param array<int,int> $expected
     */
    private function expect($socket, array $expected): string
    {
        $response = $this->read($socket);
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException(
                'Resposta inesperada do servidor SMTP: ' . trim($response)
            );
        }
        return $response;
    }

    /**
     * Lê uma resposta SMTP completa (linhas "250-" continuam; "250 " encerra).
     *
     * @param resource $socket
     */
    private function read($socket): string
    {
        $data = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $data .= $line;
            // 4º caractere ' ' marca a última linha; '-' indica continuação.
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($data === '') {
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('Tempo esgotado aguardando resposta do servidor SMTP.');
            }
            throw new RuntimeException('O servidor SMTP encerrou a conexão inesperadamente.');
        }

        return $data;
    }

    /**
     * Escreve no socket, tratando erros de escrita.
     *
     * @param resource $socket
     */
    private function write($socket, string $data): void
    {
        $written = @fwrite($socket, $data);
        if ($written === false) {
            throw new RuntimeException('Falha ao enviar dados para o servidor SMTP.');
        }
    }
}
