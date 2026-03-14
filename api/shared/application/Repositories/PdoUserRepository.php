<?php
declare(strict_types=1);

namespace Shared\Infrastructure\Persistence;

use PDO;
use Shared\Application\DTO\CreateUserDTO;
use Shared\Application\Repositories\UserRepositoryInterface;
use Shared\Domain\Exceptions\DatabaseException;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function create(CreateUserDTO $dto): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password, name)
                 VALUES (:email, :password, :name)'
            );

            $stmt->execute([
                'email'    => $dto->email,
                'password' => password_hash($dto->password, PASSWORD_DEFAULT),
                'name'     => $dto->name,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (\Throwable $e) {
            throw new DatabaseException('Failed to create user', 0, $e);
        }
    }
}
