<?php

declare(strict_types=1);

/**
 * Verificação do banco de dados (CLI).
 *
 * Confere se todas as TABELAS e COLUNAS exigidas pelas funcionalidades já
 * foram criadas no banco — útil para flagrar "código novo em banco antigo",
 * em que a migração ainda não foi aplicada (ex.: colunas financeiras de
 * gift_purchases ou o enum de status de gifts).
 *
 * Uso (na raiz do projeto):
 *     php scripts/check_db.php
 *
 * Saída: lista o que está OK e o que está FALTANDO, com a migração sugerida.
 * Código de saída 0 quando tudo está presente; 1 quando falta algo; 2 em erro
 * de conexão/configuração. Não altera nada no banco (somente leitura).
 */

// ------------------------------------------------------------------
//  Bootstrap mínimo (sem HTTP): constantes + autoloader + config.
// ------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Database;

// ------------------------------------------------------------------
//  Esquema esperado: tabela => [colunas obrigatórias], com a migração
//  responsável por criá-la (apenas para orientar o operador).
// ------------------------------------------------------------------
$expected = [
    'guests' => [
        'migration' => 'database/schema.sql (+ 2026_10_13_guests_account.sql)',
        'columns'   => ['id', 'name', 'email', 'phone', 'password_hash', 'invite_token'],
    ],
    'rsvps' => [
        'migration' => 'database/schema.sql',
        'columns'   => ['id', 'guest_id', 'status', 'companions', 'message'],
    ],
    'rsvp_companions' => [
        'migration' => 'database/migrations/2026_10_12_rsvp_companions.sql',
        'columns'   => ['id', 'rsvp_id', 'name'],
    ],
    'gifts' => [
        'migration' => 'database/schema.sql',
        'columns'   => ['id', 'name', 'description', 'price_cents', 'image', 'status', 'sort_order'],
    ],
    'gift_purchases' => [
        'migration' => 'database/migrations/2026_10_13_asaas_payments.sql + 2026_10_13_gift_finance.sql',
        'columns'   => [
            'id', 'gift_id', 'guest_id', 'buyer_name', 'buyer_email', 'amount_cents',
            // Financeiro (ponto 11): snapshot do preço, taxas, líquido e datas.
            'gift_price_cents', 'fee_cents', 'net_cents',
            'paid_at', 'confirmed_at', 'settled_at', 'refunded_at',
            'payment_status', 'finance_status',
            // Integração ASAAS.
            'payment_ref', 'asaas_customer_id', 'billing_type',
            'invoice_url', 'bank_slip_url', 'pix_payload', 'pix_qr_base64',
            'message', 'created_at',
        ],
    ],
    'gift_purchase_status_log' => [
        'migration' => 'database/migrations/2026_10_13_gift_finance.sql',
        'columns'   => ['id', 'purchase_id', 'from_status', 'to_status', 'note', 'created_at'],
    ],
    'asaas_webhook_events' => [
        'migration' => 'database/migrations/2026_10_13_asaas_payments.sql',
        'columns'   => ['id', 'event_id', 'event_type', 'payment_id', 'received_at'],
    ],
    'communication_logs' => [
        'migration' => 'database/migrations/2026_10_14_communication.sql',
        'columns'   => ['id', 'guest_id', 'channel', 'type', 'recipient', 'status', 'error'],
    ],
    'albums' => [
        'migration' => 'database/migrations/2026_10_14_gallery_albums.sql',
        'columns'   => ['id', 'title', 'sort_order'],
    ],
    'album_photos' => [
        'migration' => 'database/migrations/2026_10_14_gallery_albums.sql',
        'columns'   => ['id', 'album_id', 'image', 'sort_order'],
    ],
    'settings' => [
        'migration' => 'database/schema.sql',
        'columns'   => ['id', 'key_name', 'value'],
    ],
];

// ------------------------------------------------------------------
//  Helpers de saída (ANSI quando o terminal suportar).
// ------------------------------------------------------------------
$supportsColor = (DIRECTORY_SEPARATOR === '/') || getenv('ANSICON') !== false || getenv('WT_SESSION') !== false;
$green = $supportsColor ? "\033[32m" : '';
$red   = $supportsColor ? "\033[31m" : '';
$yellow= $supportsColor ? "\033[33m" : '';
$reset = $supportsColor ? "\033[0m"  : '';

$ok   = static fn (string $s): string => $green . $s . $reset;
$bad  = static fn (string $s): string => $red . $s . $reset;
$warn = static fn (string $s): string => $yellow . $s . $reset;

echo "== Verificação do banco de dados ==\n\n";

// ------------------------------------------------------------------
//  Conexão.
// ------------------------------------------------------------------
try {
    $pdo = Database::connection();
    if (!Database::test()) {
        fwrite(STDERR, $bad("Falha: o banco respondeu de forma inesperada.\n"));
        exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $bad('Falha ao conectar: ') . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifique app/Config/database.php.\n");
    exit(2);
}

// ------------------------------------------------------------------
//  Introspecção: tabelas e colunas existentes.
// ------------------------------------------------------------------
/** @return array<int,string> */
$listTables = static function (\PDO $pdo): array {
    $rows = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    return array_map('strval', $rows);
};

/** @return array<int,string> */
$listColumns = static function (\PDO $pdo, string $table): array {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    $stmt->execute();
    return array_map(static fn ($r): string => (string) $r['Field'], $stmt->fetchAll());
};

$existingTables = $listTables($pdo);
$missingAnything = false;

foreach ($expected as $table => $spec) {
    if (!in_array($table, $existingTables, true)) {
        $missingAnything = true;
        echo $bad('[FALTA TABELA] ') . $table . "\n";
        echo '    aplique: ' . $spec['migration'] . "\n\n";
        continue;
    }

    $cols        = $listColumns($pdo, $table);
    $missingCols = array_values(array_diff($spec['columns'], $cols));

    if ($missingCols === []) {
        echo $ok('[OK] ') . $table . ' (' . count($spec['columns']) . " colunas conferidas)\n";
    } else {
        $missingAnything = true;
        echo $bad('[FALTAM COLUNAS] ') . $table . ': ' . implode(', ', $missingCols) . "\n";
        echo '    aplique: ' . $spec['migration'] . "\n";
    }
}

echo "\n";

if ($missingAnything) {
    echo $bad("Resultado: há itens faltando no banco. ")
        . "Rode as migrações indicadas (phpMyAdmin > Importar/SQL) e verifique de novo.\n";
    exit(1);
}

echo $ok("Resultado: banco em dia. Todas as tabelas e colunas esperadas estão presentes.\n");
exit(0);
