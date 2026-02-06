<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];

$canCreate = in_array('manage_users', $permissions, true) || in_array('super_admin', $roles, true);
$canEdit = $canCreate;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

if (!$canCreate && !$canEdit) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$userData = [];
if ($isEdit) {
    // Load data via API
    $apiUrl = '/api/users_account?id=' . $id;
    $response = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . $apiUrl);
    $data = json_decode($response, true);
    $userData = $data['data'] ?? [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Form</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="modal-content">
        <div class="modal-header">
            <h2><?= $isEdit ? 'Edit' : 'Add' ?> User</h2>
            <button onclick="closeModal()">×</button>
        </div>

        <form id="userForm" class="modal-body">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($userData['username'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password <?= !$isEdit ? '*' : '(leave blank to keep)' ?></label>
                <input type="password" id="password" name="password" <?= !$isEdit ? 'required' : '' ?>>
            </div>

            <div class="form-group">
                <label for="preferred_language">Preferred Language</label>
                <select id="preferred_language" name="preferred_language">
                    <option value="en" <?= ($userData['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="ar" <?= ($userData['preferred_language'] ?? 'en') === 'ar' ? 'selected' : '' ?>>Arabic</option>
                    <option value="fr" <?= ($userData['preferred_language'] ?? 'en') === 'fr' ? 'selected' : '' ?>>French</option>
                    <option value="es" <?= ($userData['preferred_language'] ?? 'en') === 'es' ? 'selected' : '' ?>>Spanish</option>
                </select>
            </div>

            <div class="form-group">
                <label for="role_id">Role ID</label>
                <input type="text" id="role_id" list="roleList" name="role_id" value="<?= htmlspecialchars($userData['role_id'] ?? '') ?>">
                <datalist id="roleList"></datalist>
            </div>

            <div class="form-group">
                <label for="country_id">Country ID</label>
                <input type="text" id="country_id" name="country_id" value="<?= htmlspecialchars($userData['country_id'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="city_id">City ID</label>
                <input type="text" id="city_id" name="city_id" value="<?= htmlspecialchars($userData['city_id'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="timezone">Timezone</label>
                <input type="text" id="timezone" name="timezone" value="<?= htmlspecialchars($userData['timezone'] ?? 'UTC') ?>">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="is_active" name="is_active" <?= ($userData['is_active'] ?? 1) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
        </form>

        <div class="modal-footer">
            <button onclick="closeModal()">Cancel</button>
            <?php if ($canCreate || $canEdit): ?>
            <button type="submit" form="userForm">Save</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
    window.USER_DATA = <?= json_encode($userData) ?>;
    window.USER_MODE = '<?= $isEdit ? "edit" : "create" ?>';

    function closeModal() {
        if (window.parent && window.parent.AdminModal) {
            window.parent.AdminModal.closeModal();
        } else {
            window.close();
        }
    }

    async function submitForm(e) {
        e.preventDefault();
        const form = document.getElementById('userForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        data.is_active = form.querySelector('[name="is_active"]').checked ? 1 : 0;

        try {
            const method = window.USER_MODE === 'edit' ? 'PUT' : 'POST';
            const response = await fetch('/api/users_account', {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                alert('Saved successfully');
                closeModal();
                if (window.parent && window.parent.location) {
                    window.parent.location.reload();
                }
            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Save failed: ' + error.message);
        }
    }

    document.getElementById('userForm').addEventListener('submit', submitForm);
    </script>
</body>
</html>