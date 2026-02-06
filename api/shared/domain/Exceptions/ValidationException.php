<?php
declare(strict_types=1);

final class ValidationException extends DomainException
{
    protected string $errorCode = 'validation_failed';
    protected int $statusCode = 422;

    public function __construct(array|string $errors)
    {
        parent::__construct('Validation failed');
        $this->context = is_array($errors) ? $errors : ['error' => $errors];
    }
}
