<?php
declare(strict_types=1);

namespace Shared\Application\DTO;

use Shared\Domain\Exceptions\ValidationException;

final class CreateUserDTO
{
    public string $email;
    public string $password;
    public ?string $name;

    public function __construct(array $data)
    {
        $this->email = trim($data['email'] ?? '');
        $this->password = (string)($data['password'] ?? '');
        $this->name = isset($data['name']) ? trim($data['name']) : null;

        $this->validate();
    }

    private function validate(): void
    {
        if ($this->email === '') {
            throw new ValidationException('Email is required');
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        if (strlen($this->password) < 6) {
            throw new ValidationException('Password must be at least 6 characters');
        }
    }
}
