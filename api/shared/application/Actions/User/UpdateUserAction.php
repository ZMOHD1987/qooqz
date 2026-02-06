<?php
declare(strict_types=1);

namespace Shared\Application\Actions\User;

use Shared\Application\Actions\ActionInterface;
use Shared\Application\Context\RequestContext;
use Shared\Application\DTO\UpdateUserDTO;
use Shared\Application\Repositories\UserRepositoryInterface;

final class UpdateUserAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function execute(RequestContext $context, object $dto): array
    {
        if (!$dto instanceof UpdateUserDTO) {
            throw new \InvalidArgumentException('Invalid DTO');
        }

        $this->repository->update($dto);

        return [
            'id'     => $dto->id,
            'status' => 'updated',
        ];
    }
}
