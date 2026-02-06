<?php
declare(strict_types=1);

namespace Shared\Application\Actions;

use Shared\Application\Context\RequestContext;

interface ActionInterface
{
    public function execute(RequestContext $context): array;
}
