<?php
declare(strict_types=1);

final class PdoBadWordsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'word', 'severity', 'is_active', 'created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT bw.* FROM bad_words bw WHERE 1=1";
        $params = [];

        if (isset($filters['severity']) && in_array($filters['severity'], ['low', 'medium', 'high'], true)) {
            $sql .= " AND bw.severity = :severity";
            $params[':severity'] = $filters['severity'];
        }

        if (isset($filters['is_active']) && in_array((int)$filters['is_active'], [0, 1], true)) {
            $sql .= " AND bw.is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (bw.word LIKE :search OR EXISTS (
                SELECT 1 FROM bad_word_translations bwt
                WHERE bwt.bad_word_id = bw.id AND bwt.word LIKE :search_t
            ))";
            $params[':search']   = '%' . trim($filters['search']) . '%';
            $params[':search_t'] = '%' . trim($filters['search']) . '%';
        }

        if (isset($filters['language_code']) && $filters['language_code'] !== '') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM bad_word_translations bwt
                WHERE bwt.bad_word_id = bw.id AND bwt.language_code = :language_code
            )";
            $params[':language_code'] = $filters['language_code'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY bw.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM bad_words bw WHERE 1=1";
        $params = [];

        if (isset($filters['severity']) && in_array($filters['severity'], ['low', 'medium', 'high'], true)) {
            $sql .= " AND bw.severity = :severity";
            $params[':severity'] = $filters['severity'];
        }

        if (isset($filters['is_active']) && in_array((int)$filters['is_active'], [0, 1], true)) {
            $sql .= " AND bw.is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (bw.word LIKE :search OR EXISTS (
                SELECT 1 FROM bad_word_translations bwt
                WHERE bwt.bad_word_id = bw.id AND bwt.word LIKE :search_t
            ))";
            $params[':search']   = '%' . trim($filters['search']) . '%';
            $params[':search_t'] = '%' . trim($filters['search']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID (with translations)
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bad_words WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $row['translations'] = $this->getTranslations($id);
        return $row;
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO bad_words (word, severity, is_regex, is_active)
            VALUES (:word, :severity, :is_regex, :is_active)
        ");

        $stmt->execute([
            ':word'      => $data['word'],
            ':severity'  => $data['severity'] ?? 'medium',
            ':is_regex'  => isset($data['is_regex']) ? (int)$data['is_regex'] : 0,
            ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        $allowed = ['word', 'severity', 'is_regex', 'is_active'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        $sql = "UPDATE bad_words SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        // Translations are deleted via ON DELETE CASCADE
        $stmt = $this->pdo->prepare("DELETE FROM bad_words WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Translations CRUD
    // ================================
    public function getTranslations(int $badWordId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bad_word_translations
            WHERE bad_word_id = :bad_word_id
        ");
        $stmt->execute([':bad_word_id' => $badWordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslation(array $data): int
    {
        // Upsert: check if translation exists for this bad_word_id + language_code
        $stmt = $this->pdo->prepare("
            SELECT id FROM bad_word_translations
            WHERE bad_word_id = :bad_word_id AND language_code = :language_code
        ");
        $stmt->execute([
            ':bad_word_id'    => $data['bad_word_id'],
            ':language_code'  => $data['language_code'],
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $this->pdo->prepare("
                UPDATE bad_word_translations
                SET word = :word
                WHERE id = :id
            ");
            $stmt->execute([
                ':id'   => (int)$existingId,
                ':word' => $data['word'],
            ]);
            return (int)$existingId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO bad_word_translations (bad_word_id, language_code, word)
            VALUES (:bad_word_id, :language_code, :word)
        ");
        $stmt->execute([
            ':bad_word_id'   => $data['bad_word_id'],
            ':language_code' => $data['language_code'],
            ':word'          => $data['word'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function deleteTranslation(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM bad_word_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Get all active words for filtering (cached per request)
    // ================================
    public function getAllActiveWords(?string $languageCode = null): array
    {
        $sql = "
            SELECT bw.id, bw.word, bw.severity, bw.is_regex
            FROM bad_words bw
            WHERE bw.is_active = 1
        ";
        $params = [];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Also get translated words
        $sqlT = "
            SELECT bwt.word, bw.severity, bw.is_regex, bwt.language_code
            FROM bad_word_translations bwt
            INNER JOIN bad_words bw ON bwt.bad_word_id = bw.id
            WHERE bw.is_active = 1
        ";
        $paramsT = [];

        if ($languageCode !== null && $languageCode !== '') {
            $sqlT .= " AND bwt.language_code = :language_code";
            $paramsT[':language_code'] = $languageCode;
        }

        $stmtT = $this->pdo->prepare($sqlT);
        $stmtT->execute($paramsT);
        $translations = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        return [
            'words'        => $words,
            'translations' => $translations,
        ];
    }
}