<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class LanguagesModel
{
    protected ?mysqli $db = null;

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db ?: ($GLOBALS['conn'] ?? null);
    }

    protected function db(): mysqli
    {
        if (!$this->db) {
            throw new RuntimeException('No active database connection');
        }
        return $this->db;
    }

    /* ================= List ================= */
    public function all(): array
    {
        $res = $this->db()->query(
            "SELECT code, name, direction FROM languages ORDER BY name ASC"
        );

        if (!$res) {
            throw new RuntimeException($this->db()->error);
        }

        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /* ================= Save (Insert / Update) ================= */
    public function save(array $data): bool
    {
        $sql = "
            INSERT INTO languages (code, name, direction)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                direction = VALUES(direction)
        ";

        $stmt = $this->db()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($this->db()->error);
        }

        $stmt->bind_param(
            'sss',
            $data['code'],
            $data['name'],
            $data['direction']
        );

        return $stmt->execute();
    }

    /* ================= Delete ================= */
    public function delete(string $code): bool
    {
        $stmt = $this->db()->prepare(
            "DELETE FROM languages WHERE code = ? LIMIT 1"
        );

        if (!$stmt) {
            throw new RuntimeException($this->db()->error);
        }

        $stmt->bind_param('s', $code);
        return $stmt->execute();
    }
}

/* ============================================================
   Facade class used by Controllers / Routes
   ============================================================ */

class Languages
{
    private static ?LanguagesModel $instance = null;

    protected static function model(): LanguagesModel
    {
        if (!self::$instance) {
            self::$instance = new LanguagesModel();
        }
        return self::$instance;
    }

    public static function all(): array
    {
        return self::model()->all();
    }

    public static function save(array $data): bool
    {
        return self::model()->save($data);
    }

    public static function delete(string $code): bool
    {
        return self::model()->delete($code);
    }
}
