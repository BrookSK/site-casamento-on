<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model de configurações chave/valor (tabela settings).
 *
 * Usada para dados do casamento e para as configurações de integração
 * (SMTP, ASAAS). Valores sensíveis devem ser cifrados ANTES de salvar
 * (ver App\Core\Crypto) — este model apenas persiste strings.
 */
final class Setting extends Model
{
    protected string $table = 'settings';

    /** Lê um valor por chave (ou o default). */
    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->selectOne(
            'SELECT value FROM ' . $this->table . ' WHERE key_name = :k LIMIT 1',
            ['k' => $key]
        );

        return $row['value'] ?? $default;
    }

    /**
     * Lê várias chaves de uma vez.
     *
     * @param array<int, string> $keys
     * @return array<string, string|null>
     */
    public function getMany(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->select(
            'SELECT key_name, value FROM ' . $this->table
            . ' WHERE key_name IN (' . $placeholders . ')',
            array_values($keys)
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key_name']] = $row['value'];
        }
        return $out;
    }

    /** Cria ou atualiza um valor (upsert). */
    public function set(string $key, ?string $value): void
    {
        $this->execute(
            'INSERT INTO ' . $this->table . ' (key_name, value) VALUES (:k, :v) '
            . 'ON DUPLICATE KEY UPDATE value = :v2',
            ['k' => $key, 'v' => $value, 'v2' => $value]
        );
    }

    /**
     * Define várias chaves de uma vez.
     *
     * @param array<string, string|null> $pairs
     */
    public function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }
}
