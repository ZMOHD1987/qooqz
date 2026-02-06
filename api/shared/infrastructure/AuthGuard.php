<?php
declare(strict_types=1);

final class AuthGuard
{
    public static function user(): ?array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($auth, 'Bearer ')) {
            return null;
        }

        $token = substr($auth, 7);

        try {
            $claims = JWT::decode($token);
        } catch (Throwable) {
            return null;
        }

        return [
            'id' => (int)$claims['sub'],
            'role' => $claims['role'],
            'permissions_hash' => $claims['permissions_hash'],
        ];
    }

    public static function require(): array
    {
        $user = self::user();
        if (!$user) {
            ResponseFormatter::unauthorized('Authentication required');
            exit;
        }
        return $user;
    }
}
