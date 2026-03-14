<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);

require_once $baseDir.'/bootstrap.php';
require_once $baseDir.'/shared/core/ResponseFormatter.php';
require_once $baseDir.'/shared/helpers/safe_helpers.php';
require_once $baseDir.'/shared/config/db.php';

require_once API_VERSION_PATH.'/models/units/repositories/PdoUnitsRepository.php';
require_once API_VERSION_PATH.'/models/units/validators/UnitsValidator.php';
require_once API_VERSION_PATH.'/models/units/services/UnitsService.php';
require_once API_VERSION_PATH.'/models/units/controllers/UnitsController.php';

$pdo=$GLOBALS['ADMIN_DB']??null;
if(!$pdo instanceof PDO){
    ResponseFormatter::error('Database not initialized',500);
    return;
}

$repo=new PdoUnitsRepository($pdo);
$service=new UnitsService($repo);
$controller=new UnitsController($service);

try{
    switch($_SERVER['REQUEST_METHOD']){
        case 'GET':
            $id=$_GET['id']??null;
            ResponseFormatter::success($id?$controller->show((int)$id):$controller->list());
            break;

        case 'POST':
            $data=json_decode(file_get_contents('php://input'),true)??[];
            ResponseFormatter::success($controller->create($data));
            break;

        case 'PUT':
            $data=json_decode(file_get_contents('php://input'),true)??[];
            ResponseFormatter::success($controller->update($data));
            break;

        case 'DELETE':
            $data=json_decode(file_get_contents('php://input'),true)??[];
            $controller->delete($data);
            ResponseFormatter::success(['deleted'=>true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed',405);
    }
}catch(InvalidArgumentException $e){
    ResponseFormatter::error($e->getMessage(),422);
}catch(Throwable $e){
    safe_log('error','Units route failed',['error'=>$e->getMessage()]);
    ResponseFormatter::error('Internal server error',500);
}

