<?php
// htdocs/api/middleware/middleware_pipeline.php
// Global Middleware Pipeline for a professional, secure MVC system.

declare(strict_types=1);

class MiddlewarePipeline
{
    private array $middlewares = [];
    private array $context = []; // Shared context for PDO, JWT, request, etc.
    private bool $debug = false;

    /**
     * Constructor
     * @param array $context Context to pass to all middlewares
     * @param bool $debug Enable debug logging
     */
    public function __construct(array $context = [], bool $debug = false)
    {
        $this->context = $context;
        $this->debug = $debug;
    }

    /**
     * Add middleware to the pipeline
     * @param string $middlewareClass Class name
     * @param array $params Additional parameters
     * @return $this
     */
    public function through(string $middlewareClass, array $params = []): self
    {
        $this->middlewares[] = ['class' => $middlewareClass, 'params' => $params];
        return $this;
    }

    /**
     * Alias to add middleware quickly
     */
    public function pipe(string $middlewareClass, array $params = []): self
    {
        return $this->through($middlewareClass, $params);
    }

    /**
     * Run the middleware pipeline
     * @param array $data Initial data (request, etc.)
     * @param callable $destination Final controller
     * @return mixed
     */
    public function then(array $data, callable $destination)
    {
        $this->context = array_merge($this->context, $data);

        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, $middleware) {
                return function ($ctx) use ($next, $middleware) {
                    $class = $middleware['class'];
                    $params = $middleware['params'];

                    $start = microtime(true);

                    try {
                        if (!class_exists($class)) {
                            throw new RuntimeException("Middleware class $class not found");
                        }

                        // Prefer static handle, else instance handle
                        if (method_exists($class, 'handle')) {
                            $result = $class::handle($ctx, $next, ...$params);
                        } else {
                            $instance = new $class(...$params);
                            if (!method_exists($instance, 'handle')) {
                                throw new RuntimeException("Middleware $class has no handle method");
                            }
                            $result = $instance->handle($ctx, $next);
                        }

                        if ($this->debug) {
                            $this->log("[Middleware] $class executed in " . round((microtime(true) - $start) * 1000, 2) . " ms");
                        }

                        return $result;

                    } catch (Exception $e) {
                        $this->log("[Middleware Exception] $class: " . $e->getMessage());
                        http_response_code(500);
                        echo json_encode([
                            'success' => false,
                            'message' => "Middleware error: {$class}",
                            'debug' => $this->debug ? $e->getMessage() : null
                        ]);
                        exit;
                    }
                };
            },
            $destination
        );

        return $pipeline($this->context);
    }

    /**
     * Log message to file or error_log
     * @param string $msg
     */
    private function log(string $msg): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }

    /**
     * Get or set shared context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
