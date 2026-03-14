<?php
// htdocs/api/config/routes.php

return [

    // Home
    [
        'method'  => 'GET',
        'path'    => '/home',
        'handler' => ['HomeController', 'index'],
    ],

    // Auth
    [
        'method'  => 'POST',
        'path'    => '/auth/login',
        'handler' => ['AuthController', 'login'],
    ],

];
