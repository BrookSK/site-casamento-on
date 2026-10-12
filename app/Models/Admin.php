<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model dos administradores do painel.
 *
 * Responsável por buscar admins e validar credenciais. Nunca expõe o hash
 * da senha para fora do processo de verificação.
 */
final class Admin extends Model
{
    protected string $table = 'admins';

    /**
     * Busca um admin pelo e-mail.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
    }

    /**
     * Verifica e-mail + senha. Retorna os dados do admin (sem o hash) em caso
     * de sucesso, ou null caso contrário.
     *
     * @return array{id:int, name:string, email:string}|null
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $admin = $this->findByEmail($email);

        if ($admin === null || empty($admin['password_hash'])) {
            // password_verify com hash falso para custo de tempo semelhante
            // (dificulta descobrir se o e-mail existe por tempo de resposta).
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');
            return null;
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            return null;
        }

        return [
            'id'    => (int) $admin['id'],
            'name'  => (string) $admin['name'],
            'email' => (string) $admin['email'],
        ];
    }

    /**
     * Cria um admin com senha já segura (hash bcrypt).
     * Usado pelo script de criação do primeiro admin.
     */
    public function create(string $name, string $email, string $plainPassword): int
    {
        $this->execute(
            'INSERT INTO ' . $this->table . ' (name, email, password_hash) '
            . 'VALUES (:name, :email, :hash)',
            [
                'name'  => $name,
                'email' => $email,
                'hash'  => password_hash($plainPassword, PASSWORD_DEFAULT),
            ]
        );

        return (int) $this->lastInsertId();
    }
}
