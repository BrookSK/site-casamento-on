<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexão com o banco de dados via PDO.
 *
 * Mantém uma única instância de PDO por requisição (padrão singleton leve).
 * As credenciais vêm de app/Config/database.php (fora do repositório).
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** Impede instanciar: use Database::connection(). */
    private function __construct()
    {
    }

    /**
     * Retorna a conexão PDO, criando-a na primeira chamada.
     *
     * @throws RuntimeException Se a configuração não existir ou a conexão falhar.
     */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $configFile = APP_PATH . '/Config/database.php';

        if (!is_file($configFile)) {
            throw new RuntimeException(
                'Configuração do banco não encontrada. Copie '
                . 'app/Config/database.example.php para app/Config/database.php '
                . 'e preencha as credenciais.'
            );
        }

        /** @var array<string, mixed> $config */
        $config = require $configFile;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($config['host'] ?? 'localhost'),
            (int) ($config['port'] ?? 3306),
            (string) ($config['database'] ?? ''),
            (string) ($config['charset'] ?? 'utf8mb4')
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erros viram exceções
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                   // prepares reais (segurança)
        ];

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                $options
            );
        } catch (PDOException $e) {
            // Não expõe detalhes sensíveis; a mensagem técnica fica no log do servidor.
            error_log('[Database] Falha ao conectar: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados.');
        }

        return self::$pdo;
    }

    /**
     * Testa a conexão executando uma consulta trivial.
     * Útil para o script de verificação. Retorna true se o banco responder.
     */
    public static function test(): bool
    {
        return self::connection()->query('SELECT 1')->fetchColumn() === 1;
    }
}
