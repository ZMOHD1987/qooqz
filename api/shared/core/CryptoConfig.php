<?php
declare(strict_types=1);

final class CryptoConfig
{
    private static ?string $masterKey = null;

    public static function masterKey(): string
    {
        if (self::$masterKey === null) {
            
            // 1. Check Environment Variable
            $envKey = getenv('CRYPTO_MASTER_KEY');
            if ($envKey && strlen($envKey) === 32) {
                self::$masterKey = $envKey;
                return self::$masterKey;
            }

            // 2. Check Local Config File
            $localPath = __DIR__ . '/../config/crypto_key.php';
            if (file_exists($localPath)) {
                $config = require $localPath;
                if (isset($config['master_key']) && is_string($config['master_key']) && strlen($config['master_key']) === 32) {
                    self::$masterKey = $config['master_key'];
                    return self::$masterKey;
                }
            }

            // 3. Fallback to hardcoded path (Legacy/Production)
            $path = '/home/hcsfcsto/.qooqz/.a9F3kQmP7ZxL2D.php';

            if (!file_exists($path)) {
                // If neither local nor production key exists, throw error
                throw new RuntimeException('Crypto key file missing. Please create api/shared/config/crypto_key.php');
            }

            $config = require $path;

            if (
                !isset($config['master_key']) ||
                !is_string($config['master_key']) ||
                strlen($config['master_key']) !== 32
            ) {
                throw new RuntimeException('Invalid crypto master key');
            }

            self::$masterKey = $config['master_key'];
        }

        return self::$masterKey;
    }
}
