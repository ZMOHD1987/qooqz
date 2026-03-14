<?php
// htdocs/api/shared/config/db.php

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'sv61.ifastnet10.org');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'hcsfcsto_user');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'Mohd28332@');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'hcsfcsto_qooqz');
if (!defined('DB_CHARSET')) define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: 3306);

return [
    'host' => DB_HOST,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'name' => DB_NAME,
    'charset' => DB_CHARSET,
    'port' => DB_PORT,
];