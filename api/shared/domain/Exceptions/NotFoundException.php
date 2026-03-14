<?php
declare(strict_types=1);

final class NotFoundException extends DomainException
{
    protected string $errorCode = 'not_found';
    protected int $statusCode = 404;
}
