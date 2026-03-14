<?php
declare(strict_types=1);

namespace Shared\Domain\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected string $errorCode = 'domain_error';
    protected int $statusCode = 400;
    protected array $context = [];

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
