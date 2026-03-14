<?php
declare(strict_types=1);

final class EventDispatcher
{
    /**
     * [
     *   'event.name' => [
     *       priority => [callable, callable]
     *   ]
     * ]
     */
    private static array $listeners = [];

    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Register listener
     * ========================= */
    public static function listen(string $event, callable $listener, int $priority = 0): void
    {
        self::$listeners[$event][$priority][] = $listener;
    }

    /* =========================
     * Dispatch event
     * ========================= */
    public static function dispatch(string $event, array $payload = []): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }

        // Sort by priority DESC
        krsort(self::$listeners[$event]);

        foreach (self::$listeners[$event] as $priority => $listeners) {
            foreach ($listeners as $listener) {
                try {
                    $result = $listener($payload);

                    // allow stopping propagation
                    if ($result === false) {
                        return;
                    }

                } catch (Throwable $e) {
                    Logger::error(
                        "Event '{$event}' failed: " . $e->getMessage()
                    );
                }
            }
        }
    }

    /* =========================
     * Remove listener
     * ========================= */
    public static function remove(string $event, callable $listener): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }

        foreach (self::$listeners[$event] as $priority => $listeners) {
            foreach ($listeners as $i => $registered) {
                if ($registered === $listener) {
                    unset(self::$listeners[$event][$priority][$i]);
                }
            }

            if (empty(self::$listeners[$event][$priority])) {
                unset(self::$listeners[$event][$priority]);
            }
        }

        if (empty(self::$listeners[$event])) {
            unset(self::$listeners[$event]);
        }
    }

    /* =========================
     * Utilities
     * ========================= */
    public static function has(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    public static function clear(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
        } else {
            unset(self::$listeners[$event]);
        }
    }

    public static function listeners(string $event): array
    {
        return self::$listeners[$event] ?? [];
    }
}
