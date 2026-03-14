<?php
declare(strict_types=1);

use Shared\Application\Context\RequestContext;
use Shared\Application\DTO\UpdateUserDTO;
use Shared\Application\Actions\User\UpdateUserAction;
use Shared\Infrastructure\Persistence\MySQL\UserRepository;

defined('API_ENTRY') || exit('Direct access denied');

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$context = RequestContext::current();
$dto = new UpdateUserDTO($context->input());

$repository = new UserRepository($GLOBALS['ADMIN_DB']);
$action = new UpdateUserAction($repository);

$result = $action->execute($context, $dto);

http_response_code(200);
echo json_encode([
    'success' => true,
    'data'    => $result,
]);
