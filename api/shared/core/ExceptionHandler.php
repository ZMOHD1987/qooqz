<?php
declare(strict_types=1);
// htdocs/api/shared/core/ExceptionHandler.php

final class ExceptionHandler
{
    private static bool $registered = false;

    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Bootstrap
     * ========================= */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        set_exception_handler([self::class, 'handleThrowable']);

        set_error_handler(function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /* =========================
     * Throwable handler
     * ========================= */
    public static function handleThrowable(Throwable $e): void
    {
        self::report($e);
        self::render($e);
        exit;
    }

    /* =========================
     * Fatal errors
     * ========================= */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ], true)) {
            $e = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            self::report($e);
            self::render($e);
            exit;
        }
    }

    /* =========================
     * Report
     * ========================= */
    private static function report(Throwable $e): void
    {
        if (class_exists('Logger')) {
            Logger::error(
                sprintf(
                    '%s: %s in %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }

        if (class_exists('EventDispatcher')) {
            EventDispatcher::dispatch('exception.thrown', [
                'exception' => $e
            ]);
        }
    }

    /* =========================
     * Render API response
     * ========================= */
    private static function render(Throwable $e): void
    {
        $debug = (bool) ConfigLoader::get('app.debug', false);

        if ($e instanceof DomainException) {
            ResponseFormatter::error(
                $e->getMessage(),
                $e->getStatusCode(),
                $e->getContext()
            );
            return;
        }

        if ($debug) {
            ResponseFormatter::serverError([
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => explode("\n", $e->getTraceAsString()),
            ]);
            return;
        }

        ResponseFormatter::serverError('Internal Server Error');
    }
}
