<?php
declare(strict_types=1);

namespace Shared\Application\Actions\User;

use Shared\Application\Actions\ActionInterface;
use Shared\Application\DTO\CreateUserDTO;
use Shared\Application\Context\RequestContext;
use Shared\Domain\Exceptions\DomainException;

final class CreateUserAction implements ActionInterface
{
    public function __construct()
    {
        // لا شيء هنا الآن
    }

    public function execute(RequestContext $context): array
    {
        /** @var CreateUserDTO $dto */
        $dto = $context->getDTO();

        // مؤقتًا: محاكاة إنشاء مستخدم
        return [
            'id' => 1,
            'email' => $dto->email,
            'name' => $dto->name,
            'status' => 'created'
        ];
    }
}
