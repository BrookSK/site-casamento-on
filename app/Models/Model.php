<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model base.
 *
 * Fornece acesso ao PDO e helpers simples de consulta usando
 * prepared statements (seguros contra SQL injection).
 *
 * Cada Model concreto define a propriedade $table.
 */
abstract class Model
{
    /** Nome da tabela no banco. Definido pelas subclasses. */
    protected string $table = '';

    protected function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Executa um SELECT e retorna todas as linhas.
     *
     * @param array<string, mixed> $params Parâmetros nomeados do prepared statement
     * @return array<int, array<string, mixed>>
     */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Executa um SELECT e retorna a primeira linha (ou null).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Executa INSERT/UPDATE/DELETE e retorna o número de linhas afetadas.
     *
     * @param array<string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Retorna o ID da última linha inserida. */
    protected function lastInsertId(): string
    {
        return $this->db()->lastInsertId();
    }

    /**
     * Retorna todos os registros da tabela do Model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->select('SELECT * FROM ' . $this->table);
    }

    /**
     * Busca um registro por ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}
