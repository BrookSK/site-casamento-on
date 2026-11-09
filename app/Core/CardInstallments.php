<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Parcelamento no cartão com a taxa repassada a quem presenteia.
 *
 * O casal precisa receber o valor cheio do presente. Para cada quantidade de
 * parcelas, calculamos o total a cobrar no cartão de modo que, descontadas as
 * taxas do ASAAS (as da própria conta, obtidas pelo simulador de vendas), o
 * valor líquido seja pelo menos o preço do presente.
 *
 * Modelo: o líquido de uma venda de T reais em N parcelas é linear em T
 * (líquido = a·T − b: "a" vem da taxa percentual e "b" da taxa fixa). Medimos
 * a e b com o simulador (taxa percentual informada + uma venda de R$ 10.000)
 * e guardamos em cache. Na hora de cobrar, o total escolhido é conferido de
 * novo no simulador.
 */
final class CardInstallments
{
    /**
     * Máximo de parcelas oferecido. O ASAAS aceita até 21x em Visa/Mastercard,
     * mas só 12x nas demais bandeiras (Elo, Amex, Hipercard...). Como não dá
     * para saber a bandeira com segurança só pelo número, usamos o limite que
     * vale para todas.
     */
    public const MAX = 12;

    /** Valor mínimo de cada parcela (proteção contra parcelas irrisórias). */
    public const MIN_INSTALLMENT_CENTS = 500;

    /** Validade do cache das taxas (segundos). */
    private const CACHE_TTL = 6 * 3600;

    private const SAMPLE_LOW  = 100.0;
    private const SAMPLE_HIGH = 10000.0;

    /**
     * Opções de parcelamento para um presente.
     *
     * @return array<int, array{n:int, total_cents:int, per_cents:int, net_cents:int}>
     *         indexadas pelo número de parcelas
     * @throws RuntimeException se as taxas não puderem ser obtidas
     */
    public static function options(Asaas $asaas, int $priceCents, array $overrides = []): array
    {
        $coef = self::coefficients($asaas);
        $out  = [];
        foreach ($coef as $n => [$a, $b]) {
            // Folga de 1 centavo por parcela: o ASAAS arredonda cada parcela
            // e isso pode tirar centavos do líquido. Com a folga, o total
            // mostrado já passa na conferência e é exatamente o cobrado
            // (custa a quem presenteia no máximo alguns centavos a mais).
            $total = self::totalFor($priceCents, (float) $a, (float) $b) + ($n > 1 ? $n : 0);
            // Total corrigido numa conferência anterior (ver verify()).
            if (isset($overrides[$n]) && (int) $overrides[$n] > $total) {
                $total = (int) $overrides[$n];
            }
            $per   = intdiv($total, $n);
            if ($n > 1 && $per < self::MIN_INSTALLMENT_CENTS) {
                continue;
            }
            $out[$n] = [
                'n'           => $n,
                'total_cents' => $total,
                'per_cents'   => $per,
                'net_cents'   => (int) floor(((float) $a * $total / 100 - (float) $b) * 100),
            ];
        }
        return $out;
    }

    /**
     * Confere no simulador do ASAAS se cobrar $totalCents em N parcelas deixa
     * o líquido >= preço. Se faltar algum centavo, devolve o menor total que
     * cobre (o chamador decide: nunca cobramos mais do que a pessoa viu).
     *
     * @return array{total_cents:int, net_cents:int}
     * @throws RuntimeException
     */
    public static function verify(Asaas $asaas, int $priceCents, int $n, int $totalCents): array
    {
        $coef = self::coefficients($asaas);
        if (!isset($coef[$n])) {
            throw new RuntimeException('Quantidade de parcelas indisponível.');
        }
        [$a] = $coef[$n];

        $total = $totalCents;
        $net   = 0;
        for ($i = 0; $i < 4; $i++) {
            $net = (int) floor($asaas->simulateCard($total / 100, [$n])[$n]['net'] * 100 + 0.0001);
            if ($net >= $priceCents) {
                return ['total_cents' => $total, 'net_cents' => $net];
            }
            // Faltaram centavos: sobe o total o suficiente e confere de novo.
            $total += max(1, (int) ceil(($priceCents - $net) / max(0.01, (float) $a)));
        }
        throw new RuntimeException('Não foi possível fechar o valor das parcelas (líquido ' . $net . ' < ' . $priceCents . ').');
    }

    /** Menor total (centavos) cujo líquido a·T − b cobre o preço. */
    private static function totalFor(int $priceCents, float $a, float $b): int
    {
        if ($a <= 0) {
            throw new RuntimeException('Taxa do cartão inválida.');
        }
        $total = (int) ceil((($priceCents / 100 + $b) / $a) * 100 - 0.0001);
        return max($priceCents, $total);
    }

    /**
     * Coeficientes (a, b) do líquido por quantidade de parcelas, com cache em
     * arquivo (storage/cache), separado por ambiente do ASAAS.
     *
     * @return array<int, array{0:float, 1:float}>
     */
    private static function coefficients(Asaas $asaas): array
    {
        static $memo = [];
        $env = $asaas->environment();
        if (isset($memo[$env])) {
            return $memo[$env];
        }

        $file = defined('BASE_PATH') ? BASE_PATH . '/storage/cache/asaas_card_fees_' . $env . '.json' : '';
        if ($file !== '' && is_file($file) && filemtime($file) > time() - self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && count($cached) === self::MAX) {
                $coef = [];
                foreach ($cached as $n => $pair) {
                    $coef[(int) $n] = [(float) $pair[0], (float) $pair[1]];
                }
                return $memo[$env] = $coef;
            }
        }

        $counts = range(1, self::MAX);
        $high   = $asaas->simulateCard(self::SAMPLE_HIGH, $counts);
        // A taxa percentual vem na resposta (feePercentage). Se não vier,
        // estima pela diferença entre duas vendas (R$ 100 e R$ 10.000).
        $low = null;
        foreach ($counts as $n) {
            if ($high[$n]['pct'] === null) {
                $low = $asaas->simulateCard(self::SAMPLE_LOW, $counts);
                break;
            }
        }

        $coef = [];
        foreach ($counts as $n) {
            $a = $low === null
                ? 1 - ((float) $high[$n]['pct']) / 100
                : ($high[$n]['net'] - $low[$n]['net']) / (self::SAMPLE_HIGH - self::SAMPLE_LOW);
            // Parte fixa (taxa por transação + arredondamentos).
            $b = $a * self::SAMPLE_HIGH - $high[$n]['net'];
            if ($a <= 0 || $a > 1) {
                throw new RuntimeException('Resposta inesperada do simulador do ASAAS para ' . $n . 'x.');
            }
            $coef[$n] = [round($a, 8), round(max(0.0, $b), 4)];
        }

        if ($file !== '') {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($file, (string) json_encode($coef), LOCK_EX);
        }
        return $memo[$env] = $coef;
    }
}
