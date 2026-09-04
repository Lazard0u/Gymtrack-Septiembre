<?php
/**
 * Controlador HTTP AdminFileController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AdminFileController
{
    private const MAX_BYTES=5_242_880;
    private const IMAGE_MIMES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public function upload(): void
    {
        AuthMiddleware::verificarRoles(['dueño','admin_general']);AuthMiddleware::verificarPermiso('gym.configure');$gymId=AuthMiddleware::requerirContextoGimnasio();$userId=AuthMiddleware::obtenerUsuarioId();$datasetId=AuthMiddleware::obtenerDemoDatasetId();
        $repo=new AdminManagementRepository($datasetId);if(!$repo->gym($gymId))ApiResponder::error(403,'gym_scope_mismatch','El gimnasio activo no pertenece al conjunto de datos de tu sesión.');
        $category=(string)($_POST['categoria']??'');if(!in_array($category,['gimnasio_imagen','entrenador_foto','promocion_imagen','documento'],true))ApiResponder::error(422,'validation_error','Seleccioná una categoría válida.',['categoria'=>'Categoría no permitida.']);
        $file=$_FILES['archivo']??null;if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)ApiResponder::error(422,'file_required','Seleccioná un archivo válido.',['archivo'=>'No se recibió el archivo.']);
        $size=(int)($file['size']??0);if($size<1||$size>self::MAX_BYTES)ApiResponder::error(422,'file_too_large','El archivo debe pesar menos de 5 MB.',['archivo'=>'Tamaño máximo: 5 MB.']);
        $temporary=(string)$file['tmp_name'];$mime=(new finfo(FILEINFO_MIME_TYPE))->file($temporary)?:'';$allowed=self::IMAGE_MIMES;
        if($category==='documento')$allowed+=['application/pdf'=>'pdf'];
        if(!isset($allowed[$mime]))ApiResponder::error(422,'file_type_not_allowed','El tipo de archivo no está permitido.',['archivo'=>'Usá JPG, PNG, WebP'.($category==='documento'?' o PDF.':'.')]);
        if(isset(self::IMAGE_MIMES[$mime])){$dimensions=@getimagesize($temporary);if(!$dimensions||$dimensions[0]<64||$dimensions[1]<64||$dimensions[0]>8000||$dimensions[1]>8000)ApiResponder::error(422,'invalid_image','La imagen no tiene dimensiones válidas.',['archivo'=>'Entre 64×64 y 8000×8000 px.']);}
        $key=sprintf('%s/%d/%s/%s.%s',$datasetId===null?'real':'demo-'.$datasetId,$gymId,$category,bin2hex(random_bytes(20)),$allowed[$mime]);$storage=new LocalFileStorage();$storage->putUploaded($temporary,$key);
        try{$pdo=Database::conectar();$stmt=$pdo->prepare('INSERT INTO archivos (gimnasio_id,categoria,nombre_original,storage_key,mime_type,tamano_bytes,sha256,estado,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,?,?,"activo",?,?,?)');$name=mb_substr(basename((string)$file['name']),0,255);$stmt->execute([$gymId,$category,$name,$key,$mime,$size,hash_file('sha256',$storage->path($key)),AuthMiddleware::esCuentaDemo()?1:0,$datasetId,$userId]);$id=(int)$pdo->lastInsertId();if($category==='gimnasio_imagen')$pdo->prepare('UPDATE gimnasios SET imagen_path=? WHERE id=?')->execute(['/api/public/files/'.$id,$gymId]);AdminAuditLogger::record('file.uploaded','archivo','success',$userId,$gymId,(string)$id,$category,null,['mime_type'=>$mime,'size'=>$size]);ApiResponder::success(['id'=>$id,'categoria'=>$category,'url'=>$category==='gimnasio_imagen'?'/api/public/files/'.$id:'/api/admin/files/'.$id],201);}catch(Throwable $error){$storage->delete($key);throw $error;}
    }

    public function show(string $id): void
    {
        AuthMiddleware::verificarRoles(['empleado','dueño','admin_general']);AuthMiddleware::verificarPermiso('gyms.read');$gymId=AuthMiddleware::requerirContextoGimnasio();$this->stream((int)$id,$gymId,false);
    }

    public function publicShow(string $id): void
    {
        $this->stream((int)$id,null,true);
    }

    private function stream(int $id,?int $gymId,bool $public): never
    {
        $sql='SELECT a.storage_key,a.mime_type,a.nombre_original,a.gimnasio_id FROM archivos a JOIN gimnasios g ON g.id=a.gimnasio_id WHERE a.id=? AND a.estado="activo"';$params=[$id];
        if($public){$context=(new SystemContext())->obtenerDatasetActivo();$sql.=' AND a.categoria="gimnasio_imagen" AND g.estado IN ("publicado","temporalmente_cerrado") AND g.is_demo=? AND (g.demo_dataset_id <=> ?)';array_push($params,$context===null?0:1,$context['id']??null);}else{$sql.=' AND a.is_demo=? AND (a.demo_dataset_id <=> ?) AND (a.gimnasio_id=? OR (a.categoria="promocion_imagen" AND EXISTS(SELECT 1 FROM promociones p JOIN promocion_gimnasios pg ON pg.promocion_id=p.id WHERE p.imagen_archivo_id=a.id AND pg.gimnasio_id=? AND p.eliminado_en IS NULL)))';array_push($params,AuthMiddleware::esCuentaDemo()?1:0,AuthMiddleware::obtenerDemoDatasetId(),$gymId,$gymId);}
        $stmt=Database::conectar()->prepare($sql.' LIMIT 1');$stmt->execute($params);$file=$stmt->fetch();if(!$file){http_response_code(404);echo json_encode(['error'=>true,'mensaje'=>'Archivo no encontrado.']);exit;}$path=(new LocalFileStorage())->path($file['storage_key']);if(!is_file($path)){http_response_code(404);echo json_encode(['error'=>true,'mensaje'=>'Archivo no disponible.']);exit;}
        header('Content-Type: '.$file['mime_type']);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($file['nombre_original'])).'"');header('Cache-Control: private, max-age=300');readfile($path);exit;
    }
}
