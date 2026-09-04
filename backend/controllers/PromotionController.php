<?php
/**
 * Controlador HTTP PromotionController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class PromotionController
{
    private const ROLES=['empleado','dueño','admin_general'];

    public function index(): void
    {
        [, $gym,$repo]=$this->admin('promotions.read');$result=$repo->list($gym,$_GET);ApiResponder::success(['items'=>$result['items']],200,['pagination'=>$result['pagination']]);
    }

    public function show(string $id): void
    {
        [, $gym,$repo]=$this->admin('promotions.read');$promotion=$repo->find($gym,(int)$id);if(!$promotion)ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');ApiResponder::success($promotion);
    }

    public function create(): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$data=$this->data($this->body(),$gym,$repo);$created=$repo->create($gym,$data,$actor);AdminAuditLogger::record('promotion.created','promocion','success',$actor,$gym,(string)$created['id'],'Alta en borrador',null,$this->compact($created));ApiResponder::success($created,201);
    }

    public function update(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$data=$this->data($this->body(),$gym,$repo);$result=$repo->update($gym,(int)$id,$data);AdminAuditLogger::record('promotion.updated','promocion','success',$actor,$gym,$id,null,$this->compact($result['before']),$this->compact($result['after']??[]));ApiResponder::success($result['after']??[]);
    }

    public function delete(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$before=$repo->delete($gym,(int)$id);AdminAuditLogger::record('promotion.deleted','promocion','success',$actor,$gym,$id,'Eliminación lógica',$this->compact($before));ApiResponder::success(['id'=>(int)$id,'deleted'=>true]);
    }

    public function schedule(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$result=$repo->schedule($gym,(int)$id);AdminAuditLogger::record('promotion.scheduled','promocion','success',$actor,$gym,$id,'Promoción programada',null,['state'=>$result['promotion']['estado'],'queued'=>$result['queued']]);ApiResponder::success($result);
    }

    public function pause(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$promotion=$repo->pause($gym,(int)$id);AdminAuditLogger::record('promotion.paused','promocion','success',$actor,$gym,$id);ApiResponder::success($promotion);
    }

    public function finish(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('promotions.write');$promotion=$repo->finish($gym,(int)$id);AdminAuditLogger::record('promotion.finished','promocion','success',$actor,$gym,$id);ApiResponder::success($promotion);
    }

    public function results(string $id): void
    {
        [, $gym,$repo]=$this->admin('promotions.read');ApiResponder::success($repo->results($gym,(int)$id));
    }

    private function admin(string $permission): array
    {
        AuthMiddleware::verificarRoles(self::ROLES);AuthMiddleware::verificarPermiso($permission);$actor=AuthMiddleware::obtenerUsuarioId();$gym=AuthMiddleware::requerirContextoGimnasio();return[$actor,$gym,new PromotionRepository(AuthMiddleware::obtenerDemoDatasetId())];
    }

    private function data(array $body,int $gymId,PromotionRepository $repo): array
    {
        foreach(['nombre','descripcion','audiencia','tipo_descuento','codigo_descuento'] as $stringKey){if(isset($body[$stringKey])&&!is_string($body[$stringKey]))ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[$stringKey=>'Ingresá un texto válido.']);}
        $v=new AdminInputValidator($body);$name=$v->requiredString('nombre','El nombre',120,3);$description=$v->requiredString('descripcion','La descripción',5000,10);$audience=$v->enum('audiencia',['todos_socios','socios_activos','socios_con_deuda','socios_inactivos'],'todos_socios');$discount=$v->enum('tipo_descuento',['sin_descuento','porcentaje','monto_fijo'],'sin_descuento');$code=$v->optionalString('codigo_descuento','El código',60);$v->failIfInvalid();
        $timezoneValue=$body['zona_horaria']??$repo->gymTimezone($gymId);if(!is_string($timezoneValue))ApiResponder::error(422,'validation_error','Revisá la zona horaria.',['zona_horaria'=>'Usá una zona horaria IANA válida.']);$timezone=trim($timezoneValue);if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))ApiResponder::error(422,'validation_error','Revisá la zona horaria.',['zona_horaria'=>'Usá una zona horaria IANA válida.']);
        $start=$this->dateTime($body,'inicio_en','El inicio',$timezone);$end=$this->dateTime($body,'fin_en','El fin',$timezone);if($start>=$end)ApiResponder::error(422,'validation_error','Revisá las fechas.',['fin_en'=>'El fin debe ser posterior al inicio.']);
        $channels=$this->channels($body['canales']??null);
        $gymIds=$this->gymIds($body['gimnasio_ids']??[$gymId]);if(!in_array($gymId,$gymIds,true))ApiResponder::error(422,'validation_error','El gimnasio activo debe formar parte de la promoción.',['gimnasio_ids'=>'Incluí el gimnasio activo.']);$authorization=new AuthorizationService();$actor=AuthMiddleware::obtenerUsuarioId();foreach($gymIds as $targetGym){if(!$authorization->canAccessGym($actor,$targetGym)||!$authorization->hasPermission($actor,'promotions.write',$targetGym))ApiResponder::error(403,'gym_scope_mismatch','No tenés permiso para publicar en uno de los gimnasios seleccionados.');}
        $value=null;$currency=null;if($discount!=='sin_descuento'){$rawValue=$body['valor_descuento']??'';if(!is_string($rawValue)&&!is_int($rawValue)&&!is_float($rawValue))ApiResponder::error(422,'validation_error','Revisá el descuento.',['valor_descuento'=>'Ingresá un valor de descuento válido.']);$raw=str_replace(',','.',trim((string)$rawValue));if(!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/',$raw)||(float)$raw<=0||($discount==='porcentaje'&&(float)$raw>100))ApiResponder::error(422,'validation_error','Revisá el descuento.',['valor_descuento'=>'Ingresá un valor de descuento válido.']);$value=number_format((float)$raw,2,'.','');if($discount==='monto_fijo'){$currencyValue=$body['moneda']??'UYU';if(!is_string($currencyValue))ApiResponder::error(422,'validation_error','Revisá la moneda.',['moneda'=>'Usá un código de tres letras.']);$currency=strtoupper(trim($currencyValue));if(!preg_match('/^[A-Z]{3}$/',$currency))ApiResponder::error(422,'validation_error','Revisá la moneda.',['moneda'=>'Usá un código de tres letras.']);}}
        $image=null;if(isset($body['imagen_archivo_id'])&&$body['imagen_archivo_id']!==''){$parsed=filter_var($body['imagen_archivo_id'],FILTER_VALIDATE_INT);if($parsed===false||$parsed<1)ApiResponder::error(422,'validation_error','Revisá la imagen.',['imagen_archivo_id'=>'El archivo no es válido.']);$image=(int)$parsed;}
        return['name'=>$name,'description'=>$description,'audience'=>$audience,'start_at'=>$start,'end_at'=>$end,'timezone'=>$timezone,'discount_type'=>$discount,'discount_value'=>$value,'currency'=>$currency,'discount_code'=>$code===null?null:mb_strtoupper($code),'image_file_id'=>$image,'channels'=>$channels,'gym_ids'=>$gymIds];
    }

    private function channels(mixed $raw): array
    {
        if(!is_array($raw))ApiResponder::error(422,'validation_error','Revisá los canales.',['canales'=>'Usá una lista de canales válida.']);$channels=[];
        foreach($raw as $channel){if(!is_string($channel)||!in_array($channel,['internal','email','whatsapp'],true))ApiResponder::error(422,'validation_error','Revisá los canales.',['canales'=>'Elegí notificación interna, correo o WhatsApp.']);$channels[$channel]=$channel;}
        if($channels===[])ApiResponder::error(422,'validation_error','Revisá los canales.',['canales'=>'Elegí al menos un canal.']);if(isset($channels['whatsapp']))ApiResponder::error(409,'whatsapp_beta_unavailable','WhatsApp sigue en beta y no tiene un adaptador de envío configurado.');return array_values($channels);
    }

    private function gymIds(mixed $raw): array
    {
        if(!is_array($raw))ApiResponder::error(422,'validation_error','Revisá los gimnasios.',['gimnasio_ids'=>'Usá una lista de gimnasios válida.']);$gymIds=[];
        foreach($raw as $gymId){if(!(is_int($gymId)||(is_string($gymId)&&ctype_digit($gymId)))||(int)$gymId<1)ApiResponder::error(422,'validation_error','Revisá los gimnasios.',['gimnasio_ids'=>'Cada gimnasio debe tener un identificador válido.']);$gymIds[(int)$gymId]=(int)$gymId;}
        if($gymIds===[])ApiResponder::error(422,'validation_error','Revisá los gimnasios.',['gimnasio_ids'=>'Seleccioná al menos un gimnasio.']);return array_values($gymIds);
    }

    private function dateTime(array $body,string $key,string $label,string $timezone): string
    {
        if(!isset($body[$key])||!is_string($body[$key]))ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[$key=>"{$label} no es válido."]);$value=str_replace('T',' ',trim($body[$key]));if(strlen($value)===16)$value.=':00';$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone($timezone));if(!$date||$date->format('Y-m-d H:i:s')!==$value)ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[$key=>"{$label} no es válido."]);return$value;
    }
    private function body(): array{try{$decoded=json_decode((string)file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);}catch(JsonException){ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');}if(!is_array($decoded))ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');return$decoded;}
    private function compact(array $row): array{return array_intersect_key($row,array_flip(['id','nombre','estado','audiencia','inicio_en','fin_en','version']));}
}
