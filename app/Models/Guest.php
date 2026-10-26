<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model dos convidados (tabela guests) — contas de acesso.
 *
 * Login por e-mail OU telefone. O telefone é normalizado (só dígitos) e
 * guardado em phone_normalized, usado como identificador único de login.
 */
final class Guest extends Model
{
    protected string $table = 'guests';

    /** Normaliza um telefone para apenas dígitos. Ex.: (11) 9...-... -> 11988887777 */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Normaliza um CPF para apenas dígitos. Pontos, hífens, espaços e quaisquer
     * outros caracteres de formatação são descartados — a busca pelo registro
     * usa somente os números. Ex.: "123.456.789-01" -> "12345678901".
     */
    public static function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    /**
     * Localiza um convidado pelo CPF (somente dígitos). Usado no convite
     * público para encontrar/associar a confirmação à pessoa certa.
     *
     * @return array<string,mixed>|null
     */
    public function findByCpf(string $cpfNormalized): ?array
    {
        if ($cpfNormalized === '') {
            return null;
        }
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE cpf_normalized = :c LIMIT 1',
            ['c' => $cpfNormalized]
        );
    }

    /** Busca por e-mail (exato). @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE email = :e LIMIT 1',
            ['e' => $email]
        );
    }

    /** Busca por telefone normalizado. @return array<string,mixed>|null */
    public function findByPhone(string $phoneNormalized): ?array
    {
        if ($phoneNormalized === '') {
            return null;
        }
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE phone_normalized = :p LIMIT 1',
            ['p' => $phoneNormalized]
        );
    }

    /**
     * Gera um token de convite/definição de senha (32 hex) seguro.
     * Usado nos links enviados por e-mail/WhatsApp após a confirmação.
     */
    public static function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16)); // 32 caracteres hex
        } catch (\Throwable $e) {
            // Fallback improvável (random_bytes indisponível).
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    /**
     * Busca um convidado por e-mail OU telefone (login/recuperação).
     * @return array<string,mixed>|null
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        if (str_contains($identifier, '@')) {
            return $this->findByEmail($identifier);
        }
        return $this->findByPhone(self::normalizePhone($identifier));
    }

    /**
     * Localiza um convidado por um token de convite VÁLIDO (não expirado).
     * Usado no link seguro de 1ª definição de senha enviado após o RSVP.
     *
     * @return array<string,mixed>|null
     */
    public function findByInviteToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE invite_token = :t '
            . 'AND (invite_token_expires_at IS NULL OR invite_token_expires_at > NOW()) LIMIT 1',
            ['t' => $token]
        );
    }

    /**
     * Localiza um convidado por um token de RECUPERAÇÃO de senha válido.
     *
     * @return array<string,mixed>|null
     */
    public function findByResetToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE password_reset_token = :t '
            . 'AND password_reset_expires_at IS NOT NULL AND password_reset_expires_at > NOW() LIMIT 1',
            ['t' => $token]
        );
    }

    /**
     * Gera e grava um token de recuperação de senha (validade em horas).
     * Retorna o token em claro para montar o link enviado ao convidado.
     */
    public function startPasswordReset(int $id, int $validHours = 2): string
    {
        $token = self::generateToken();
        $this->execute(
            'UPDATE ' . $this->table . ' SET password_reset_token = :t, '
            . 'password_reset_expires_at = DATE_ADD(NOW(), INTERVAL :h HOUR) WHERE id = :id',
            ['t' => $token, 'h' => $validHours, 'id' => $id]
        );
        return $token;
    }

    /**
     * Define a senha do convidado e invalida AMBOS os tokens (convite e
     * recuperação), encerrando o link usado. Hash sempre via password_hash.
     */
    public function setPassword(int $id, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->execute(
            'UPDATE ' . $this->table . ' SET password_hash = :hash, '
            . 'invite_token = NULL, invite_token_expires_at = NULL, '
            . 'password_reset_token = NULL, password_reset_expires_at = NULL, '
            . 'account_created_at = COALESCE(account_created_at, NOW()) WHERE id = :id',
            ['hash' => $hash, 'id' => $id]
        );
    }

    /**
     * Verifica credenciais: o identificador pode ser e-mail ou telefone.
     *
     * @return array{id:int,name:string,email:string}|null
     */
    public function verifyLogin(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);

        // Decide se é e-mail ou telefone pelo conteúdo.
        if (str_contains($identifier, '@')) {
            $guest = $this->findByEmail($identifier);
        } else {
            $guest = $this->findByPhone(self::normalizePhone($identifier));
        }

        if ($guest === null || empty($guest['password_hash'])) {
            // Custo de tempo semelhante ao de um hash real (dificulta enumeração).
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');
            return null;
        }

        if (!password_verify($password, (string) $guest['password_hash'])) {
            return null;
        }

        return [
            'id'    => (int) $guest['id'],
            'name'  => (string) $guest['name'],
            'email' => (string) ($guest['email'] ?? ''),
        ];
    }

    /**
     * Cria uma conta nova OU vincula a senha a um convidado existente
     * (quando o e-mail ou telefone já existe de um RSVP anterior).
     *
     * @param array{name:string,email:string,phone:string,password:string} $data
     * @return array{id:int,name:string,email:string}  Dados para login
     * @throws \RuntimeException  Em conflito de identidade entre dois registros.
     */
    public function register(array $data): array
    {
        $name  = mb_substr(trim($data['name']), 0, 150);
        $email = trim($data['email']);
        $phoneDisplay = trim($data['phone']);
        $phoneNorm    = self::normalizePhone($phoneDisplay);
        $hash  = password_hash($data['password'], PASSWORD_DEFAULT);

        $byEmail = $email !== '' ? $this->findByEmail($email) : null;
        $byPhone = $phoneNorm !== '' ? $this->findByPhone($phoneNorm) : null;

        // Se e-mail e telefone apontam para registros DIFERENTES, é ambíguo.
        if ($byEmail !== null && $byPhone !== null && (int) $byEmail['id'] !== (int) $byPhone['id']) {
            throw new \RuntimeException('conflict');
        }

        $existing = $byEmail ?? $byPhone;

        if ($existing !== null) {
            // Já tem conta com senha? Então não dá para "criar" de novo.
            if (!empty($existing['password_hash'])) {
                throw new \RuntimeException('exists');
            }
            // Vincula: define senha e completa dados que faltam.
            $this->execute(
                'UPDATE ' . $this->table . ' SET '
                . 'name = :name, email = :email, phone = :phone, '
                . 'phone_normalized = :pnorm, password_hash = :hash, '
                . 'account_created_at = NOW() WHERE id = :id',
                [
                    'id'    => (int) $existing['id'],
                    'name'  => $name,
                    'email' => $email !== '' ? $email : ($existing['email'] ?? null),
                    'phone' => $phoneDisplay !== '' ? $phoneDisplay : ($existing['phone'] ?? null),
                    'pnorm' => $phoneNorm !== '' ? $phoneNorm : null,
                    'hash'  => $hash,
                ]
            );
            $id = (int) $existing['id'];
        } else {
            $this->execute(
                'INSERT INTO ' . $this->table
                . ' (name, email, phone, phone_normalized, password_hash, account_created_at) '
                . 'VALUES (:name, :email, :phone, :pnorm, :hash, NOW())',
                [
                    'name'  => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phoneDisplay !== '' ? $phoneDisplay : null,
                    'pnorm' => $phoneNorm !== '' ? $phoneNorm : null,
                    'hash'  => $hash,
                ]
            );
            $id = (int) $this->lastInsertId();
        }

        return ['id' => $id, 'name' => $name, 'email' => $email];
    }

    /**
     * Dados do perfil + status da confirmação de presença (se houver).
     *
     * @return array<string,mixed>|null
     */
    public function profile(int $id): ?array
    {
        return $this->selectOne(
            'SELECT g.id, g.name, g.email, g.phone, '
            . 'r.status AS rsvp_status, r.companions, r.responded_at '
            . 'FROM guests g '
            . 'LEFT JOIN rsvps r ON r.guest_id = g.id '
            . 'WHERE g.id = :id '
            . 'ORDER BY r.id DESC LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Localiza OU cria automaticamente a conta de quem está presenteando,
     * reaproveitando a MESMA lógica da confirmação de presença (ponto 6):
     *
     *   - Convidado novo: cria em `guests` já com um `invite_token` (7 dias),
     *     base do link seguro de 1ª definição de senha, e `account_created_at`.
     *   - Convidado existente SEM senha: completa dados que faltam e garante um
     *     `invite_token` novo (link válido agora).
     *   - Convidado existente COM senha (conta ativa): preserva a conta; apenas
     *     completa contatos faltantes. Não gera token (não reenvia link).
     *
     * O retorno usa o mesmo formato do RSVP, para reaproveitar o envio de
     * boas-vindas/definição de senha (Notifier::sendWelcome).
     *
     * @param array{name:string, email:?string, phone:?string} $data
     *
     * @return array{
     *   guest:array{id:int,name:string,email:string},
     *   invite_token:?string,
     *   has_password:bool
     * }
     * @throws \RuntimeException 'conflict' quando e-mail e telefone apontam
     *                           para convidados DIFERENTES (identidade ambígua).
     */
    public function findOrCreateForPurchase(array $data): array
    {
        $name         = mb_substr(trim((string) $data['name']), 0, 150);
        $email        = trim((string) ($data['email'] ?? ''));
        $email        = $email !== '' ? $email : null;
        $phoneDisplay = trim((string) ($data['phone'] ?? ''));
        $phoneDisplay = $phoneDisplay !== '' ? $phoneDisplay : null;
        $phoneNorm    = $phoneDisplay !== null ? self::normalizePhone($phoneDisplay) : '';
        $phoneNorm    = $phoneNorm !== '' ? $phoneNorm : null;

        $byEmail = $email !== null ? $this->findByEmail($email) : null;
        $byPhone = $phoneNorm !== null ? $this->findByPhone($phoneNorm) : null;

        // E-mail e telefone apontando para registros diferentes: ambíguo.
        if ($byEmail !== null && $byPhone !== null && (int) $byEmail['id'] !== (int) $byPhone['id']) {
            throw new \RuntimeException('conflict');
        }

        $existing    = $byEmail ?? $byPhone;
        $inviteToken = null;
        $hasPassword = false;

        if ($existing === null) {
            // Convidado novo: cria já com token de convite (7 dias).
            $inviteToken = self::generateToken();
            $this->execute(
                'INSERT INTO ' . $this->table
                . ' (name, email, phone, phone_normalized, invite_token, '
                . 'invite_token_expires_at, account_created_at) '
                . 'VALUES (:name, :email, :phone, :pnorm, :token, '
                . 'DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())',
                [
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phoneDisplay,
                    'pnorm' => $phoneNorm,
                    'token' => $inviteToken,
                ]
            );
            $id         = (int) $this->lastInsertId();
            $finalEmail = (string) ($email ?? '');
        } else {
            $id          = (int) $existing['id'];
            $hasPassword = !empty($existing['password_hash']);

            // Completa dados que faltam, sem sobrescrever o que já existe.
            $newEmail = ($existing['email'] ?? null) ?: $email;
            $newPhone = ($existing['phone'] ?? null) ?: $phoneDisplay;
            $newPnorm = ($existing['phone_normalized'] ?? null) ?: $phoneNorm;

            if ($hasPassword) {
                // Conta ativa: não gera token; só atualiza nome/contatos.
                $this->execute(
                    'UPDATE ' . $this->table . ' SET name = :name, email = :email, '
                    . 'phone = :phone, phone_normalized = :pnorm WHERE id = :id',
                    [
                        'id'    => $id,
                        'name'  => $name,
                        'email' => $newEmail,
                        'phone' => $newPhone,
                        'pnorm' => $newPnorm,
                    ]
                );
            } else {
                // Sem senha: gera um token novo (7 dias), garantindo link válido.
                $inviteToken = self::generateToken();
                $this->execute(
                    'UPDATE ' . $this->table . ' SET name = :name, email = :email, '
                    . 'phone = :phone, phone_normalized = :pnorm, '
                    . 'invite_token = :token, '
                    . 'invite_token_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), '
                    . 'account_created_at = COALESCE(account_created_at, NOW()) '
                    . 'WHERE id = :id',
                    [
                        'id'    => $id,
                        'name'  => $name,
                        'email' => $newEmail,
                        'phone' => $newPhone,
                        'pnorm' => $newPnorm,
                        'token' => $inviteToken,
                    ]
                );
            }
            $finalEmail = (string) ($newEmail ?? '');
        }

        return [
            'guest'        => ['id' => $id, 'name' => $name, 'email' => $finalEmail],
            'invite_token' => $inviteToken,
            'has_password' => $hasPassword,
        ];
    }
}
