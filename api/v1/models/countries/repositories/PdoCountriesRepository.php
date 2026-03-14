<?php
declare(strict_types=1);

final class PdoCountriesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List countries with optional filters and pagination.
     * If $filters['lang'] provided, include translation name as `name`.
     *
     * Returns ['items'=>[], 'meta'=>[]]
     */
    public function list(array $filters = []): array
    {
        $lang = $filters['lang'] ?? null;
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $selectParams = [];
        $countParams = [];

        if (!empty($filters['id'])) {
            $where[] = 'c.id = :id';
            $selectParams[':id'] = (int)$filters['id'];
            $countParams[':id'] = (int)$filters['id'];
        }
        if (!empty($filters['iso2'])) {
            $where[] = 'c.iso2 = :iso2';
            $selectParams[':iso2'] = $filters['iso2'];
            $countParams[':iso2'] = $filters['iso2'];
        }
        if (!empty($filters['iso3'])) {
            $where[] = 'c.iso3 = :iso3';
            $selectParams[':iso3'] = $filters['iso3'];
            $countParams[':iso3'] = $filters['iso3'];
        }
        if (!empty($filters['currency_code'])) {
            $where[] = 'c.currency_code = :currency_code';
            $selectParams[':currency_code'] = $filters['currency_code'];
            $countParams[':currency_code'] = $filters['currency_code'];
        }

        $nameSearch = null;
        if (!empty($filters['name'])) {
            $nameSearch = trim($filters['name']);
            if ($lang) {
                $where[] = '(c.name LIKE :name OR ct.name LIKE :name)';
            } else {
                $where[] = 'c.name LIKE :name';
            }
            $selectParams[':name'] = '%' . $nameSearch . '%';
            $countParams[':name'] = '%' . $nameSearch . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        if ($lang) {
            $countSql = "SELECT COUNT(DISTINCT c.id) as total FROM countries c
                LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = :_count_lang
                {$whereSql}";
            $countParams[':_count_lang'] = $lang;
            $stmt = $this->pdo->prepare($countSql);
            $stmt->execute($countParams);
        } else {
            $countSql = "SELECT COUNT(*) as total FROM countries c {$whereSql}";
            $stmt = $this->pdo->prepare($countSql);
            $stmt->execute($countParams);
        }

        $total = (int)$stmt->fetchColumn();

        // Main select
        $select = "SELECT c.id, c.iso2, c.iso3, c.name as base_name, c.currency_code";
        if ($lang) {
            $select .= ", ct.name AS translated_name";
        }
        $sql = $select . " FROM countries c ";
        if ($lang) {
            $sql .= " LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = :lang ";
            $selectParams[':lang'] = $lang;
        }
        $sql .= " {$whereSql} ORDER BY COALESCE(ct.name, c.name) ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        // Merge limit/offset into params for execute
        $selectParams[':limit'] = $perPage;
        $selectParams[':offset'] = $offset;

        // Execute with execute($params) to avoid binding non-existing params
        $stmt->execute($selectParams);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize name: prefer translated_name if present
        foreach ($items as &$it) {
            if (!empty($it['translated_name'])) {
                $it['name'] = $it['translated_name'];
            } else {
                $it['name'] = $it['base_name'];
            }
            unset($it['base_name'], $it['translated_name']);
        }

        $meta = [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0
        ];

        return ['items' => $items, 'meta' => $meta];
    }

    /**
     * Get country by id, optionally include translation for $lang.
     */
    public function getById(int $id, ?string $lang = null): ?array
    {
        $sql = "SELECT c.id, c.iso2, c.iso3, c.name as base_name, c.currency_code";
        if ($lang) $sql .= ", ct.name AS translated_name";
        $sql .= " FROM countries c";
        if ($lang) {
            $sql .= " LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = :lang";
        }
        $sql .= " WHERE c.id = :id LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($lang) $stmt->bindValue(':lang', $lang, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['name'] = $row['translated_name'] ?? $row['base_name'];
        unset($row['base_name'], $row['translated_name']);

        $row['translations'] = $this->getTranslations((int)$row['id']);
        return $row;
    }

    /**
     * Get country by iso2 or iso3 or name (first match). Tries iso2, iso3, then translation (if lang), then base name.
     */
    public function getByIdentifier(string $identifier, ?string $lang = null): ?array
    {
        // numeric id
        if (ctype_digit($identifier)) {
            return $this->getById((int)$identifier, $lang);
        }

        // iso2 exact
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE iso2 = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) return $this->getById((int)$id, $lang);

        // iso3 exact
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE iso3 = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) return $this->getById((int)$id, $lang);

        // translation match if lang specified
        if ($lang) {
            $stmt = $this->pdo->prepare("SELECT country_id FROM country_translations WHERE language_code = :lang AND name = :v LIMIT 1");
            $stmt->execute([':lang' => $lang, ':v' => $identifier]);
            if ($id = $stmt->fetchColumn()) return $this->getById((int)$id, $lang);
        }

        // name match (base)
        $stmt = $this->pdo->prepare("SELECT id FROM countries WHERE name = :v LIMIT 1");
        $stmt->execute([':v' => $identifier]);
        if ($id = $stmt->fetchColumn()) return $this->getById((int)$id, $lang);

        return null;
    }

    /**
     * Convenience: check if a given identifier (iso2 or iso3) already exists.
     * identifier may be null/empty; returns false in that case.
     */
    public function getByIdentifierExists(?string $identifier): bool
    {
        if (empty($identifier)) return false;
        // check iso2 or iso3
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM countries WHERE iso2 = :v OR iso3 = :v");
        $stmt->execute([':v' => $identifier]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Insert country and optionally its translations.
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO countries (iso2, iso3, name, currency_code)
            VALUES (:iso2, :iso3, :name, :currency_code)
        ");
        $stmt->execute([
            ':iso2' => $data['iso2'] ?? null,
            ':iso3' => $data['iso3'] ?? null,
            ':name' => $data['name'] ?? null,
            ':currency_code' => $data['currency_code'] ?? null
        ]);
        $id = (int)$this->pdo->lastInsertId();

        if (!empty($data['translations']) && is_array($data['translations'])) {
            $this->upsertTranslations($id, $data['translations']);
        }

        return $id;
    }

    /**
     * Update country record and translations (if provided)
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE countries SET
                iso2 = :iso2,
                iso3 = :iso3,
                name = :name,
                currency_code = :currency_code
            WHERE id = :id
        ");
        $ok = $stmt->execute([
            ':iso2' => $data['iso2'] ?? null,
            ':iso3' => $data['iso3'] ?? null,
            ':name' => $data['name'] ?? null,
            ':currency_code' => $data['currency_code'] ?? null,
            ':id' => $id
        ]);

        if (!empty($data['translations']) && is_array($data['translations'])) {
            $this->upsertTranslations($id, $data['translations']);
        }

        return (bool)$ok;
    }

    /**
     * Delete translations and country
     */
    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM country_translations WHERE country_id = :id");
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare("DELETE FROM countries WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Fetch all translations for a given country_id
     */
    public function getTranslations(int $countryId): array
    {
        $stmt = $this->pdo->prepare("SELECT language_code, name FROM country_translations WHERE country_id = :id");
        $stmt->execute([':id' => $countryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upsert translations array for a country.
     */
    private function upsertTranslations(int $countryId, array $translations): void
    {
        $stmtInsert = $this->pdo->prepare("
            INSERT INTO country_translations (country_id, language_code, name)
            VALUES (:country_id, :language_code, :name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");

        foreach ($translations as $t) {
            if (empty($t['language_code']) || !isset($t['name'])) continue;
            $stmtInsert->execute([
                ':country_id' => $countryId,
                ':language_code' => $t['language_code'],
                ':name' => $t['name']
            ]);
        }
    }
}