<?php
/**
 * Controlador HTTP AgendaController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AgendaController
{
    private const ADMIN_ROLES = ['empleado','dueño','admin_general'];

    public function adminSessions(): void
    {
        [, $gymId,$repo]=$this->adminAny(['classes.read','reservations.read']);$result=$repo->sessions($gymId,AdminRepository::query($_GET));ApiResponder::success(['items'=>$result['items'],'range'=>$result['range']],200,['pagination'=>$result['pagination']]);
    }

    public function adminOptions(): void
    {
        [, $gymId,$repo]=$this->adminAny(['classes.read','reservations.read']);ApiResponder::success($repo->options($gymId));
    }

    public function createClass(): void
    {
        [$actor,$gymId,$repo]=$this->admin('classes.write');$data=$this->classData($this->body(),true);$created=$repo->createClass($gymId,$data,$actor);AdminAuditLogger::record('class.created','clase','success',$actor,$gymId,(string)$created['id'],'Alta de clase recurrente',null,['sessions_created'=>$created['sessions_created']]);ApiResponder::success($created,201);
    }

    public function updateClass(string $id): void
    {
        [$actor,$gymId,$repo]=$this->admin('classes.write');$data=$this->classData($this->body(),false);$result=$repo->updateClass($gymId,(int)$id,$data);AdminAuditLogger::record('class.updated','clase','success',$actor,$gymId,$id,null,$this->compact($result['before']??[]),$this->compact($result['after']??[]));ApiResponder::success($result['after']??[]);
    }

    public function createSession(string $classId): void
    {
        [$actor,$gymId,$repo]=$this->admin('classes.write');$data=$this->sessionData($this->body());$created=$repo->createSession($gymId,(int)$classId,$data,$actor);AdminAuditLogger::record('class.session.created','sesion_clase','success',$actor,$gymId,(string)($created['id']??''),null,null,$this->compact($created));ApiResponder::success($created,201);
    }

    public function updateSession(string $id): void
    {
        [$actor,$gymId,$repo]=$this->admin('classes.write');$data=$this->sessionData($this->body());$result=$repo->updateSession($gymId,(int)$id,$data);AdminAuditLogger::record('class.session.updated','sesion_clase','success',$actor,$gymId,$id,null,$this->compact($result['before']??[]),$this->compact($result['after']??[]));ApiResponder::success($result['after']??[]);
    }

    public function cancelSession(string $id): void
    {
        [$actor,$gymId,$repo]=$this->admin('classes.write');$v=new AdminInputValidator($this->body());$reason=$v->requiredString('motivo','El motivo',255,8);$v->failIfInvalid();$result=$repo->cancelSession($gymId,(int)$id,$reason,$actor);AdminAuditLogger::record('class.session.cancelled','sesion_clase','success',$actor,$gymId,$id,$reason);ApiResponder::success($result);
    }

    public function roster(string $id): void
    {
        [, $gymId,$repo]=$this->admin('reservations.read');ApiResponder::success($repo->roster($gymId,(int)$id));
    }

    public function adminBook(string $sessionId): void
    {
        [$actor,$gymId,$repo]=$this->admin('reservations.write');$body=$this->body();$v=new AdminInputValidator($body);$memberId=$v->integer('usuario_id','El socio',1,PHP_INT_MAX);$v->failIfInvalid();$result=$repo->book($gymId,(int)$sessionId,$memberId,$actor,'administracion',$this->idempotencyKey());AdminAuditLogger::record('booking.created','reserva','success',$actor,$gymId,(string)($result['booking']['id']??''),$result['booking']['estado']??null);ApiResponder::success($result,201);
    }

    public function adminCancelBooking(string $id): void
    {
        [$actor,$gymId,$repo]=$this->admin('reservations.write');$v=new AdminInputValidator($this->body());$reason=$v->requiredString('motivo','El motivo',255,4);$v->failIfInvalid();$result=$repo->cancelBooking($gymId,(int)$id,$actor,null,$reason,true);AdminAuditLogger::record('booking.cancelled','reserva','success',$actor,$gymId,$id,$reason);ApiResponder::success($result);
    }

    public function attendance(string $id): void
    {
        [$actor,$gymId,$repo]=$this->admin('attendance.write');$body=$this->body();$v=new AdminInputValidator($body);$state=$v->enum('estado',['presente','ausente','tarde','justificada'],'presente');$notes=$v->optionalString('notas','Las notas',255);$v->failIfInvalid();$result=$repo->attendance($gymId,(int)$id,$state,$notes,$actor);AdminAuditLogger::record('attendance.recorded','reserva','success',$actor,$gymId,$id,$state);ApiResponder::success($result);
    }

    public function memberSessions(): void
    {
        AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('classes.read');$userId=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();$repo=new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId());$result=$repo->sessions($gymId,AdminRepository::query($_GET),$userId);ApiResponder::success(['items'=>$result['items'],'range'=>$result['range']],200,['pagination'=>$result['pagination']]);
    }

    public function myBookings(): void
    {
        AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('reservations.read');$userId=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();ApiResponder::success(['items'=>(new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId()))->myBookings($gymId,$userId)]);
    }

    public function memberBook(string $sessionId): void
    {
        AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('reservations.write');$userId=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();$result=(new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId()))->book($gymId,(int)$sessionId,$userId,$userId,'socio',$this->idempotencyKey());ApiResponder::success($result,201);
    }

    public function memberCancel(string $id): void
    {
        AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('reservations.write');$userId=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();$body=$this->body(false);$reason=trim((string)($body['motivo']??'Cancelada por el socio'));$result=(new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId()))->cancelBooking($gymId,(int)$id,$userId,$userId,mb_substr($reason,0,255));ApiResponder::success($result);
    }

    private function admin(string $permission): array
    {
        AuthMiddleware::verificarRoles(self::ADMIN_ROLES);AuthMiddleware::verificarPermiso($permission);$gymId=AuthMiddleware::requerirContextoGimnasio();return [AuthMiddleware::obtenerUsuarioId(),$gymId,new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId())];
    }

    private function adminAny(array $permissions): array
    {
        AuthMiddleware::verificarRoles(self::ADMIN_ROLES);$actor=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();$authorization=new AuthorizationService();foreach($permissions as $permission){if($authorization->hasPermission($actor,$permission,$gymId))return [$actor,$gymId,new AgendaRepository(AuthMiddleware::obtenerDemoDatasetId())];}ApiResponder::error(403,'permission_denied','No tenés el permiso requerido para consultar la agenda.');
    }

    private function classData(array $body,bool $creating): array
    {
        $v=new AdminInputValidator($body);$name=$v->requiredString('nombre','El nombre',100,2);$description=$v->optionalString('descripcion','La descripción',600);$trainer=$v->integer('instructor_id','El entrenador',1,PHP_INT_MAX);$location=$v->integer('sede_id','La sede',1,PHP_INT_MAX);$capacity=$v->integer('cupo_maximo','El cupo',1,500);$cancel=$v->integer('cancelacion_minutos','La anticipación de cancelación',0,10080);$weekday=$v->enum('dia_semana',['lunes','martes','miercoles','jueves','viernes','sabado','domingo'],'lunes');$repeat=$creating?$v->integer('repeat_weeks','La cantidad de semanas',1,52):1;$first=$creating?$v->date('first_date','La primera fecha'):null;$start=$this->time($body,'hora_inicio','La hora de inicio');$end=$this->time($body,'hora_fin','La hora de fin');if($start>=$end)ApiResponder::error(422,'validation_error','Revisá el horario.',['hora_fin'=>'La hora de fin debe ser posterior al inicio.']);$color=$this->color($body);$notes=$v->optionalString('notas','Las notas',600);$v->failIfInvalid();
        if($creating&&$first!==null&&$first<(new DateTimeImmutable('today'))->format('Y-m-d'))ApiResponder::error(422,'validation_error','La primera fecha no puede estar en el pasado.',['first_date'=>'Elegí una fecha desde hoy.']);
        return ['name'=>$name,'description'=>$description,'instructor_id'=>$trainer,'sede_id'=>$location,'capacity'=>$capacity,'cancellation_minutes'=>$cancel,'weekday'=>$weekday,'repeat_weeks'=>$repeat,'first_date'=>$first,'start_time'=>$start,'end_time'=>$end,'color'=>$color,'notes'=>$notes,'active'=>$v->boolean('activa')||$creating];
    }

    private function sessionData(array $body): array
    {
        $v=new AdminInputValidator($body);$trainer=$v->integer('instructor_id','El entrenador',1,PHP_INT_MAX);$location=$v->integer('sede_id','La sede',1,PHP_INT_MAX);$capacity=$v->integer('cupo_maximo','El cupo',1,500);$notes=$v->optionalString('notas','Las notas',600);$v->failIfInvalid();$start=$this->dateTime($body,'inicio_en','El inicio');$end=$this->dateTime($body,'fin_en','El fin');if($start>=$end)ApiResponder::error(422,'validation_error','Revisá el horario.',['fin_en'=>'El fin debe ser posterior al inicio.']);return ['instructor_id'=>$trainer,'sede_id'=>$location,'capacity'=>$capacity,'notes'=>$notes,'start_at'=>$start,'end_at'=>$end];
    }

    private function time(array $body,string $key,string $label): string{$value=trim((string)($body[$key]??''));if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$value))ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[$key=>"{$label} no es válida."]);return $value;}
    private function dateTime(array $body,string $key,string $label): string{$value=str_replace('T',' ',trim((string)($body[$key]??'')));if(strlen($value)===16)$value.=':00';$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);if(!$date||$date->format('Y-m-d H:i:s')!==$value)ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[$key=>"{$label} no es válido."]);return $value;}
    private function color(array $body): string{$value=strtoupper(trim((string)($body['color']??'#0D7A56')));if(!preg_match('/^#[0-9A-F]{6}$/',$value))ApiResponder::error(422,'validation_error','Revisá los campos indicados.',['color'=>'Usá un color hexadecimal válido.']);return $value;}
    private function idempotencyKey(): ?string{$key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));if($key==='')return null;if(!preg_match('/^[a-f0-9-]{36}$/i',$key))ApiResponder::error(422,'invalid_idempotency_key','Idempotency-Key debe ser un UUID válido.');return strtolower($key);}
    private function body(bool $required=true): array{$raw=(string)file_get_contents('php://input');if($raw===''&&!$required)return [];$decoded=json_decode($raw,true);if(!is_array($decoded))ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');return $decoded;}
    private function compact(array $row): array{return array_intersect_key($row,array_flip(['id','nombre','estado','inicio_en','fin_en','cupo_maximo','instructor_id','sede_id']));}
}
