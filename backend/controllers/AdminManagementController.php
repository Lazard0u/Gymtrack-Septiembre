<?php

declare(strict_types=1);

final class AdminManagementController
{
    private const ADMIN_ROLES = ['empleado','dueño','admin_general'];
    private const EMPLOYEE_PERMISSIONS = [
        'members.read','members.write','classes.read','classes.write','reservations.read','reservations.write',
        'attendance.write','memberships.read','memberships.write','payments.read','payments.manual','finance.read','reports.export',
    ];

    public function showGym(string $gymId): void
    {
        [$userId,$activeGymId,$repo] = $this->authorize('gyms.read');
        $this->assertActiveGym((int)$gymId,$activeGymId);
        ApiResponder::success($repo->gym($activeGymId) ?? []);
    }

    public function createGym(): void
    {
        AuthMiddleware::verificarRoles([AuthorizationService::DUENO,AuthorizationService::ADMIN]);
        AuthMiddleware::verificarPermiso('gym.configure');
        $userId=AuthMiddleware::obtenerUsuarioId();$role=(new AuthorizationService())->effectiveRole($userId,null);$repo=new AdminManagementRepository(AuthMiddleware::obtenerDemoDatasetId());
        if (!in_array($role,[AuthorizationService::DUENO,AuthorizationService::ADMIN],true)) ApiResponder::error(403,'forbidden','Sólo un dueño o administrador general puede crear gimnasios.');
        $data=$this->gymData($this->body(),false);
        $created=$repo->createGym($data,$userId,$role);
        AdminAuditLogger::record('gym.created','gimnasio','success',$userId,(int)$created['id'],(string)$created['id'],'Alta desde Administración',null,$this->compact($created,['id','nombre','slug','estado','verificacion_estado']));
        ApiResponder::success($created,201);
    }

    public function updateGym(string $gymId): void
    {
        [$userId,$activeGymId,$repo,$role]=$this->authorize('gym.configure');$id=(int)$gymId;$this->assertActiveGym($id,$activeGymId);
        $data=$this->gymData($this->body(),true);$result=$repo->updateGym($id,$data,$role===AuthorizationService::ADMIN);
        AdminAuditLogger::record('gym.updated','gimnasio','success',$userId,$id,(string)$id,null,$this->compact($result['before'],['nombre','estado','verificacion_estado']),$this->compact($result['after']??[],['nombre','estado','verificacion_estado']));
        ApiResponder::success($result['after']??[]);
    }

    public function archiveGym(string $gymId): void
    {
        [$userId,$activeGymId,$repo,$role]=$this->authorize('gym.configure');$id=(int)$gymId;$this->assertActiveGym($id,$activeGymId);
        if(!in_array($role,[AuthorizationService::DUENO,AuthorizationService::ADMIN],true))ApiResponder::error(403,'forbidden','Sólo un dueño o administrador general puede archivar gimnasios.');
        $body=$this->body();$v=new AdminInputValidator($body);$reason=$v->requiredString('motivo','El motivo',255,8);$v->failIfInvalid();$before=$repo->archiveGym($id);
        AdminAuditLogger::record('gym.archived','gimnasio','success',$userId,$id,(string)$id,$reason,$this->compact($before,['nombre','estado','verificacion_estado']),['estado'=>'borrador','archivado'=>true]);
        unset($_SESSION['admin_support_reason']);$csrf=(new SessionManager())->rotateContext(null);ApiResponder::success(['archived'=>true,'csrf_token'=>$csrf]);
    }

    public function locations(string $gymId): void
    {
        [, $activeGymId,$repo]=$this->authorize('gyms.read');$this->assertActiveGym((int)$gymId,$activeGymId);ApiResponder::success(['items'=>$repo->locations($activeGymId)]);
    }

    public function createLocation(string $gymId): void
    {
        [$userId,$activeGymId,$repo]=$this->authorize('gym.configure');$this->assertActiveGym((int)$gymId,$activeGymId);$data=$this->locationData($this->body());$created=$repo->createLocation($activeGymId,$data);
        AdminAuditLogger::record('gym.location.created','gimnasio_sede','success',$userId,$activeGymId,(string)$created['id'],null,null,$this->compact($created,['id','nombre','ciudad','estado']));ApiResponder::success($created,201);
    }

