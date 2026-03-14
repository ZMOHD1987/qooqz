<?php
declare(strict_types=1);

/**
 * Framework-grade Middleware Pipeline
 */
final class MiddlewarePipeline
{
    /** @var MiddlewareBase[] */
    private array $middlewares = [];

    private array $context;

    public function __construct(array $context = [])
    {
        $this->context = $context;
    }

    /**
     * Register middleware
     */
    public function pipe(string $middlewareClass): void
    {
        if (!is_subclass_of($middlewareClass, MiddlewareBase::class)) {
            throw new InvalidArgumentException(
                "Middleware {$middlewareClass} must extend MiddlewareBase"
            );
        }

        $this->middlewares[] = $middlewareClass;
    }

    /**
     * Execute pipeline
     */
    public function handle(array $request, callable $finalHandler): mixed
    {
        $dispatcher = array_reduce(
            array_reverse($this->middlewares),
            function (callable $next, string $middlewareClass) {

                return function (array $request) use ($next, $middlewareClass) {

                    $middleware = new $middlewareClass($this->context);

                    return $middleware->handle($request, $next);
                };
            },
            $finalHandler
        );

        return $dispatcher($request);
    }
}
