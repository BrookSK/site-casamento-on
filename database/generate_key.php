<?php

declare(strict_types=1);

/**
 * Gera a chave de aplicação usada para cifrar segredos (SMTP, ASAAS).
 *
 * Rode pela LINHA DE COMANDO:
 *   php database/generate_key.php
 *
 * Cria app/Config/app_key.php se ainda não existir. Esse arquivo NÃO vai
 * para o Git (está no .gitignore). Guarde um backup seguro: perder a chave
 * significa não conseguir decifrar os segredos já salvos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado pela linha de comando.\n");
}

$target = dirname(__DIR__) . '/app/Config/app_key.php';

if (is_file($target)) {
    exit("[AVISO] A chave já existe em app/Config/app_key.php. Nada a fazer.\n");
}

$key = base64_encode(random_bytes(32));
$content = "<?php\n\ndeclare(strict_types=1);\n\n"
    . "// Chave de aplicação (32 bytes, base64). NÃO versionar. NÃO compartilhar.\n"
    . "return '" . $key . "';\n";

if (file_put_contents($target, $content) === false) {
    exit("[ERRO] Não foi possível escrever em {$target}. Verifique as permissões.\n");
}

echo "[OK] Chave gerada em app/Config/app_key.php\n";