    public function updateLocation(string $gymId,string $locationId): void
    {
        [$userId,$activeGymId,$repo]=$this->authorize('gym.configure');$this->assertActiveGym((int)$gymId,$activeGymId);$data=$this->locationData($this->body());$result=$repo->updateLocation($activeGymId,(int)$locationId,$data);
        AdminAuditLogger::record('gym.location.updated','gimnasio_sede','success',$userId,$activeGymId,$locationId,null,$this->compact($result['before']??[],['nombre','ciudad','estado']),$this->compact($result['after']??[],['nombre','ciudad','estado']));ApiResponder::success($result['after']??[]);
    }

    public function member(string $userId): void
    {
        [, $gymId,$repo]=$this->authorize('members.read');$member=$repo->member($gymId,(int)$userId);if(!$member)ApiResponder::error(404,'member_not_found','El socio no pertenece al gimnasio activo.');ApiResponder::success($member);
    }

    public function updateMember(string $userId): void
    {
        [$actor,$gymId,$repo]=$this->authorize('members.write');$body=$this->body();$v=new AdminInputValidator($body);$state=$v->enum('estado',['activo','inactivo','archivado'],'activo');$notes=$v->optionalString('notas_operativas','Las notas',2000);$v->failIfInvalid();$result=$repo->updateMember($gymId,(int)$userId,['estado'=>$state,'notas_operativas'=>$notes]);
        AdminAuditLogger::record('member.updated','socio','success',$actor,$gymId,$userId,trim((string)($body['motivo']??''))?:null,['estado'=>$result['before']['estado']??null],['estado'=>$result['after']['estado']??null]);ApiResponder::success($result['after']??[]);
    }

    public function employee(string $userId): void
    {
        [, $gymId,$repo]=$this->authorize('staff.manage');$employee=$repo->employee($gymId,(int)$userId);if(!$employee)ApiResponder::error(404,'employee_not_found','El empleado no pertenece al gimnasio activo.');ApiResponder::success($employee);
    }

    public function updateEmployee(string $userId): void
    {
        [$actor,$gymId,$repo]=$this->authorize('staff.manage');$body=$this->body();$v=new AdminInputValidator($body);$cargo=$v->requiredString('cargo','El cargo',100);$state=$v->enum('estado',['activo','inactivo','archivado'],'activo');$notes=$v->optionalString('notas_operativas','Las notas',2000);$permissions=$this->permissions($body['permissions']??[]);$v->failIfInvalid();$result=$repo->updateEmployee($gymId,(int)$userId,['cargo'=>$cargo,'estado'=>$state,'notas_operativas'=>$notes,'permissions'=>$permissions]);
        AdminAuditLogger::record('employee.updated','empleado','success',$actor,$gymId,$userId,null,$this->compact($result['before']??[],['cargo','estado','permissions']),$this->compact($result['after']??[],['cargo','estado','permissions']));ApiResponder::success($result['after']??[]);
    }

    public function invitations(): void
    {
        [, $gymId,$repo]=$this->authorizeAny(['members.write','staff.manage']);ApiResponder::success(['items'=>$repo->invitations($gymId)]);
    }

    public function invite(): void
    {
        $body=$this->body();$v=new AdminInputValidator($body);$email=$v->email('email');$type=$v->enum('tipo',['socio','empleado','entrenador'],'socio');$cargo=$v->optionalString('cargo','El cargo',100)??($type==='entrenador'?'Entrenador':'Operación');$biography=$v->optionalString('biografia','La biografía',1000);$specialties=$v->stringList('especialidades',15,80);$v->failIfInvalid();
        $permission=$type==='socio'?'members.write':'staff.manage';[$actor,$gymId,$repo]=$this->authorize($permission);$permissions=$type==='socio'?[]:$this->permissions($body['permissions']??[]);$profile=['cargo'=>$cargo,'biografia'=>$biography,'especialidades'=>$specialties,'disponibilidad'=>[]];
        $attached=$repo->attachExistingUser($gymId,$email,$type,$profile,$permissions);
        if($attached){(new MailService())->send($email,'Acceso a GymTrack','Tu cuenta fue asociada a un gimnasio. Iniciá sesión para continuar.');AdminAuditLogger::record('invitation.accepted_existing','invitacion','success',$actor,$gymId,(string)$attached['assignment_id'],'Cuenta existente asociada');ApiResponder::success(['status'=>'accepted_existing','user_id'=>$attached['user_id']],201);}
        $token=bin2hex(random_bytes(32));$id=$repo->createInvitation($gymId,$email,$type,$profile,$permissions,$actor,hash('sha256',$token));$url=rtrim((string)(getenv('FRONTEND_URL')?:'http://localhost:5173'),'/').'/invitacion/'.$token;
        try{(new MailService())->send($email,'Invitación a GymTrack',"Recibiste una invitación para unirte a GymTrack. Vence en 72 horas:\n{$url}");}catch(Throwable $error){$repo->revokeInvitation($gymId,$id);throw $error;}
        AdminAuditLogger::record('invitation.created','invitacion','success',$actor,$gymId,(string)$id,$type,null,['email'=>$email,'tipo'=>$type]);ApiResponder::success(['status'=>'pending','invitation_id'=>$id,'expires_in_hours'=>72],201);
    }

