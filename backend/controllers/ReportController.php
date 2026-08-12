<?php

declare(strict_types=1);

final class ReportController
{
    private const ROLES=['empleado','dueño','admin_general'];
    public function generate(): void
    {
        [$user,$gym,$dataset]=$this->authorize();$body=$this->body();$type=(string)($body['type']??'');$module=(string)($body['module']??'');if(!in_array($type,['xlsx','pdf'],true))ApiResponder::error(422,'validation_error','Elegí Excel o PDF.',['type'=>'El formato no es válido.']);if(!in_array($module,['payments','finance'],true))ApiResponder::error(422,'validation_error','Elegí un reporte válido.',['module'=>'El módulo no es válido.']);$filters=is_array($body['filters']??null)?$body['filters']:[];$repo=new ReportRepository($dataset);$id=$repo->create($gym,$user,$type,$module,$filters);
        try{$report=(new PaymentRepository($dataset))->reportRows($gym,$module,$filters);$dir=$this->storage();$name='gymtrack-'.$module.'-'.date('Ymd-His').'-'.$id.'.'.$type;$path=$dir.'/'.$name;if($type==='xlsx')(new SpreadsheetReportWriter())->write($path,$report);else(new PdfReportWriter())->write($path,$report);$size=(int)filesize($path);$mime=$type==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'application/pdf';$repo->complete($id,$path,$name,$mime,$size);AdminAuditLogger::record('report.generated','export','success',$user,$gym,(string)$id,null,null,['module'=>$module,'type'=>$type,'rows'=>count($report['rows'])]);ApiResponder::success(['id'=>$id,'state'=>'completado','filename'=>$name,'size'=>$size,'download_url'=>'/api/admin/exports/'.$id.'/download'],201);}catch(Throwable $error){$repo->fail($id);error_log('[GymTrack report] '.$error->getMessage());ApiResponder::error(500,'report_generation_failed','No se pudo generar el reporte. Intentá nuevamente.');}
    }
    public function download(string $id): void
    {
        [$user,$gym,$dataset]=$this->authorize();$row=(new ReportRepository($dataset))->find($gym,(int)$id);if(!$row)ApiResponder::error(404,'export_not_found','La exportación no pertenece al gimnasio activo.');if($row['estado']!=='completado'||empty($row['archivo_path']))ApiResponder::error(409,'export_not_ready','La exportación todavía no está disponible.');if(!empty($row['expira_en'])&&strtotime((string)$row['expira_en'])<time())ApiResponder::error(410,'export_expired','La exportación venció. Generá una nueva.');$base=realpath($this->storage());$path=realpath((string)$row['archivo_path']);if($base===false||$path===false||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path))ApiResponder::error(404,'export_file_missing','El archivo ya no está disponible.');AdminAuditLogger::record('report.downloaded','export','success',$user,$gym,$id);header('Content-Type: '.($row['mime_type']?:'application/octet-stream'));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode((string)$row['archivo_nombre']));header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');readfile($path);exit;
    }
    private function authorize(): array{AuthMiddleware::verificarRoles(self::ROLES);AuthMiddleware::verificarPermiso('reports.export');return [AuthMiddleware::obtenerUsuarioId(),AuthMiddleware::requerirContextoGimnasio(),AuthMiddleware::obtenerDemoDatasetId()];}
    private function storage(): string{$base=rtrim((string)(getenv('UPLOAD_STORAGE_PATH')?:'/var/lib/gymtrack/uploads'),'/').'/reports';if(!is_dir($base)&&!mkdir($base,0750,true)&&!is_dir($base))throw new RuntimeException('No se pudo preparar el almacenamiento de reportes.');return $base;}
    private function body(): array{$decoded=json_decode((string)file_get_contents('php://input'),true);if(!is_array($decoded))ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');return $decoded;}
}
