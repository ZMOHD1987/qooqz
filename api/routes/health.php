<?php
declare(strict_types=1);

return [
    'GET' => [
        '/health' => function () {
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'time' => date('c')
            ]);
        },
    ],
];
