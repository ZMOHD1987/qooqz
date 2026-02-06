<?php
declare(strict_types=1);

final class UsersController
{
    private UsersService $service;
    private PermissionService $permissionService;

    public function __construct(UsersService $service, PermissionService $permissionService)
    {
        $this->service = $service;
        $this->permissionService = $permissionService;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        // ✅ Check view permission
        if (!$this->permissionService->hasPermission('manage_users') && 
            !$this->permissionService->hasPermission('view_users')) {
            throw new UnauthorizedException('No permission to view users');
        }

        // ✅ Apply row-level security
        $whereClause = $this->permissionService->buildListWhereClause('users', 'manage_users');
        
        error_log('[UsersController] List WHERE: ' . $whereClause['where']);
        error_log('[UsersController] List PARAMS: ' . json_encode($whereClause['params']));

        // Merge with existing filters
        $filters = array_merge($filters, $whereClause['params']);
        
        if (!empty($whereClause['where'])) {
            $filters['_where_clause'] = $whereClause['where'];
        }

        $userLanguage = isset($_GET['language']) ? trim($_GET['language']) : null;
        return $this->service->list($limit, $offset, $filters, $userLanguage);
    }

    public function get(int $id): array
    {
        $user = $this->service->get($id);

        // ✅ Check if user can view this record
        $canView = $this->permissionService->canView(
            'users',
            'manage_users',
            $user['id'] ?? null,
            $user['tenant_id'] ?? null
        );

        if (!$canView) {
            throw new UnauthorizedException('No permission to view this user');
        }

        return $user;
    }

    public function create(array $data): array
    {
        // ✅ Check create permission
        if (!$this->permissionService->canCreate('users', 'manage_users')) {
            throw new UnauthorizedException('No permission to create users');
        }

        $userId = $this->permissionService->getCurrentUserId();
        return $this->service->save($data, $userId);
    }

    public function update(array $data, ?int $id = null): array
    {
        $itemId = $id ?? (int)($data['id'] ?? 0);

        if ($itemId <= 0) {
            throw new InvalidArgumentException('ID is required for update');
        }

        $data['id'] = $itemId;

        // ✅ Get existing record to check ownership
        $existing = $this->service->get($itemId);

        // ✅ Check edit permission
        $canEdit = $this->permissionService->canEdit(
            'users',
            'manage_users',
            $existing['id'] ?? null
        );

        if (!$canEdit) {
            throw new UnauthorizedException('No permission to edit this user');
        }

        $userId = $this->permissionService->getCurrentUserId();
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $id = (int)$data['id'];

        // ✅ Get existing record
        $existing = $this->service->get($id);

        // ✅ Check delete permission
        $canDelete = $this->permissionService->canDelete(
            'users',
            'manage_users',
            $existing['id'] ?? null
        );

        if (!$canDelete) {
            throw new UnauthorizedException('No permission to delete this user');
        }

        $userId = $this->permissionService->getCurrentUserId();
        $this->service->delete($id, $userId);
    }
}