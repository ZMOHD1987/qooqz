<?php
declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\MySQL;

use PDO;
use Shared\Application\Repositories\UserRepositoryInterface;
use Shared\Application\DTO\CreateUserDTO;
use Shared\Domain\Exceptions\DatabaseException;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function create(CreateUserDTO $dto): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password, name) VALUES (:email, :password, :name)'
        );

        if (!$stmt->execute([
            'email'    => $dto->email,
            'password' => password_hash($dto->password, PASSWORD_BCRYPT),
            'name'     => $dto->name
        ])) {
            throw new DatabaseException('User insert failed');
        }

        return (int)$this->pdo->lastInsertId();
    }
}
