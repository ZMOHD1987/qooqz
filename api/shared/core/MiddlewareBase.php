<?php
declare(strict_types=1);

/**
 * Base class for all middleware
 * Framework-grade, production ready
 */
abstract class MiddlewareBase
{
    protected array $context;

    final public function __construct(array $context = [])
    {
        $this->context = $context;
    }

    /**
     * Middleware handler
     *
     * @param array    $request  Normalized request data
     * @param callable $next     Next middleware
     */
    abstract public function handle(array $request, callable $next): mixed;

    /* =========================
     * Context helpers
     * ========================= */

    protected function userId(): ?int
    {
        return $this->context['user']['id'] ?? null;
    }

    protected function tenantId(): ?int
    {
        return $this->context['tenant_id'] ?? null;
    }

    protected function scope(): string
    {
        return $this->context['scope'] ?? 'public';
    }

    protected function isAuthenticated(): bool
    {
        return $this->userId() !== null;
    }

    /* =========================
     * Infrastructure helpers
     * ========================= */

    protected function logInfo(string $message, array $context = []): void
    {
        if (class_exists('Logger')) {
            Logger::info($message, $context);
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        if (class_exists('Logger')) {
            Logger::error($message, $context);
        }
    }

    protected function cacheGet(string $key): mixed
    {
        return class_exists('CacheManager')
            ? CacheManager::get($key)
            : null;
    }

    protected function cacheSet(string $key, mixed $value, int $ttl = 3600): void
    {
        if (class_exists('CacheManager')) {
            CacheManager::set($key, $value, $ttl);
        }
    }

    /* =========================
     * Guards
     * ========================= */

    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new RuntimeException('Unauthorized');
        }
    }
}
