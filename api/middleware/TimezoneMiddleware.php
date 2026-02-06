<?php
//htdocs/api/middleware/TimezoneMiddleware.php
function apply_user_timezone($user = null)
{
    if (!$user || empty($user['timezone'])) {
        date_default_timezone_set('UTC');
        return 'UTC';
    }

    try {
        date_default_timezone_set($user['timezone']);
        return $user['timezone'];
    } catch (\Exception $e) {
        date_default_timezone_set('UTC');
        return 'UTC';
    }
}
