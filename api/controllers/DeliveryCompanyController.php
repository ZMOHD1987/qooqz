<?php
/**
 * api/controllers/DeliveryCompanyController.php
 *
 * DeliveryCompanyController: methods for list/get/create/update/delete and helpers.
 *
 * Relies on: $db (mysqli), DeliveryCompanyModel (api/models/DeliveryCompany.php)
 */

declare(strict_types=1);

class DeliveryCompanyController
{
    private mysqli $db;
    private DeliveryCompanyModel $model;
    private array $currentUser;
    private string $debugLog;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->model = new DeliveryCompanyModel($db);
        $this->currentUser = $this->getCurrentUserSafe() ?? ['id' => 0];
        $this->debugLog = __DIR__ . '/../error_debug.log';
    }

    private function getCurrentUserSafe(): ?array
    {
        // Prefer auth helper function if available
        if (function_exists('get_authenticated_user_with_permissions')) {
            try {
                $u = get_authenticated_user_with_permissions();
                if (is_array($u)) return $u;
            } catch (Throwable $e) {
                @file_put_contents($this->debugLog, "[".date('c')."] auth user read failed: ".$e->getMessage().PHP_EOL, FILE_APPEND);
            }
        }
        // Fallbacks
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
        if (!empty($_SESSION['user_id'])) {
            return [
                'id' => (int)$_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0,
                'permissions' => $_SESSION['permissions'] ?? []
            ];
        }
        return null;
    }

    private function isAdmin(): bool
    {
        if (isset($this->currentUser['role_id']) && (int)$this->currentUser['role_id'] === 1) return true;
        if (!empty($this->currentUser['permissions']) && is_array($this->currentUser['permissions']) && in_array('superadmin', $this->currentUser['permissions'])) return true;
        return false;
    }

    private function checkCsrf($token): bool
    {
        if (function_exists('verify_csrf')) {
            return verify_csrf($token);
        }
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals((string)$_SESSION['csrf_token'], (string)$token);
    }

    private function log($msg)
    {
        @file_put_contents($this->debugLog, "[".date('c')."] " . trim($msg) . PHP_EOL, FILE_APPEND);
    }

    // ------- Filters for countries/cities (translation-aware) -------
    public function filterCountries(?string $lang = null): array
    {
        $rows = [];
        $hasTrans = $this->tableExists('country_translations');
        if ($lang && $hasTrans) {
            $sql = "SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name
                    FROM delivery_companies dc
                    JOIN countries c ON c.id = dc.country_id
                    LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?
                    WHERE dc.country_id IS NOT NULL
                    ORDER BY name ASC";
            $stmt = $this->db->prepare($sql);
            if ($stmt) { bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
        } else {
            $sql = "SELECT DISTINCT c.id, c.name
                    FROM delivery_companies dc
                    JOIN countries c ON c.id = dc.country_id
                    WHERE dc.country_id IS NOT NULL
                    ORDER BY c.name ASC";
            $res = $this->db->query($sql);
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function filterCities(?int $country_id = null, ?string $lang = null): array
    {
        $rows = [];
        $hasTrans = $this->tableExists('city_translations');
        if ($lang && $hasTrans) {
            if ($country_id) {
                $sql = "SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name
                        FROM delivery_companies dc
                        JOIN cities ci ON ci.id = dc.city_id
                        LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?
                        WHERE dc.city_id IS NOT NULL AND ci.country_id = ?
                        ORDER BY name ASC";
                $stmt = $this->db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 'si', [$lang, $country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            } else {
                $sql = "SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name
                        FROM delivery_companies dc
                        JOIN cities ci ON ci.id = dc.city_id
                        LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?
                        WHERE dc.city_id IS NOT NULL
                        ORDER BY name ASC";
                $stmt = $this->db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            }
        } else {
            if ($country_id) {
                $sql = "SELECT DISTINCT ci.id, ci.name
                        FROM delivery_companies dc
                        JOIN cities ci ON ci.id = dc.city_id
                        WHERE dc.city_id IS NOT NULL AND ci.country_id = ?
                        ORDER BY ci.name ASC";
                $stmt = $this->db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 'i', [$country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            } else {
                $sql = "SELECT DISTINCT ci.id, ci.name
                        FROM delivery_companies dc
                        JOIN cities ci ON ci.id = dc.city_id
                        WHERE dc.city_id IS NOT NULL
                        ORDER BY ci.name ASC";
                $res = $this->db->query($sql);
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
        }
        return $rows;
    }

    // ------- parents list -------
    public function parents(): array
    {
        $rows = [];
        $sql = "SELECT id, name FROM delivery_companies ORDER BY name ASC";
        $res = $this->db->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    // ------- get single company (with translations) -------
    public function getCompany(int $id, ?string $lang = null): ?array
    {
        // Use model if it returns all fields; otherwise perform joined query for translations
        $row = $this->model->findById($id);
        if (!$row) return null;

        $translations = [];
        if ($this->tableExists('delivery_company_translations')) {
            $tstmt = $this->db->prepare("SELECT language_code, description, terms, meta_title, meta_description FROM delivery_company_translations WHERE company_id = ?");
            if ($tstmt) {
                bind_params_stmt($tstmt, 'i', [$id]);
                $tstmt->execute();
                $trs = stmt_fetch_all_assoc($tstmt);
                foreach ($trs as $tr) $translations[$tr['language_code']] = $tr;
                $tstmt->close();
            }
        }
        $row['translations'] = $translations;
        // If translation tables exist and language requested, add country_name/city_name (handled previously in monolith)
        // Simple fallback: do not modify names here; front-end can request localized names via filter endpoints.
        return $row;
    }

    // ------- list companies (filters, pagination) -------
    public function listCompanies(array $filters = [], int $page = 1, int $per = 20, ?string $lang = null, bool $debug = false): array
    {
        // Delegate to model if it has list implementation with filters & pagination
        return $this->model->list($filters, $per, max(0, ($page - 1) * $per));
    }

    // ------- create company -------
    public function createCompany(array $post, array $files = []): array
    {
        // CSRF
        if (isset($post['csrf_token']) && !$this->checkCsrf($post['csrf_token'])) {
            return ['error' => 'Invalid CSRF', 'code' => 403];
        }

        // validate
        if (!function_exists('validate_delivery_company_create')) {
            return ['error' => 'Validator missing', 'code' => 500];
        }
        $v = validate_delivery_company_create($post);
        if (!$v['valid']) return ['error' => 'Validation failed', 'code' => 400, 'errors' => $v['errors']];

        // owner
        if (isset($post['user_id']) && $this->isAdmin()) {
            $ownerId = (int)$post['user_id'];
        } else {
            $ownerId = isset($this->currentUser['id']) ? (int)$this->currentUser['id'] : 0;
        }

        $data = $v['data'];
        $data['user_id'] = $ownerId;

        // create via model if available
        $newId = $this->model->create($data);
        if (!$newId) return ['error' => 'Create failed', 'code' => 500];

        // handle logo upload
        if (!empty($files['logo'])) {
            $url = $this->saveUploadedFile($files['logo'], $newId);
            if ($url) {
                $ust = $this->db->prepare("UPDATE delivery_companies SET logo_url = ? WHERE id = ? LIMIT 1");
                if ($ust) { bind_params_stmt($ust, 'si', [$url, $newId]); $ust->execute(); $ust->close(); }
            }
        }

        // translations handled by route/controller if provided (route will pass translations JSON)
        return ['id' => $newId];
    }

    // ------- update company -------
    public function updateCompany(int $id, array $post, array $files = []): array
    {
        if (isset($post['csrf_token']) && !$this->checkCsrf($post['csrf_token'])) {
            return ['error' => 'Invalid CSRF', 'code' => 403];
        }
        $existing = $this->model->findById($id);
        if (!$existing) return ['error' => 'Not found', 'code' => 404];

        // authorization: owner or admin
        $ownerId = isset($existing['user_id']) ? (int)$existing['user_id'] : 0;
        $uid = isset($this->currentUser['id']) ? (int)$this->currentUser['id'] : 0;
        if (!$this->isAdmin() && $ownerId !== $uid) return ['error' => 'Forbidden', 'code' => 403];

        if (!function_exists('validate_delivery_company_update')) return ['error'=>'Validator missing', 'code'=>500];
        $v = validate_delivery_company_update($post, $this->isAdmin());
        if (!$v['valid']) return ['error'=>'Validation failed','code'=>400,'errors'=>$v['errors']];

        $data = $v['data'];

        // logo upload
        if (!empty($files['logo'])) {
            $url = $this->saveUploadedFile($files['logo'], $id);
            if ($url) $data['logo_url'] = $url;
        }

        $ok = $this->model->update($id, $data);
        if (!$ok) return ['error' => 'Update failed', 'code' => 500];

        return ['message' => 'Updated'];
    }

    // ------- delete company -------
    public function deleteCompany(int $id, array $post = []): array
    {
        if (isset($post['csrf_token']) && !$this->checkCsrf($post['csrf_token'])) {
            return ['error' => 'Invalid CSRF', 'code' => 403];
        }
        $existing = $this->model->findById($id);
        if (!$existing) return ['error' => 'Not found', 'code' => 404];

        $ownerId = isset($existing['user_id']) ? (int)$existing['user_id'] : 0;
        $uid = isset($this->currentUser['id']) ? (int)$this->currentUser['id'] : 0;
        if (!$this->isAdmin() && $ownerId !== $uid) return ['error' => 'Forbidden', 'code' => 403];

        $ok = $this->model->delete($id);
        if (!$ok) return ['error' => 'Delete failed', 'code' => 500];
        return ['deleted' => true];
    }

    // ------- create token -------
    public function createCompanyToken(int $companyId, array $post): array
    {
        if (isset($post['csrf_token']) && !$this->checkCsrf($post['csrf_token'])) {
            return ['error' => 'Invalid CSRF', 'code' => 403];
        }
        $existing = $this->model->findById($companyId);
        if (!$existing) return ['error' => 'Not found', 'code' => 404];
        $ownerId = isset($existing['user_id']) ? (int)$existing['user_id'] : 0;
        $uid = isset($this->currentUser['id']) ? (int)$this->currentUser['id'] : 0;
        if (!$this->isAdmin() && $ownerId !== $uid) return ['error' => 'Forbidden', 'code' => 403];

        $name = substr(trim((string)($post['name'] ?? 'token')), 0, 100);
        $scopes = substr(trim((string)($post['scopes'] ?? '')), 0, 255);
        $expires_in = isset($post['expires_in']) ? (int)$post['expires_in'] : 0;
        $expires_at = $expires_in > 0 ? date('Y-m-d H:i:s', time() + $expires_in) : null;
        $token = bin2hex(random_bytes(32));
        $ins = $this->db->prepare("INSERT INTO delivery_company_tokens (company_id, token, name, scopes, expires_at) VALUES (?, ?, ?, ?, ?)");
        if (!$ins) { $this->log("token insert prepare failed: " . $this->db->error); return ['error'=>'Server error','code'=>500]; }
        bind_params_stmt($ins, 'issss', [$companyId, $token, $name, $scopes, $expires_at]);
        $ok = $ins->execute(); $ins->close();
        if (!$ok) { $this->log("token insert error: " . $this->db->error); return ['error'=>'Token creation failed','code'=>500]; }
        return ['token' => $token];
    }

    // ------- helper: save uploaded file -------
    private function saveUploadedFile(array $file, int $companyId): ?string
    {
        if (empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;
        $uploadsRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/..'), '/\\') . '/uploads/delivery_companies/' . $companyId;
        if (!is_dir($uploadsRoot)) @mkdir($uploadsRoot, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe = bin2hex(random_bytes(10)) . ($ext ? '.' . preg_replace('/[^a-z0-9]/i', '', $ext) : '');
        $dest = $uploadsRoot . '/' . $safe;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
        return '/uploads/delivery_companies/' . $companyId . '/' . $safe;
    }

    private function tableExists(string $name): bool
    {
        try {
            $r = $this->db->query("SHOW TABLES LIKE '" . $this->db->real_escape_string($name) . "'");
            return ($r && $r->num_rows > 0);
        } catch (Throwable $e) { return false; }
    }
}