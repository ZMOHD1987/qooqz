<?php
declare(strict_types=1);

namespace Shared\Application\Context;

final class RequestContext
{
    private string $requestId;
    private ?int $tenantId;
    private ?int $userId;
    private array $roles;
    private array $permissions;
    private string $locale;
    private string $timezone;
    private string $ip;
    private array $attributes;

    public function __construct(
        string $requestId,
        ?int $tenantId,
        ?int $userId,
        array $roles,
        array $permissions,
        string $locale,
        string $timezone,
        string $ip,
        array $attributes = []
    ) {
        $this->requestId    = $requestId;
        $this->tenantId     = $tenantId;
        $this->userId       = $userId;
        $this->roles        = $roles;
        $this->permissions  = $permissions;
        $this->locale       = $locale;
        $this->timezone     = $timezone;
        $this->ip           = $ip;
        $this->attributes   = $attributes;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }
}