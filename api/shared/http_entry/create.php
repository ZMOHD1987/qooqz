<?php
declare(strict_types=1);

use Shared\Application\Context\RequestContext;
use Shared\Application\DTO\CreateUserDTO;
use Shared\Application\Actions\User\CreateUserAction;
use Shared\Infrastructure\Persistence\MySQL\UserRepository;

/**
 * HTTP ENTRY – CREATE USER
 * Loaded ONLY through kernel.php
 */

defined('API_ENTRY') || exit('Direct access denied');

/* ───── Method Guard ───── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

/* ───── Context (Already booted) ───── */
$context = RequestContext::current();

/* ───── Payload → DTO ───── */
$payload = $context->input();
$dto = new CreateUserDTO($payload);

/* ───── Action ───── */
$repository = new UserRepository($GLOBALS['ADMIN_DB']);
$action = new CreateUserAction($repository);

$result = $action->execute($context, $dto);

/* ───── Response ───── */
http_response_code(201);
echo json_encode([
    'success' => true,
    'data'    => $result,
]);
