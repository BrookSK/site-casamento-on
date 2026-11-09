<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use RuntimeException;
use Throwable;

/**
 * Cliente HTTP da API do ASAAS (gateway de pagamento).
 *
 * Lê o ambiente (sandbox/production) e a chave de API cifrada da tabela
 * settings, monta a URL base correta e autentica via header `access_token`.
 *
 * Cobre o fluxo necessário para a lista de presentes:
 *   - criar/garantir um cliente (customer);
 *   - criar a cobrança (payment) em PIX, CREDIT_CARD ou BOLETO;
 *   - recuperar o QR Code do PIX (encodedImage + payload).
 *
 * Em caso de erro de rede ou resposta não-2xx, lança RuntimeException com uma
 * mensagem legível (o controller decide como exibir ao usuário).
 */
final class Asaas
{
    private const BASE_SANDBOX    = 'https://api-sandbox.asaas.com/v3';
    private const BASE_PRODUCTION = 'https://api.asaas.com/v3';
    private const TIMEOUT         = 15;
    private const CARD_TIMEOUT    = 75;

    private string $baseUrl;
    private string $apiKey;

    private function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = $baseUrl;
        $this->apiKey  = $apiKey;
    }

    /**
     * Monta o cliente a partir das configurações salvas.
     *
     * @throws RuntimeException quando a chave não está configurada/decifrável.
     */
    public static function fromSettings(array $config): self
    {
        $settings = new Setting($config);
        $env      = (string) ($settings->get('asaas_environment') ?? 'sandbox');
        $enc      = $settings->get('asaas_api_key');

        if ($enc === null || $enc === '') {
            throw new RuntimeException('A chave de API do ASAAS ainda não foi configurada.');
        }

        $key = Crypto::decrypt($enc);
        if ($key === null || $key === '') {
            throw new RuntimeException('Não foi possível ler a chave do ASAAS (verifique a chave de aplicação).');
        }

        $baseUrl = $env === 'production' ? self::BASE_PRODUCTION : self::BASE_SANDBOX;
        return new self($baseUrl, $key);
    }

    /** Indica se o ASAAS está pronto para uso (chave presente e decifrável). */
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
     * Valida as credenciais fazendo uma chamada leve à API.
     * Usado pelo botão "Testar conexão" no painel.
     *
     * @return array{ok:bool, message:string}
     */
    public function ping(): array
    {
        try {
            // Lista 1 cobrança apenas para validar a autenticação.
            $this->request('GET', '/payments?limit=1');
            return ['ok' => true, 'message' => 'Conexão com o ASAAS validada com sucesso.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cria um cliente no ASAAS e retorna o id (cus_...).
     *
     * @param array{name:string, email?:?string, phone?:?string, cpfCnpj?:?string} $data
     */
    public function createCustomer(array $data): string
    {
        $payload = ['name' => $data['name']];
        if (!empty($data['email']))   { $payload['email']   = $data['email']; }
        if (!empty($data['phone'])) {
            // Celular (11 dígitos) vai em mobilePhone; fixo (10) em phone.
            // Número fora do padrão não é enviado: o telefone é opcional no
            // ASAAS e um formato inválido derrubaria a cobrança inteira.
            $phone = self::nationalPhone((string) $data['phone']);
            if (strlen($phone) === 11) {
                $payload['mobilePhone'] = $phone;
            } elseif (strlen($phone) === 10) {
                $payload['phone'] = $phone;
            }
        }
        if (!empty($data['cpfCnpj'])) { $payload['cpfCnpj'] = preg_replace('/\D+/', '', (string) $data['cpfCnpj']); }

        $res = $this->request('POST', '/customers', $payload);
        $id  = (string) ($res['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Resposta inesperada do ASAAS ao criar o cliente.');
        }
        return $id;
    }

    /**
     * Telefone no formato nacional (DDD + número, só dígitos). Remove o DDI 55
     * e o zero de operadora que o preenchimento automático do celular costuma
     * incluir (ex.: "+55 11 98888-7777" -> "11988887777").
     */
    public static function nationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        // Nenhum DDD começa com 0: um zero inicial é o prefixo de operadora.
        if ((strlen($digits) === 11 || strlen($digits) === 12) && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }
        return $digits;
    }

    /**
     * Cria uma cobrança.
     *
     * @param array{
     *   customer:string,
     *   billingType:string,
     *   value:float,
     *   dueDate:string,
     *   description?:string,
     *   externalReference?:string
     * } $data
     * @return array<string, mixed> Recurso da cobrança retornado pelo ASAAS.
     */
    public function createPayment(array $data): array
    {
        $payload = [
            'customer'    => $data['customer'],
            'billingType' => $data['billingType'],
            'dueDate'     => $data['dueDate'],
        ];
        // Parcelado (2x ou mais): o ASAAS pede installmentCount + totalValue e
        // divide o total entre as parcelas. À vista: só "value" (a doc proíbe
        // os campos de parcelamento em cobrança avulsa).
        $installments = (int) ($data['installmentCount'] ?? 1);
        if ($installments >= 2) {
            $payload['installmentCount'] = $installments;
            $payload['totalValue']       = $data['value'];
        } else {
            $payload['value'] = $data['value'];
        }
        if (!empty($data['description']))       { $payload['description']       = $data['description']; }
        if (!empty($data['externalReference'])) { $payload['externalReference'] = $data['externalReference']; }

        // Checkout transparente de cartão: quando os dados do cartão são
        // enviados junto, o ASAAS processa a transação na hora (sem fatura
        // hospedada). Exige o remoteIp do PAGADOR (não o do servidor).
        $timeout = self::TIMEOUT;
        if (!empty($data['creditCard']) && !empty($data['creditCardHolderInfo'])) {
            $payload['creditCard']           = $data['creditCard'];
            $payload['creditCardHolderInfo'] = $data['creditCardHolderInfo'];
            if (!empty($data['remoteIp'])) {
                $payload['remoteIp'] = $data['remoteIp'];
            }
            // A doc do ASAAS pede no mínimo 60 s quando o cartão é processado
            // (antes eram 15 s: uma resposta lenta virava "recusado" mesmo
            // com a cobrança aprovada, e a pessoa podia pagar de novo).
            $timeout = self::CARD_TIMEOUT;
        }

        return $this->request('POST', '/payments', $payload, $timeout);
    }

    /**
     * Recupera o QR Code do PIX de uma cobrança.
     *
     * @return array{encodedImage:?string, payload:?string, expirationDate:?string}
     */
    public function getPixQrCode(string $paymentId): array
    {
        $res = $this->request('GET', '/payments/' . rawurlencode($paymentId) . '/pixQrCode');
        return [
            'encodedImage'   => isset($res['encodedImage']) ? (string) $res['encodedImage'] : null,
            'payload'        => isset($res['payload']) ? (string) $res['payload'] : null,
            'expirationDate' => isset($res['expirationDate']) ? (string) $res['expirationDate'] : null,
        ];
    }

    /**
     * Consulta uma cobrança pelo id (usado pelo webhook/confirmação de status).
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    // -----------------------------------------------------------------
    //  HTTP
    // -----------------------------------------------------------------

    /**
     * Executa uma requisição autenticada e devolve o corpo decodificado.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws RuntimeException em erro de rede ou status não-2xx.
     */
    private function request(string $method, string $path, ?array $body = null, int $timeout = self::TIMEOUT): array
    {
        $url  = $this->baseUrl . $path;
        $json = $body !== null
            ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        if ($body !== null && $json === false) {
            throw new RuntimeException('Falha ao serializar os dados para o ASAAS.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL do PHP é necessária para o ASAAS.');
        }

        $ch = $this->handle($method, $url, $json, $timeout);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[Asaas] cURL: ' . $err);
            throw new RuntimeException('Falha de comunicação com o ASAAS. Tente novamente.');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($status < 200 || $status >= 300) {
            // O código HTTP vai no código da exceção: 400 = erro de validação
            // dos dados do pagador (mensagem segura para mostrar ao visitante).
            throw new RuntimeException($this->describeError($status, $decoded), $status);
        }

        return $decoded;
    }

    /** Monta um handle cURL autenticado (usado em request() e no simulador). */
    private function handle(string $method, string $url, ?string $json, int $timeout): \CurlHandle
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com o ASAAS.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => [
                'access_token: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: site-casamento-on',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        return $ch;
    }

    /**
     * Simulador de vendas do ASAAS (POST /payments/simulate) para cartão de
     * crédito, em paralelo para várias quantidades de parcelas. Usa as taxas
     * REAIS da conta (não uma tabela fixa).
     *
     * @param array<int, int> $counts quantidades de parcelas (1..N)
     * @return array<int, array{net:float, pct:?float}> parcelas => valor
     *         líquido total de uma venda de $value reais e a taxa percentual
     * @throws RuntimeException se alguma simulação falhar
     */
    public function simulateCard(float $value, array $counts): array
    {
        if (!function_exists('curl_multi_init')) {
            throw new RuntimeException('A extensão cURL do PHP é necessária para o ASAAS.');
        }

        $out   = [];
        $error = null;
        // Poucas requisições por vez (respeita os limites de uso da API) e uma
        // nova tentativa para as que falharem.
        $pending = array_values($counts);
        for ($attempt = 0; $attempt < 2 && $pending !== []; $attempt++) {
            $failed = [];
            foreach (array_chunk($pending, 4) as $chunk) {
                $multi   = curl_multi_init();
                $handles = [];
                foreach ($chunk as $n) {
                    $body = ['value' => round($value, 2), 'billingTypes' => ['CREDIT_CARD']];
                    if ($n > 1) {
                        $body['installmentCount'] = $n;
                    }
                    $ch = $this->handle('POST', $this->baseUrl . '/payments/simulate', (string) json_encode($body), self::TIMEOUT);
                    curl_multi_add_handle($multi, $ch);
                    $handles[$n] = $ch;
                }

                do {
                    $st = curl_multi_exec($multi, $running);
                    if ($running > 0) {
                        curl_multi_select($multi, 1.0);
                    }
                } while ($running > 0 && $st === CURLM_OK);

                foreach ($handles as $n => $ch) {
                    $raw    = curl_multi_getcontent($ch);
                    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                    curl_multi_remove_handle($multi, $ch);

                    $res = json_decode((string) $raw, true);
                    $cc  = is_array($res) && is_array($res['creditCard'] ?? null) ? $res['creditCard'] : null;
                    $net = null;
                    if ($status >= 200 && $status < 300 && $cc !== null) {
                        if (isset($cc['netValue'])) {
                            $net = (float) $cc['netValue'];
                        } elseif (isset($cc['installment']['paymentNetValue'])) {
                            $net = (float) $cc['installment']['paymentNetValue'] * $n;
                        }
                    }
                    if ($net === null) {
                        $failed[] = $n;
                        $error = 'simulação ' . $n . 'x: HTTP ' . $status . ' ' . mb_substr((string) $raw, 0, 200);
                        continue;
                    }
                    $out[$n] = [
                        'net' => $net,
                        'pct' => isset($cc['feePercentage']) && is_numeric($cc['feePercentage'])
                            ? (float) $cc['feePercentage'] : null,
                    ];
                }
                curl_multi_close($multi);
            }
            $pending = $failed;
        }

        if ($pending !== []) {
            error_log('[Asaas] simulate: ' . $error);
            throw new RuntimeException('Não foi possível consultar as taxas do cartão no ASAAS.');
        }
        return $out;
    }

    /** Ambiente da conta (sandbox/production), usado como chave de cache. */
    public function environment(): string
    {
        return str_contains($this->baseUrl, 'sandbox') ? 'sandbox' : 'production';
    }

    /**
     * Converte uma resposta de erro do ASAAS numa mensagem legível.
     *
     * @param array<string, mixed> $decoded
     */
    private function describeError(int $status, array $decoded): string
    {
        // O ASAAS devolve { "errors": [ { "code": "...", "description": "..." } ] }.
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $parts = [];
            foreach ($decoded['errors'] as $e) {
                $desc = is_array($e) ? (string) ($e['description'] ?? '') : (string) $e;
                if ($desc !== '') {
                    $parts[] = $desc;
                }
            }
            if ($parts !== []) {
                return 'ASAAS: ' . implode(' ', $parts);
            }
        }

        if ($status === 401) {
            return 'ASAAS: chave de API inválida ou do ambiente errado (HTTP 401).';
        }

        return 'ASAAS retornou um erro (HTTP ' . $status . ').';
    }
}
