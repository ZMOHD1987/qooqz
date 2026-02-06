<?php
declare(strict_types=1);

// api/v1/models/cities/repositories/PdoCitiesRepository.php

final class PdoCitiesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(string $lang = 'en', ?int $countryId = null, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [':lang' => $lang, ':perPage' => $perPage, ':offset' => $offset];

        if ($countryId) {
            $where = 'WHERE ci.country_id = :countryId';
            $params[':countryId'] = $countryId;
        }

        $sql = "
            SELECT ci.id, ci.country_id, ci.state, ci.latitude, ci.longitude,
                   COALESCE(ct.name, ci.name) AS name
            FROM cities ci
            LEFT JOIN city_translations ct 
                ON ci.id = ct.city_id AND ct.language_code = :lang
            $where
            ORDER BY name ASC
            LIMIT :perPage OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalCount(?int $countryId = null): int
    {
        $sql = "SELECT COUNT(*) AS total FROM cities";
        $params = [];

        if ($countryId) {
            $sql .= " WHERE country_id = :countryId";
            $params[':countryId'] = $countryId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, country_id, name, state, latitude, longitude
            FROM cities
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findWithTranslation(int $id, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->find($id);
            if ($row) {
                $row['translations'] = $this->getTranslations($id);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT ci.id, ci.country_id, ci.state, ci.latitude, ci.longitude,
                   COALESCE(ct.name, ci.name) AS name
            FROM cities ci
            LEFT JOIN city_translations ct 
                ON ci.id = ct.city_id AND ct.language_code = :lang
            WHERE ci.id = :id
            LIMIT 1
        ");

        $stmt->execute([':lang' => $lang, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE cities
                SET country_id = :country_id,
                    name = :name,
                    state = :state,
                    latitude = :latitude,
                    longitude = :longitude
                WHERE id = :id
            ");

            $stmt->execute([
                ':country_id' => (int)$data['country_id'],
                ':name'       => $data['name'],
                ':state'      => $data['state'] ?? null,
                ':latitude'   => $data['latitude'] ?? null,
                ':longitude'  => $data['longitude'] ?? null,
                ':id'         => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cities
                (country_id, name, state, latitude, longitude)
            VALUES
                (:country_id, :name, :state, :latitude, :longitude)
        ");

        $stmt->execute([
            ':country_id' => (int)$data['country_id'],
            ':name'       => $data['name'],
            ':state'      => $data['state'] ?? null,
            ':latitude'   => $data['latitude'] ?? null,
            ':longitude'  => $data['longitude'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("DELETE FROM city_translations WHERE city_id = :id")->execute([':id' => $id]);
            $stmt = $this->pdo->prepare("DELETE FROM cities WHERE id = :id");
            $result = $stmt->execute([':id' => $id]);

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function saveTranslations(int $cityId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO city_translations (city_id, language_code, name)
            VALUES (:city_id, :lang, :name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");

        foreach ($translations as $lang => $name) {
            $stmt->execute([
                ':city_id' => $cityId,
                ':lang'    => $lang,
                ':name'    => $name
            ]);
        }
    }

    public function getTranslations(int $cityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name
            FROM city_translations
            WHERE city_id = :city_id
        ");

        $stmt->execute([':city_id' => $cityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = $row['name'];
        }

        return $translations;
    }
}