    public function revokeInvitation(string $id): void
    {
        [$actor,$gymId,$repo]=$this->authorizeAny(['members.write','staff.manage']);if(!$repo->revokeInvitation($gymId,(int)$id))ApiResponder::error(404,'invitation_not_found','La invitación pendiente no existe.');AdminAuditLogger::record('invitation.revoked','invitacion','success',$actor,$gymId,$id);ApiResponder::success(['revoked'=>true]);
    }

    public function acceptInvitation(): void
    {
        $body=$this->body();$v=new AdminInputValidator($body);$token=$v->requiredString('token','El token',128,40);$name=$v->requiredString('nombre','El nombre',100,2);$surname=$v->requiredString('apellido','El apellido',100,2);$phone=$v->optionalString('telefono','El teléfono',20);$password=$v->requiredString('password','La contraseña',200,12);if(!$v->boolean('terminos')||!$v->boolean('privacidad'))ApiResponder::error(422,'consent_required','Debés aceptar términos y privacidad para crear la cuenta.',['terminos'=>'La aceptación es obligatoria.']);$v->failIfInvalid();
        if($passwordError=Security::passwordError($password))ApiResponder::error(422,'validation_error','Revisá los campos indicados.',['password'=>$passwordError]);
        $subject=hash('sha256',$token);$limit=new RateLimiter();$limit->ensureAllowed('invitation_accept',$subject);$limit->fail('invitation_accept',$subject);
        $result=(new AdminManagementRepository(null))->acceptInvitation($token,['nombre'=>$name,'apellido'=>$surname,'telefono'=>$phone,'password'=>$password]);$limit->clear('invitation_accept',$subject);ApiResponder::success($result,201);
    }

    public function trainers(): void
    {
        [, $gymId,$repo]=$this->authorize('staff.manage');$this->paged($repo->trainers($gymId,AdminRepository::query($_GET)));
    }

    public function updateTrainer(string $id): void
    {
        [$actor,$gymId,$repo]=$this->authorize('staff.manage');$body=$this->body();$v=new AdminInputValidator($body);$bio=$v->optionalString('biografia','La biografía',1000);$specialties=$v->stringList('especialidades',15,80);$availability=$v->stringList('disponibilidad',20,100);$state=$v->enum('estado',['activo','inactivo','archivado'],'activo');$v->failIfInvalid();$result=$repo->updateTrainer($gymId,(int)$id,['biografia'=>$bio,'especialidades'=>$specialties,'disponibilidad'=>$availability,'estado'=>$state]);AdminAuditLogger::record('trainer.updated','entrenador','success',$actor,$gymId,$id,null,['estado'=>$result['before']['estado']??null],['estado'=>$result['after']['estado']??null]);ApiResponder::success($result['after']??[]);
    }

    public function plans(): void
    {
        [, $gymId,$repo]=$this->authorize('memberships.read');$this->paged($repo->plans($gymId,AdminRepository::query($_GET)));
    }

    public function createPlan(): void
    {
        [$actor,$gymId,$repo]=$this->authorize('memberships.write');$data=$this->planData($this->body());$created=$repo->createPlan($gymId,$data,$actor);AdminAuditLogger::record('membership_plan.created','plan_membresia','success',$actor,$gymId,(string)$created['id'],null,null,$this->compact($created,['nombre','precio','moneda','version','estado']));ApiResponder::success($created,201);
    }

