<?php
declare(strict_types=1);

namespace Shared\Application\Actions\User;

use Shared\Application\Actions\ActionInterface;
use Shared\Application\Context\RequestContext;
use Shared\Application\DTO\DeleteUserDTO;
use Shared\Application\Repositories\UserRepositoryInterface;

final class DeleteUserAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function execute(RequestContext $context, object $dto): array
    {
        if (!$dto instanceof DeleteUserDTO) {
            throw new \InvalidArgumentException('Invalid DTO');
        }

        $this->repository->delete($dto);

        return [
            'id'     => $dto->id,
            'status' => 'deleted',
        ];
    }
}
