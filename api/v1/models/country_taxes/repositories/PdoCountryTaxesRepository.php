<?php
declare(strict_types=1);

// api/v1/models/country_taxes/repositories/PdoCountryTaxesRepository.php

final class PdoCountryTaxesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $countryId = null, ?int $taxClassId = null): array
    {
        $sql = "
            SELECT ct.id, ct.country_id, ct.tax_class_id, ct.tax_name, ct.tax_name_ar, ct.tax_type, ct.tax_rate, ct.is_inclusive, ct.is_active, ct.effective_date, ct.created_at,
                   c.name as country_name, c.iso2 as country_iso2, c.iso3 as country_iso3, c.currency_code as country_currency_code
            FROM country_taxes ct
            LEFT JOIN countries c ON ct.country_id = c.id
            WHERE 1=1
        ";

        $params = [];

        if ($countryId) {
            $sql .= " AND ct.country_id = :countryId";
            $params[':countryId'] = $countryId;
        }

        if ($taxClassId) {
            $sql .= " AND ct.tax_class_id = :taxClassId";
            $params[':taxClassId'] = $taxClassId;
        }

        $sql .= " ORDER BY ct.country_id ASC, ct.tax_class_id ASC, ct.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, c.name as country_name, c.iso2 as country_iso2, c.iso3 as country_iso3, c.currency_code as country_currency_code
            FROM country_taxes ct
            LEFT JOIN countries c ON ct.country_id = c.id
            WHERE ct.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCountryAndClass(int $countryId, int $taxClassId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, c.name as country_name, c.iso2 as country_iso2, c.iso3 as country_iso3, c.currency_code as country_currency_code
            FROM country_taxes ct
            LEFT JOIN countries c ON ct.country_id = c.id
            WHERE ct.country_id = :countryId AND ct.tax_class_id = :taxClassId
            LIMIT 1
        ");

        $stmt->execute([':countryId' => $countryId, ':taxClassId' => $taxClassId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE country_taxes
                SET country_id = :country_id,
                    tax_class_id = :tax_class_id,
                    tax_name = :tax_name,
                    tax_name_ar = :tax_name_ar,
                    tax_type = :tax_type,
                    tax_rate = :tax_rate,
                    is_inclusive = :is_inclusive,
                    is_active = :is_active,
                    effective_date = :effective_date
                WHERE id = :id
            ");

            $stmt->execute([
                ':country_id'    => (int)$data['country_id'],
                ':tax_class_id'  => (int)$data['tax_class_id'],
                ':tax_name'      => $data['tax_name'],
                ':tax_name_ar'   => $data['tax_name_ar'],
                ':tax_type'      => $data['tax_type'] ?? 'vat',
                ':tax_rate'      => (float)$data['tax_rate'],
                ':is_inclusive'  => (int)($data['is_inclusive'] ?? 0),
                ':is_active'     => (int)($data['is_active'] ?? 1),
                ':effective_date' => $data['effective_date'] ?? null,
                ':id'            => (int)$data['id']
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO country_taxes
                    (country_id, tax_class_id, tax_name, tax_name_ar, tax_type, tax_rate, is_inclusive, is_active, effective_date, created_at)
                VALUES
                    (:country_id, :tax_class_id, :tax_name, :tax_name_ar, :tax_type, :tax_rate, :is_inclusive, :is_active, :effective_date, NOW())
            ");

            $stmt->execute([
                ':country_id'    => (int)$data['country_id'],
                ':tax_class_id'  => (int)$data['tax_class_id'],
                ':tax_name'      => $data['tax_name'],
                ':tax_name_ar'   => $data['tax_name_ar'],
                ':tax_type'      => $data['tax_type'] ?? 'vat',
                ':tax_rate'      => (float)$data['tax_rate'],
                ':is_inclusive'  => (int)($data['is_inclusive'] ?? 0),
                ':is_active'     => (int)($data['is_active'] ?? 1),
                ':effective_date' => $data['effective_date'] ?? null
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);

        if (!$oldData) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM country_taxes
            WHERE id = :id
        ");

        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId && $result) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode([
                'old' => $oldData,
                'new' => $newData
            ]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        // Assuming entity_logs table exists and tenant_id is optional (set to 0 for global)
        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (0, :userId, 'country_tax', :entityId, :action, :changes, :ip, NOW())
        ");

        $stmt->execute([
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}