<?php
declare(strict_types=1);

require_once __DIR__.'/../repositories/PdoUnitsRepository.php';
require_once __DIR__.'/../validators/UnitsValidator.php';

final class UnitsService
{
    private PdoUnitsRepository $repo;

    public function __construct(PdoUnitsRepository $repo)
    {
        $this->repo=$repo;
    }

    public function list(string $lang='en'): array
    {
        return [
            'success'=>true,
            'data'=>$this->repo->all($lang)
        ];
    }

    public function get(int $id,string $lang='en',bool $allTranslations=false): array
    {
        $row=$this->repo->findWithTranslation($id,$lang,$allTranslations);
        if(!$row) throw new RuntimeException('Unit not found');

        return ['success'=>true,'data'=>$row];
    }

    public function save(array $data): array
    {
        $errors=UnitsValidator::validate($data);
        if($errors) throw new InvalidArgumentException(json_encode($errors,JSON_UNESCAPED_UNICODE));

        $id=$this->repo->save($data);

        if(!empty($data['translations'])){
            $this->repo->saveTranslations($id,$data['translations']);
        }

        return ['success'=>true,'data'=>$this->repo->find($id)];
    }

    public function delete(int $id): void
    {
        if(!$this->repo->delete($id)){
            throw new RuntimeException('Delete failed');
        }
    }
}
