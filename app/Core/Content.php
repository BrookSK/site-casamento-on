<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Fonte de textos editáveis das telas públicas.
 *
 * Lê os textos da tabela settings (editáveis no painel), com fallback para os
 * valores padrão (config) quando ainda não foram definidos. Carrega tudo numa
 * única consulta por requisição.
 */
final class Content
{
    /** @var array<string, string|null> */
    private array $values = [];

    /** Chaves de conteúdo conhecidas (texto). */
    public const TEXT_KEYS = [
        'bride', 'groom', 'hashtag',
        'date_label', 'time_label',
        'history_title', 'history_text',
        'fact1_label', 'fact1_value',
        'fact2_label', 'fact2_value',
        'fact3_label', 'fact3_value',
        'ceremony_place', 'ceremony_address', 'ceremony_details',
        'reception_place', 'reception_address', 'reception_details',
    ];

    /**
     * @param array<string, mixed> $config Config da aplicação
     */
    public function __construct(array $config)
    {
        try {
            $this->values = (new Setting($config))->getMany(self::TEXT_KEYS);
        } catch (Throwable $e) {
            // Sem banco: tudo cai no fallback.
            error_log('[Content] load fallback: ' . $e->getMessage());
            $this->values = [];
        }
    }

    /** Texto editável com fallback. */
    public function text(string $key, string $default = ''): string
    {
        $v = $this->values[$key] ?? null;
        return ($v !== null && $v !== '') ? $v : $default;
    }
}
