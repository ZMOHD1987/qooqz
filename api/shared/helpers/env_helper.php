<?php
declare(strict_types=1);

if (!function_exists('app_env')) {
    function app_env(): string
    {
        return $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?? 'production';
    }
}
