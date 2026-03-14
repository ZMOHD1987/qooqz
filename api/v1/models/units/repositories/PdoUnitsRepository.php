<?php
declare(strict_types=1);

final class PdoUnitsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(string $lang='en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.code,
                   COALESCE(ut.name, u.code) AS name
            FROM units u
            LEFT JOIN units_translations ut
                ON ut.unit_id = u.id AND ut.language_code = :lang
            ORDER BY name ASC
        ");

        $stmt->execute([':lang'=>$lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, code
            FROM units
            WHERE id=:id
            LIMIT 1
        ");
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findWithTranslation(int $id,string $lang='en',bool $allTranslations=false): ?array
    {
        if ($allTranslations) {
            $row=$this->find($id);
            if($row){
                $row['translations']=$this->getTranslations($id);
            }
            return $row;
        }

        $stmt=$this->pdo->prepare("
            SELECT u.id,u.code,u.tenant_id,
                   COALESCE(ut.name,u.code) AS name
            FROM units u
            LEFT JOIN units_translations ut
                ON ut.unit_id=u.id AND ut.language_code=:lang
            WHERE u.id=:id
            LIMIT 1
        ");
        $stmt->execute([':lang'=>$lang,':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): int
    {
        if(!empty($data['id'])){
            $stmt=$this->pdo->prepare("
                UPDATE units
                SET tenant_id=:tenant_id,
                    code=:code
                WHERE id=:id
            ");
            $stmt->execute([
                ':tenant_id'=>$data['tenant_id']??null,
                ':code'=>$data['code'],
                ':id'=>$data['id']
            ]);
            return (int)$data['id'];
        }

        $stmt=$this->pdo->prepare("
            INSERT INTO units (tenant_id,code)
            VALUES (:tenant_id,:code)
        ");
        $stmt->execute([
            ':tenant_id'=>$data['tenant_id']??null,
            ':code'=>$data['code']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("DELETE FROM units_translations WHERE unit_id=:id")
                      ->execute([':id'=>$id]);

            $stmt=$this->pdo->prepare("DELETE FROM units WHERE id=:id");
            $stmt->execute([':id'=>$id]);

            $this->pdo->commit();
            return true;
        }catch(Throwable){
            $this->pdo->rollBack();
            return false;
        }
    }

    public function saveTranslations(int $unitId,array $translations): void
    {
        $stmt=$this->pdo->prepare("
            INSERT INTO units_translations (unit_id,language_code,name)
            VALUES (:id,:lang,:name)
            ON DUPLICATE KEY UPDATE name=VALUES(name)
        ");

        foreach($translations as $lang=>$name){
            $stmt->execute([
                ':id'=>$unitId,
                ':lang'=>$lang,
                ':name'=>$name
            ]);
        }
    }

    public function getTranslations(int $unitId): array
    {
        $stmt=$this->pdo->prepare("
            SELECT language_code,name
            FROM units_translations
            WHERE unit_id=:id
        ");
        $stmt->execute([':id'=>$unitId]);

        $out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $out[$row['language_code']]=$row['name'];
        }
        return $out;
    }
}
