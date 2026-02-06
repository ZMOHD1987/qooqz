<?php
declare(strict_types=1);

abstract class DomainException extends RuntimeException
{
    protected int $statusCode = 400;
    protected array $context = [];

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
