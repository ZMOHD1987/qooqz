<?php
declare(strict_types=1);

final class UnitsController
{
    private UnitsService $service;

    public function __construct(UnitsService $service)
    {
        $this->service=$service;
    }

    public function list():array
    {
        return $this->service->list($_GET['lang']??'en');
    }

    public function show(int $id):array
    {
        $lang=$_GET['lang']??'en';
        $all=isset($_GET['all_translations']) && $_GET['all_translations']=='1';
        return $this->service->get($id,$lang,$all);
    }

    public function create(array $data):array
    {
        return $this->service->save($data);
    }

    public function update(array $data):array
    {
        if(empty($data['id'])) throw new InvalidArgumentException('ID required');
        return $this->service->save($data);
    }

    public function delete(array $data):void
    {
        if(empty($data['id'])) throw new InvalidArgumentException('ID required');
        $this->service->delete((int)$data['id']);
    }
}
