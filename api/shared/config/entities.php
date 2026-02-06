<?php
return [

    'users' => [
        'table' => 'users',
        'primary' => 'id',
        'fillable' => ['name', 'email', 'password', 'status'],
    ],

    'products' => [
        'table' => 'products',
        'primary' => 'id',
        'fillable' => ['name', 'price', 'stock', 'status'],
    ],

    'vendors' => [
        'table' => 'vendors',
        'primary' => 'id',
        'fillable' => ['name', 'phone', 'status'],
    ],

];
