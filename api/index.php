<?php
declare(strict_types=1);

/**
 * ==================================================
 * API Front Controller (FINAL)
 * ==================================================
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Kernel.php';

header('Content-Type: application/json; charset=utf-8');

Kernel::dispatch();
