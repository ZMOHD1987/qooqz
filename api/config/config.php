<?php
// minimal temporary config.php — use only for debugging.
// Make backup of original file (config.php.bak) before replacing.
// Restore original file after fixing the syntax error in original.

define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DOMAIN', getenv('APP_DOMAIN') ?: '');
define('DEFAULT_LANG', 'ar');

// compute BASE_PATH from script name
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir  = dirname($scriptName);
$basePath = rtrim($scriptDir, '/\\');
if ($basePath === '/' || $basePath === '\\') $basePath = '';
define('BASE_PATH', $basePath);

// Safe defaults for other optional settings used by admin pages
define('SITE_NAME', 'My Site (temp)');