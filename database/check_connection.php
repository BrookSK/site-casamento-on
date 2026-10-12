<?php

declare(strict_types=1);

/**
 * Verificação de conexão com o banco de dados.
 *
 * Rode pela LINHA DE COMANDO (não pela web):
 *   php database/check_connection.php
 *
 * Ele testa a conexão e lista as tabelas existentes. Útil para confirmar que
 * as credenciais em app/Config/database.php estão corretas e o schema foi
 * importado. Este arquivo fica FORA da pasta public/, então não é acessível
 * pelo navegador.
 */

// Permite rodar só via CLI (segurança: evita execução acidental pela web).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require APP_PATH . '/Core/Database.php';

use App\Core\Database;

echo "Testando conexão com o banco...\n";

try {
    if (Database::test()) {
        echo "[OK] Conexão estabelecida com sucesso.\n";
    } else {
        echo "[ERRO] Conectou, mas o teste SELECT 1 falhou.\n";
        exit(1);
    }

    // Lista as tabelas encontradas.
    $tables = Database::connection()
        ->query('SHOW TABLES')
        ->fetchAll(PDO::FETCH_COLUMN);

    if ($tables === []) {
        echo "[AVISO] Nenhuma tabela encontrada. Importe o database/schema.sql.\n";
    } else {
        echo "Tabelas no banco (" . count($tables) . "):\n";
        foreach ($tables as $t) {
            echo "  - {$t}\n";
        }
    }
} catch (Throwable $e) {
    echo "[ERRO] " . $e->getMessage() . "\n";
    exit(1);
}

echo "Verificação concluída.\n";