    public function updatePlan(string $id): void
    {
        [$actor,$gymId,$repo]=$this->authorize('memberships.write');$data=$this->planData($this->body());$result=$repo->updatePlan($gymId,(int)$id,$data,$actor);AdminAuditLogger::record('membership_plan.updated','plan_membresia','success',$actor,$gymId,$id,$result['versioned']?'Nueva versión por uso existente':null,$this->compact($result['before']??[],['nombre','precio','version','estado']),$this->compact($result['after']??[],['nombre','precio','version','estado']));ApiResponder::success($result['after']??[],200,['versioned'=>$result['versioned']]);
    }

    public function createMembership(): void
    {
        [$actor,$gymId,$repo]=$this->authorize('memberships.write');$body=$this->body();$v=new AdminInputValidator($body);$userId=$v->integer('usuario_id','El socio',1,PHP_INT_MAX);$planId=$v->integer('plan_id','El plan',1,PHP_INT_MAX);$start=$v->date('fecha_inicio','La fecha de inicio');$reason=$v->requiredString('motivo','El motivo',255,4);$v->failIfInvalid();$created=$repo->createMembership($gymId,['usuario_id'=>$userId,'plan_id'=>$planId,'fecha_inicio'=>$start,'motivo'=>$reason],$actor);AdminAuditLogger::record('membership.created','membresia','success',$actor,$gymId,(string)$created['id'],$reason,null,$this->compact($created,['usuario_id','plan_id','estado','fecha_inicio','fecha_vencimiento']));ApiResponder::success($created,201);
    }

    public function transitionMembership(string $id): void
    {
        [$actor,$gymId,$repo]=$this->authorize('memberships.write');$body=$this->body();$v=new AdminInputValidator($body);$status=$v->enum('estado',['activa','suspendida','vencida'],'suspendida');$reason=$v->requiredString('motivo','El motivo',255,4);$v->failIfInvalid();$result=$repo->transitionMembership($gymId,(int)$id,$status,$reason,$actor);AdminAuditLogger::record('membership.status.changed','membresia','success',$actor,$gymId,$id,$reason,['estado'=>$result['before']['estado']??null],['estado'=>$result['after']['estado']??null]);ApiResponder::success($result['after']??[]);
    }

    private function authorize(string $permission): array
    {
        AuthMiddleware::verificarRoles(self::ADMIN_ROLES);AuthMiddleware::verificarPermiso($permission);$gymId=AuthMiddleware::requerirContextoGimnasio();$repo=new AdminManagementRepository(AuthMiddleware::obtenerDemoDatasetId());if(!$repo->gym($gymId))ApiResponder::error(403,'gym_scope_mismatch','El gimnasio activo no pertenece al conjunto de datos de tu sesión.');return [AuthMiddleware::obtenerUsuarioId(),$gymId,$repo,(new AuthorizationService())->effectiveRole(AuthMiddleware::obtenerUsuarioId(),$gymId)];
    }

    private function authorizeAny(array $permissions): array
    {
        AuthMiddleware::verificarRoles(self::ADMIN_ROLES);$userId=AuthMiddleware::obtenerUsuarioId();$gymId=AuthMiddleware::requerirContextoGimnasio();$authz=new AuthorizationService();if(!array_filter($permissions,fn(string $permission):bool=>$authz->hasPermission($userId,$permission,$gymId)))ApiResponder::error(403,'forbidden','No tenés el permiso requerido para esta acción.');$repo=new AdminManagementRepository(AuthMiddleware::obtenerDemoDatasetId());if(!$repo->gym($gymId))ApiResponder::error(403,'gym_scope_mismatch','El gimnasio activo no pertenece al conjunto de datos de tu sesión.');return [$userId,$gymId,$repo,$authz->effectiveRole($userId,$gymId)];
    }

