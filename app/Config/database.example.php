<?php

declare(strict_types=1);

/**
 * MODELO de configuração do banco de dados.
 *
 * COMO USAR:
 * 1. Copie este arquivo para "database.php" (no mesmo diretório).
 * 2. Preencha com as credenciais reais do seu banco (ex.: as geradas no Plesk).
 * 3. NUNCA versione o database.php (ele já está no .gitignore).
 *
 * Este arquivo .example PODE ir para o repositório, pois não contém segredos.
 */

return [
    'driver'   => 'mysql',       // mysql | mariadb (ambos usam o driver mysql do PDO)
    'host'     => 'localhost',   // Host do banco (no Plesk, geralmente localhost)
    'port'     => 3306,          // Porta padrão do MySQL/MariaDB
    'database' => 'nome_do_banco',   // Nome do banco criado no painel
    'username' => 'usuario_do_banco',// Usuário do banco
    'password' => 'senha_do_banco',  // Senha do banco
    'charset'  => 'utf8mb4',     // Suporta acentos e emojis
];
