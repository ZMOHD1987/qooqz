<?php
declare(strict_types=1);

namespace Shared\Application\DTO;

final class DeleteUserDTO
{
    public int $id;

    public function __construct(array $payload)
    {
        if (empty($payload['id'])) {
            throw new \InvalidArgumentException('User id is required');
        }

        $this->id = (int)$payload['id'];
    }
}
