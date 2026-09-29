<?php
/**
 * Acceso a datos ReporteRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class ReporteRepository
{
    private PDO $pdo;private ?int $datasetId;
    public function __construct(?int $datasetId){$this->pdo=Database::conectar();$this->datasetId=$datasetId;}
    public function create(int $gymId,int $userId,string $type,string $module,array $filters): int{$stmt=$this->pdo->prepare('INSERT INTO exports (usuario_id,gimnasio_id,tipo,modulo,filtros_json,estado,request_id,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,"procesando",?,?,?)');$stmt->execute([$userId,$gymId,$type,$module,json_encode($filters,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),ApiResponder::requestId(),$this->demoFlag(),$this->datasetId]);return (int)$this->pdo->lastInsertId();}
    public function complete(int $id,string $path,string $name,string $mime,int $size): void{$stmt=$this->pdo->prepare('UPDATE exports SET estado="completado",archivo_path=?,archivo_nombre=?,mime_type=?,tamano_bytes=?,completado_en=NOW(),expira_en=DATE_ADD(NOW(),INTERVAL 24 HOUR),error_seguro=NULL WHERE id=?');$stmt->execute([$path,$name,$mime,$size,$id]);}
    public function fail(int $id): void{$this->pdo->prepare('UPDATE exports SET estado="fallido",error_seguro="No se pudo generar el archivo solicitado.",completado_en=NOW() WHERE id=?')->execute([$id]);}
    public function find(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT * FROM exports WHERE id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?:null;}
    private function demoFlag(): int{return $this->datasetId===null?0:1;}
}
