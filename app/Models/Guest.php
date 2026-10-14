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
}