    private function assertActiveGym(int $routeGymId,int $activeGymId): void{if($routeGymId!==$activeGymId)ApiResponder::error(403,'gym_context_mismatch','La ruta no coincide con el gimnasio activo.');}
    private function body(): array{$decoded=json_decode((string)file_get_contents('php://input'),true);if(!is_array($decoded))ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');return $decoded;}
    private function paged(array $result): never{ApiResponder::success(['items'=>$result['items']],200,['pagination'=>$result['pagination']]);}

    private function gymData(array $body,bool $editing): array
    {
        $v=new AdminInputValidator($body);$name=$v->requiredString('nombre','El nombre comercial',120,3);$legal=$v->requiredString('nombre_legal','El nombre legal',160,3);$description=$v->requiredString('descripcion','La descripción',2000,20);$address=$v->requiredString('direccion','La dirección',180,5);$city=$v->requiredString('ciudad','La ciudad',100,2);$department=$v->requiredString('departamento','El departamento',100,2);$lat=$v->latitude('latitud');$lng=$v->longitude('longitud');$timezone=$v->requiredString('zona_horaria','La zona horaria',80,3);if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))ApiResponder::error(422,'validation_error','Revisá la zona horaria.',['zona_horaria'=>'Usá una zona horaria IANA válida.']);$phone=$v->optionalString('telefono','El teléfono',40);$email=trim((string)($body['email']??''))===''?null:$v->email('email','El correo del gimnasio');$categories=$v->stringList('categorias',20,80);$services=$v->stringList('servicios',30,100);$hours=$v->stringList('horarios',20,100);$state=$v->enum('estado',['borrador','publicado','temporalmente_cerrado'],$editing?'borrador':'borrador');$verification=$v->enum('verificacion_estado',['pendiente','verificado','rechazado'],'pendiente');$v->failIfInvalid();return ['nombre'=>$name,'nombre_legal'=>$legal,'descripcion'=>$description,'direccion'=>$address,'ciudad'=>$city,'departamento'=>$department,'latitud'=>$lat,'longitud'=>$lng,'zona_horaria'=>$timezone,'telefono'=>$phone,'email'=>$email,'categorias'=>$categories,'servicios'=>$services,'horarios'=>$hours,'estado'=>$state,'verificacion_estado'=>$verification];
    }

    private function locationData(array $body): array
    {
        $v=new AdminInputValidator($body);$name=$v->requiredString('nombre','El nombre de la sede',120,3);$address=$v->requiredString('direccion','La dirección',180,5);$city=$v->requiredString('ciudad','La ciudad',100,2);$department=$v->requiredString('departamento','El departamento',100,2);$lat=$v->latitude('latitud');$lng=$v->longitude('longitud');$timezone=$v->requiredString('zona_horaria','La zona horaria',80,3);if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))ApiResponder::error(422,'validation_error','Revisá la zona horaria.',['zona_horaria'=>'Usá una zona horaria IANA válida.']);$phone=$v->optionalString('telefono','El teléfono',40);$email=trim((string)($body['email']??''))===''?null:$v->email('email','El correo de la sede');$hours=$v->stringList('horarios',20,100);$state=$v->enum('estado',['activa','inactiva'],'activa');$v->failIfInvalid();return ['nombre'=>$name,'direccion'=>$address,'ciudad'=>$city,'departamento'=>$department,'latitud'=>$lat,'longitud'=>$lng,'zona_horaria'=>$timezone,'telefono'=>$phone,'email'=>$email,'horarios'=>$hours,'estado'=>$state,'es_principal'=>false];
    }

    private function planData(array $body): array
    {
        $v=new AdminInputValidator($body);$name=$v->requiredString('nombre','El nombre',100,3);$description=$v->optionalString('descripcion','La descripción',500);$days=$v->integer('duracion_dias','La duración',1,1095);$price=$v->decimal('precio','El precio',0,999999999);$currency=$v->enum('moneda',['UYU','USD'],'UYU');$benefits=$v->stringList('beneficios',20,100);$state=$v->enum('estado',['borrador','activo','archivado'],'borrador');$v->failIfInvalid();return ['nombre'=>$name,'descripcion'=>$description,'duracion_dias'=>$days,'precio'=>$price,'moneda'=>$currency,'beneficios'=>$benefits,'estado'=>$state];
    }

    private function permissions(mixed $value): array{if(!is_array($value))return [];return array_values(array_intersect(self::EMPLOYEE_PERMISSIONS,array_map('strval',$value)));}
    private function compact(array $data,array $keys): array{return array_intersect_key($data,array_flip($keys));}
}
