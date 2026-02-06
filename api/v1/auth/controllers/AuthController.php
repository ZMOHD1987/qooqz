<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/core/ResponseFormatter.php';
require_once dirname(__DIR__, 3) . '/shared/core/Logger.php';

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../validators/LoginValidator.php';

class AuthController
{
    /**
     * Login
     */
    public static function login(array $input): void
    {
        try {
            $errors = LoginValidator::validate($input);
            if (!empty($errors)) {
                ResponseFormatter::validationError($errors);
            }

            $identifier = trim((string)(
                $input['username']
                ?? $input['email']
                ?? $input['phone']
                ?? ''
            ));
            $password = (string)($input['password'] ?? '');

            if ($identifier === '' || $password === '') {
                ResponseFormatter::validationError([
                    'identifier' => 'Required',
                    'password'   => 'Required'
                ]);
            }

            $auth = new AuthService($GLOBALS['ADMIN_DB'] ?? null);
            $user = $auth->login($identifier, $password);

            if (!$user) {
                ResponseFormatter::unauthorized('Invalid credentials');
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role_id'  => (int)$user['role_id'],
            ];

            ResponseFormatter::success([
                'user' => $_SESSION['user']
            ], 'Login successful');

        } catch (Throwable $e) {
            Logger::error('AuthController::login ' . $e->getMessage());
            ResponseFormatter::serverError();
        }
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();

            ResponseFormatter::success([], 'Logout successful');

        } catch (Throwable $e) {
            Logger::error('AuthController::logout ' . $e->getMessage());
            ResponseFormatter::serverError();
        }
    }

    /**
     * Current user
     */
    public static function me(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if (empty($_SESSION['user'])) {
                ResponseFormatter::unauthorized('Not authenticated');
            }

            ResponseFormatter::success([
                'user' => $_SESSION['user']
            ]);

        } catch (Throwable $e) {
            Logger::error('AuthController::me ' . $e->getMessage());
            ResponseFormatter::serverError();
        }
    }

    /**
     * Register
     */
    public static function register(array $input): void
    {
        try {
            $required = ['username', 'email', 'phone', 'password'];
            $errors = [];

            foreach ($required as $field) {
                if (empty(trim($input[$field] ?? ''))) {
                    $errors[$field] = 'Required';
                }
            }

            if (!empty($errors)) {
                ResponseFormatter::validationError($errors);
            }

            $auth = new AuthService($GLOBALS['ADMIN_DB'] ?? null);
            $id = $auth->register($input);

            if (!$id) {
                ResponseFormatter::serverError('Registration failed');
            }

            ResponseFormatter::created([
                'id' => (int)$id
            ], 'Registration successful');

        } catch (Throwable $e) {
            Logger::error('AuthController::register ' . $e->getMessage());
            ResponseFormatter::serverError();
        }
    }
}
