<?php
declare(strict_types=1);

namespace Shared\Application\Repositories;

use Shared\Application\DTO\CreateUserDTO;

interface UserRepositoryInterface
{
    public function create(CreateUserDTO $dto): int;
}
