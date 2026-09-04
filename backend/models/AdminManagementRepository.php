<?php
/**
 * Acceso a datos AdminManagementRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AdminManagementRepository
{
    private const MANAGEABLE_PERMISSIONS=['members.read','members.write','classes.read','classes.write','reservations.read','reservations.write','attendance.write','memberships.read','memberships.write','payments.read','payments.manual','finance.read','reports.export','promotions.read','promotions.write'];
    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
    }

    public function gym(int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,nombre_legal,slug,descripcion,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,
                    telefono,email,horarios_json,categorias_json,servicios_json,estado,verificacion_estado,imagen_path,is_demo,demo_dataset_id,creado_en,actualizado_en
             FROM gimnasios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND archivado_en IS NULL LIMIT 1'
        );
        $stmt->execute([$gymId, $this->demoFlag(), $this->datasetId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        foreach (['horarios_json' => 'horarios', 'categorias_json' => 'categorias', 'servicios_json' => 'servicios'] as $source => $target) {
            $row[$target] = json_decode((string) $row[$source], true) ?: [];
            unset($row[$source]);
        }
        $row['id'] = (int) $row['id'];
        $row['latitud'] = (float) $row['latitud'];
        $row['longitud'] = (float) $row['longitud'];
        $row['is_demo'] = (bool) $row['is_demo'];
        return $row;
    }

    public function createGym(array $data, int $actorId, string $actorRole): array
    {
        $slug = $this->uniqueSlug($data['nombre']);
        $verification = $actorRole === AuthorizationService::ADMIN ? $data['verificacion_estado'] : 'pendiente';
        $state = $actorRole === AuthorizationService::ADMIN ? $data['estado'] : 'borrador';
        if ($state === 'publicado' && $verification !== 'verificado') {
            ApiResponder::error(422, 'gym_not_verified', 'El gimnasio debe estar verificado antes de publicarse.', ['estado' => 'Marcá la verificación como aprobada.']);
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO gimnasios
                 (nombre,nombre_legal,slug,descripcion,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,
                  horarios_json,categorias_json,servicios_json,estado,verificacion_estado,imagen_path,is_demo,demo_dataset_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $data['nombre'],$data['nombre_legal'],$slug,$data['descripcion'],$data['direccion'],$data['ciudad'],$data['departamento'],$data['pais'],
                $data['latitud'],$data['longitud'],$data['zona_horaria'],$data['telefono'],$data['email'],
                $this->json($data['horarios']),$this->json($data['categorias']),$this->json($data['servicios']),$state,$verification,null,
                $this->demoFlag(),$this->datasetId,
            ]);
            $gymId = (int) $this->pdo->lastInsertId();
            $this->insertLocation($gymId, [...$data, 'nombre' => 'Sede principal', 'es_principal' => true, 'estado' => 'activa']);
            if ($actorRole === AuthorizationService::DUENO) $this->assignRole($actorId, $gymId, AuthorizationService::DUENO);
            $this->pdo->commit();
            return $this->gym($gymId) ?? throw new RuntimeException('No se pudo leer el gimnasio creado.');
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function updateGym(int $gymId, array $data, bool $canVerify): array
    {
        $before = $this->gym($gymId) ?? throw new RuntimeException('Gimnasio no encontrado.');
        $verification = $canVerify ? $data['verificacion_estado'] : $before['verificacion_estado'];
        $state = $data['estado'];
        if ($state === 'publicado' && $verification !== 'verificado') {
            ApiResponder::error(422, 'gym_not_verified', 'El gimnasio debe estar verificado antes de publicarse.', ['estado' => 'La verificación sigue pendiente.']);
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE gimnasios SET nombre=?,nombre_legal=?,descripcion=?,direccion=?,ciudad=?,departamento=?,pais=?,latitud=?,longitud=?,zona_horaria=?,
                 telefono=?,email=?,horarios_json=?,categorias_json=?,servicios_json=?,estado=?,verificacion_estado=? WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([
                $data['nombre'],$data['nombre_legal'],$data['descripcion'],$data['direccion'],$data['ciudad'],$data['departamento'],$data['pais'],$data['latitud'],
                $data['longitud'],$data['zona_horaria'],$data['telefono'],$data['email'],$this->json($data['horarios']),$this->json($data['categorias']),
                $this->json($data['servicios']),$state,$verification,$gymId,$this->demoFlag(),$this->datasetId,
            ]);
            $primary = $this->primaryLocation($gymId);
            if ($primary) {
                $this->updateLocation($gymId, (int) $primary['id'], [...$data, 'nombre' => $primary['nombre'], 'es_principal' => true, 'estado' => 'activa']);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return ['before' => $before, 'after' => $this->gym($gymId)];
    }

    public function archiveGym(int $gymId): array
    {
        $before = $this->gym($gymId);
        if (!$before) ApiResponder::error(404, 'gym_not_found', 'El gimnasio no existe dentro del contexto activo.');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM membresias WHERE gimnasio_id=? AND estado="activa"');
        $stmt->execute([$gymId]);
        if ((int) $stmt->fetchColumn() > 0) ApiResponder::error(409, 'gym_has_active_memberships', 'No se puede archivar un gimnasio con membresías activas.');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE gimnasios SET estado="borrador",archivado_en=NOW() WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)')->execute([$gymId,$this->demoFlag(),$this->datasetId]);
            $this->pdo->prepare('UPDATE gimnasio_sedes SET estado="inactiva" WHERE gimnasio_id=?')->execute([$gymId]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return $before;
    }

    public function locations(int $gymId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,gimnasio_id,nombre,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,horarios_json,es_principal,estado,creado_en,actualizado_en
             FROM gimnasio_sedes WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY es_principal DESC,nombre'
        );
        $stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);
        return array_map(fn(array $row): array => $this->normalizeLocation($row), $stmt->fetchAll());
    }

    public function createLocation(int $gymId, array $data): array
    {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM gimnasio_sedes WHERE gimnasio_id=? AND nombre=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$gymId,$data['nombre'],$this->demoFlag(),$this->datasetId]);
        if((int)$stmt->fetchColumn()>0)ApiResponder::error(409,'location_name_exists','Ya existe una sede con ese nombre.',['nombre'=>'Usá un nombre diferente.']);
        $this->insertLocation($gymId, $data);
        return $this->location($gymId, (int) $this->pdo->lastInsertId()) ?? throw new RuntimeException('No se pudo leer la sede creada.');
    }

    public function updateLocation(int $gymId, int $locationId, array $data): array
    {
        $before = $this->location($gymId, $locationId);
        if (!$before) ApiResponder::error(404, 'location_not_found', 'La sede no existe dentro del gimnasio activo.');
        $duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM gimnasio_sedes WHERE gimnasio_id=? AND nombre=? AND id<>? AND is_demo=? AND (demo_dataset_id <=> ?)');$duplicate->execute([$gymId,$data['nombre'],$locationId,$this->demoFlag(),$this->datasetId]);
        if((int)$duplicate->fetchColumn()>0)ApiResponder::error(409,'location_name_exists','Ya existe una sede con ese nombre.',['nombre'=>'Usá un nombre diferente.']);
        $stmt = $this->pdo->prepare(
            'UPDATE gimnasio_sedes SET nombre=?,direccion=?,ciudad=?,departamento=?,pais=?,latitud=?,longitud=?,zona_horaria=?,telefono=?,email=?,horarios_json=?,estado=?
             WHERE id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
        );
        $stmt->execute([$data['nombre'],$data['direccion'],$data['ciudad'],$data['departamento'],$data['pais'],$data['latitud'],$data['longitud'],$data['zona_horaria'],
            $data['telefono'],$data['email'],$this->json($data['horarios']),$data['estado'],$locationId,$gymId,$this->demoFlag(),$this->datasetId]);
        if ((bool) $before['es_principal']) {
            $stmt = $this->pdo->prepare('UPDATE gimnasios SET direccion=?,ciudad=?,departamento=?,pais=?,latitud=?,longitud=?,zona_horaria=?,telefono=?,email=?,horarios_json=? WHERE id=?');
            $stmt->execute([$data['direccion'],$data['ciudad'],$data['departamento'],$data['pais'],$data['latitud'],$data['longitud'],$data['zona_horaria'],$data['telefono'],$data['email'],$this->json($data['horarios']),$gymId]);
        }
        return ['before' => $before, 'after' => $this->location($gymId, $locationId)];
    }

    public function member(int $gymId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id,u.nombre,u.apellido,u.email,u.telefono,u.fecha_nacimiento,u.email_verificado_en,ugr.id asignacion_id,ugr.activo,
                    sp.numero_socio,sp.estado,sp.fecha_alta,sp.notas_operativas
             FROM usuarios u JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id JOIN roles r ON r.id=ugr.rol_id AND r.nombre="socio"
             LEFT JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id
             WHERE u.id=? AND ugr.gimnasio_id=? AND u.is_demo=? AND (u.demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        $member = $stmt->fetch();
        if (!$member) return null;
        $stmt = $this->pdo->prepare('SELECT m.id,COALESCE(pm.nombre,m.plan) plan,m.estado,m.fecha_inicio,m.fecha_vencimiento,m.precio_pagado FROM membresias m LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE m.usuario_id=? AND m.gimnasio_id=? ORDER BY m.fecha_inicio DESC');
        $stmt->execute([$userId,$gymId]);
        $member['membresias'] = $stmt->fetchAll();
        return $member;
    }

    public function updateMember(int $gymId, int $userId, array $data): array
    {
        $before = $this->member($gymId,$userId);
        if (!$before) ApiResponder::error(404,'member_not_found','El socio no pertenece al gimnasio activo.');
        $active = $data['estado'] === 'activo' ? 1 : 0;
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('UPDATE usuario_gimnasio_roles SET activo=? WHERE id=? AND gimnasio_id=?');
            $stmt->execute([$active,$before['asignacion_id'],$gymId]);
            $stmt=$this->pdo->prepare('UPDATE socio_perfiles SET estado=?,notas_operativas=? WHERE usuario_gimnasio_rol_id=?');
            $stmt->execute([$data['estado'],$data['notas_operativas'],$before['asignacion_id']]);
            $this->pdo->commit();
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
        return ['before'=>$before,'after'=>$this->member($gymId,$userId)];
    }

    public function updateEmployee(int $gymId, int $userId, array $data): array
    {
        $before=$this->employee($gymId,$userId);
        if(!$before) ApiResponder::error(404,'employee_not_found','El empleado no pertenece al gimnasio activo.');
        $this->pdo->beginTransaction();
        try {
            $active=$data['estado']==='activo'?1:0;
            $this->pdo->prepare('UPDATE usuario_gimnasio_roles SET activo=? WHERE id=?')->execute([$active,$before['asignacion_id']]);
            $this->pdo->prepare('UPDATE empleado_perfiles SET cargo=?,estado=?,notas_operativas=? WHERE usuario_gimnasio_rol_id=?')->execute([$data['cargo'],$data['estado'],$data['notas_operativas'],$before['asignacion_id']]);
            $this->replacePermissions((int)$before['asignacion_id'],$data['permissions']);
            $this->pdo->commit();
        } catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
        return ['before'=>$before,'after'=>$this->employee($gymId,$userId)];
    }

    public function employee(int $gymId,int $userId): ?array
    {
        $stmt=$this->pdo->prepare('SELECT u.id,u.nombre,u.apellido,u.email,u.telefono,ugr.id asignacion_id,ep.cargo,ep.estado,ep.fecha_ingreso,ep.notas_operativas FROM usuarios u JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id JOIN roles r ON r.id=ugr.rol_id AND r.nombre="empleado" LEFT JOIN empleado_perfiles ep ON ep.usuario_gimnasio_rol_id=ugr.id WHERE u.id=? AND ugr.gimnasio_id=? AND u.is_demo=? AND (u.demo_dataset_id <=> ?) LIMIT 1');
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();if(!$row)return null;
        $row['permissions']=array_values(array_intersect(self::MANAGEABLE_PERMISSIONS,(new AuthorizationService())->permissions($userId,$gymId)));
        return $row;
    }

    public function attachExistingUser(int $gymId,string $email,string $type,array $profile,array $permissions): ?array
    {
        $stmt=$this->pdo->prepare('SELECT id,is_demo,demo_dataset_id FROM usuarios WHERE email_normalizado=? LIMIT 1');$stmt->execute([$email]);$user=$stmt->fetch();
        if(!$user)return null;
        if((int)$user['is_demo']!==$this->demoFlag() || (($user['demo_dataset_id']===null?null:(int)$user['demo_dataset_id'])!==$this->datasetId)) ApiResponder::error(409,'dataset_scope_conflict','El correo ya pertenece a otro conjunto de datos.');
        $role=$type==='socio'?'socio':'empleado';
        $this->pdo->beginTransaction();
        try{
            $assignmentId=$this->assignRole((int)$user['id'],$gymId,$role);
            if($type==='socio')$this->ensureMemberProfile($assignmentId,$gymId,(int)$user['id']);
            else{
                $this->ensureEmployeeProfile($assignmentId,$profile['cargo']??'Operación');
                $this->replacePermissions($assignmentId,$permissions);
                if($type==='entrenador')$this->ensureTrainer((int)$user['id'],$gymId,$profile);
            }
            $this->pdo->commit();
            return ['user_id'=>(int)$user['id'],'assignment_id'=>$assignmentId];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function createInvitation(int $gymId,string $email,string $type,array $profile,array $permissions,int $actorId,string $tokenHash): int
    {
        $this->pdo->prepare('UPDATE invitaciones_gimnasio SET estado="revocada" WHERE gimnasio_id=? AND email_normalizado=? AND tipo=? AND estado="pendiente"')->execute([$gymId,$email,$type]);
        $stmt=$this->pdo->prepare('INSERT INTO invitaciones_gimnasio (gimnasio_id,email,email_normalizado,tipo,token_hash,permisos_json,perfil_json,estado,invitado_por,expira_en,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,"pendiente",?,DATE_ADD(NOW(),INTERVAL 72 HOUR),?,?)');
        $stmt->execute([$gymId,$email,$email,$type,$tokenHash,$this->json($permissions),$this->json($profile),$actorId,$this->demoFlag(),$this->datasetId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function invitations(int $gymId): array
    {
        $this->pdo->prepare('UPDATE invitaciones_gimnasio SET estado="vencida" WHERE gimnasio_id=? AND estado="pendiente" AND expira_en<NOW()')->execute([$gymId]);
        $stmt=$this->pdo->prepare('SELECT id,email,tipo,estado,expira_en,creado_en FROM invitaciones_gimnasio WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY creado_en DESC LIMIT 100');$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);return $stmt->fetchAll();
    }

    public function revokeInvitation(int $gymId,int $id): bool
    {
        $stmt=$this->pdo->prepare('UPDATE invitaciones_gimnasio SET estado="revocada" WHERE id=? AND gimnasio_id=? AND estado="pendiente" AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);return $stmt->rowCount()===1;
    }

    public function acceptInvitation(string $token,array $newUser): array
    {
        $hash=hash('sha256',$token);$this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare('SELECT * FROM invitaciones_gimnasio WHERE token_hash=? AND estado="pendiente" AND expira_en>=NOW() FOR UPDATE');$stmt->execute([$hash]);$invite=$stmt->fetch();
            if(!$invite)ApiResponder::error(410,'invitation_invalid','La invitación venció, fue utilizada o no es válida.');
            $this->datasetId=$invite['demo_dataset_id']===null?null:(int)$invite['demo_dataset_id'];
            $email=(string)$invite['email_normalizado'];$stmt=$this->pdo->prepare('SELECT id,is_demo,demo_dataset_id FROM usuarios WHERE email_normalizado=? LIMIT 1');$stmt->execute([$email]);$user=$stmt->fetch();
            if(!$user){
                $role=$invite['tipo']==='socio'?'socio':'empleado';$roleId=$this->roleId($role);
                $stmt=$this->pdo->prepare('INSERT INTO usuarios (nombre,apellido,email,email_normalizado,email_verificado_en,password_hash,telefono,terminos_aceptados_en,privacidad_aceptada_en,rol_id,activo,is_demo,demo_dataset_id) VALUES (?,?,?,?,NOW(),?,?,NOW(),NOW(),?,1,?,?)');
                $stmt->execute([$newUser['nombre'],$newUser['apellido'],$email,$email,password_hash($newUser['password'],PASSWORD_BCRYPT,['cost'=>12]),$newUser['telefono'],$roleId,(int)$invite['is_demo'],$invite['demo_dataset_id']]);
                $user=['id'=>(int)$this->pdo->lastInsertId(),'is_demo'=>$invite['is_demo'],'demo_dataset_id'=>$invite['demo_dataset_id']];
            }
            if((int)$user['is_demo']!==(int)$invite['is_demo'] || (($user['demo_dataset_id']===null?null:(int)$user['demo_dataset_id'])!==($invite['demo_dataset_id']===null?null:(int)$invite['demo_dataset_id'])))ApiResponder::error(409,'dataset_scope_conflict','La cuenta pertenece a otro conjunto de datos.');
            $profile=json_decode((string)$invite['perfil_json'],true)?:[];$permissions=json_decode((string)$invite['permisos_json'],true)?:[];$role=$invite['tipo']==='socio'?'socio':'empleado';
            $assignmentId=$this->assignRole((int)$user['id'],(int)$invite['gimnasio_id'],$role);
            if($invite['tipo']==='socio')$this->ensureMemberProfile($assignmentId,(int)$invite['gimnasio_id'],(int)$user['id']);
            else{$this->ensureEmployeeProfile($assignmentId,$profile['cargo']??'Operación');$this->replacePermissions($assignmentId,$permissions);if($invite['tipo']==='entrenador')$this->ensureTrainer((int)$user['id'],(int)$invite['gimnasio_id'],$profile);}
            $this->pdo->prepare('UPDATE invitaciones_gimnasio SET estado="aceptada",aceptada_por=?,aceptada_en=NOW() WHERE id=?')->execute([(int)$user['id'],(int)$invite['id']]);
            $this->pdo->commit();return ['user_id'=>(int)$user['id'],'gym_id'=>(int)$invite['gimnasio_id'],'type'=>$invite['tipo']];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function trainers(int $gymId,array $query): array
    {
        $where=['eg.gimnasio_id=?','ep.is_demo=?','(ep.demo_dataset_id <=> ?)'];$params=[$gymId,$this->demoFlag(),$this->datasetId];
        if($query['q']!==''){$where[]='(u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)';$like='%'.addcslashes($query['q'],'%_\\').'%';array_push($params,$like,$like,$like);}
        if($query['status']!==''){$where[]='ep.estado=?';$params[]=$query['status'];}
        return $this->paged('SELECT ep.id,u.id usuario_id,u.nombre,u.apellido,u.email,ep.biografia,ep.especialidades_json,ep.disponibilidad_json,ep.estado,eg.activo,ep.creado_en FROM entrenador_perfiles ep JOIN usuarios u ON u.id=ep.usuario_id JOIN entrenador_gimnasios eg ON eg.entrenador_perfil_id=ep.id WHERE '.implode(' AND ',$where).' ORDER BY u.nombre,u.apellido','SELECT COUNT(*) FROM entrenador_perfiles ep JOIN usuarios u ON u.id=ep.usuario_id JOIN entrenador_gimnasios eg ON eg.entrenador_perfil_id=ep.id WHERE '.implode(' AND ',$where),$params,$query,true);
    }

    public function updateTrainer(int $gymId,int $profileId,array $data): array
    {
        $before=$this->trainer($gymId,$profileId);if(!$before)ApiResponder::error(404,'trainer_not_found','El entrenador no pertenece al gimnasio activo.');
        $this->pdo->prepare('UPDATE entrenador_perfiles SET biografia=?,especialidades_json=?,disponibilidad_json=?,estado=? WHERE id=?')->execute([$data['biografia'],$this->json($data['especialidades']),$this->json($data['disponibilidad']),$data['estado'],$profileId]);
        $this->pdo->prepare('UPDATE entrenador_gimnasios SET activo=? WHERE entrenador_perfil_id=? AND gimnasio_id=?')->execute([$data['estado']==='activo'?1:0,$profileId,$gymId]);
        return ['before'=>$before,'after'=>$this->trainer($gymId,$profileId)];
    }

    public function plans(int $gymId,array $query): array
    {
        $where=['pm.gimnasio_id=?','pm.is_demo=?','(pm.demo_dataset_id <=> ?)'];$params=[$gymId,$this->demoFlag(),$this->datasetId];
        if($query['q']!==''){$where[]='(pm.nombre LIKE ? OR pm.descripcion LIKE ?)';$like='%'.addcslashes($query['q'],'%_\\').'%';array_push($params,$like,$like);}
        if($query['status']!==''){$where[]='pm.estado=?';$params[]=$query['status'];}
        return $this->paged('SELECT pm.id,pm.nombre,pm.slug,pm.descripcion,pm.duracion_dias,pm.precio,pm.moneda,pm.beneficios_json,pm.version,pm.estado,pm.creado_en,pm.actualizado_en FROM planes_membresia pm WHERE '.implode(' AND ',$where).' ORDER BY pm.nombre,pm.version DESC','SELECT COUNT(*) FROM planes_membresia pm WHERE '.implode(' AND ',$where),$params,$query,true);
    }

    public function createPlan(int $gymId,array $data,int $actorId): array
    {
        $slug=$this->slug($data['nombre']);$duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM planes_membresia WHERE gimnasio_id=? AND slug=?');$duplicate->execute([$gymId,$slug]);if((int)$duplicate->fetchColumn()>0)ApiResponder::error(409,'membership_plan_exists','Ya existe un plan con ese nombre.',['nombre'=>'Usá un nombre diferente.']);$stmt=$this->pdo->prepare('INSERT INTO planes_membresia (gimnasio_id,nombre,slug,descripcion,duracion_dias,precio,moneda,beneficios_json,version,estado,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,?,?,?,1,?,?,?,?)');
        $stmt->execute([$gymId,$data['nombre'],$slug,$data['descripcion'],$data['duracion_dias'],$data['precio'],$data['moneda'],$this->json($data['beneficios']),$data['estado'],$this->demoFlag(),$this->datasetId,$actorId]);
        return $this->plan($gymId,(int)$this->pdo->lastInsertId())??throw new RuntimeException('No se pudo leer el plan creado.');
    }

    public function updatePlan(int $gymId,int $planId,array $data,int $actorId): array
    {
        $before=$this->plan($gymId,$planId);if(!$before)ApiResponder::error(404,'plan_not_found','El plan no pertenece al gimnasio activo.');
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM membresias WHERE plan_id=?');$stmt->execute([$planId]);$used=(int)$stmt->fetchColumn()>0;
        if($used && ($before['precio']!==$data['precio'] || (int)$before['duracion_dias']!==$data['duracion_dias'])){
            $this->pdo->beginTransaction();try{$this->pdo->prepare('UPDATE planes_membresia SET estado="archivado" WHERE id=?')->execute([$planId]);$stmt=$this->pdo->prepare('INSERT INTO planes_membresia (gimnasio_id,nombre,slug,descripcion,duracion_dias,precio,moneda,beneficios_json,version,estado,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$gymId,$data['nombre'],$before['slug'],$data['descripcion'],$data['duracion_dias'],$data['precio'],$data['moneda'],$this->json($data['beneficios']),(int)$before['version']+1,$data['estado'],$this->demoFlag(),$this->datasetId,$actorId]);$newId=(int)$this->pdo->lastInsertId();$this->pdo->commit();return ['versioned'=>true,'before'=>$before,'after'=>$this->plan($gymId,$newId)];}catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
        }
        $this->pdo->prepare('UPDATE planes_membresia SET nombre=?,descripcion=?,duracion_dias=?,precio=?,moneda=?,beneficios_json=?,estado=? WHERE id=? AND gimnasio_id=?')->execute([$data['nombre'],$data['descripcion'],$data['duracion_dias'],$data['precio'],$data['moneda'],$this->json($data['beneficios']),$data['estado'],$planId,$gymId]);
        return ['versioned'=>false,'before'=>$before,'after'=>$this->plan($gymId,$planId)];
    }

    public function createMembership(int $gymId,array $data,int $actorId): array
    {
        $member=$this->member($gymId,$data['usuario_id']);if(!$member)ApiResponder::error(422,'member_not_found','Seleccioná un socio del gimnasio activo.',['usuario_id'=>'El socio no pertenece a este gimnasio.']);
        $plan=$this->plan($gymId,$data['plan_id']);if(!$plan||$plan['estado']!=='activo')ApiResponder::error(422,'plan_not_available','Seleccioná un plan activo del gimnasio.',['plan_id'=>'El plan no está disponible.']);
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM membresias WHERE usuario_id=? AND gimnasio_id=? AND estado="activa"');$stmt->execute([$data['usuario_id'],$gymId]);if((int)$stmt->fetchColumn()>0)ApiResponder::error(409,'active_membership_exists','El socio ya tiene una membresía activa en este gimnasio.');
        $start=new DateTimeImmutable($data['fecha_inicio']);$end=$start->modify('+'.(int)$plan['duracion_dias'].' days');$legacy=$plan['duracion_dias']>=300?'anual':($plan['duracion_dias']>=80?'trimestral':'mensual');
        $number=$member['numero_socio']?:$this->memberNumber($gymId,$data['usuario_id']);
        $this->pdo->beginTransaction();try{$stmt=$this->pdo->prepare('INSERT INTO membresias (usuario_id,numero_socio,gimnasio_id,plan_id,plan,fecha_inicio,fecha_vencimiento,estado,precio_pagado,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$data['usuario_id'],$number,$gymId,$data['plan_id'],$legacy,$start->format('Y-m-d'),$end->format('Y-m-d'),'activa',$plan['precio'],$this->demoFlag(),$this->datasetId]);$id=(int)$this->pdo->lastInsertId();$this->pdo->prepare('INSERT INTO membresia_historial (membresia_id,estado_anterior,estado_nuevo,motivo,actor_usuario_id) VALUES (?,NULL,"activa",?,?)')->execute([$id,$data['motivo'],$actorId]);$this->pdo->commit();return $this->membership($gymId,$id)??throw new RuntimeException('No se pudo leer la membresía creada.');}catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function transitionMembership(int $gymId,int $membershipId,string $status,string $reason,int $actorId): array
    {
        $before=$this->membership($gymId,$membershipId);if(!$before)ApiResponder::error(404,'membership_not_found','La membresía no pertenece al gimnasio activo.');
        $allowed=['activa'=>['suspendida','vencida'],'suspendida'=>['activa','vencida'],'vencida'=>['activa']];if(!in_array($status,$allowed[$before['estado']]??[],true))ApiResponder::error(409,'invalid_membership_transition','Ese cambio de estado no está permitido.');
        if($status==='activa'){$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM membresias WHERE usuario_id=? AND gimnasio_id=? AND estado="activa" AND id<>?');$stmt->execute([$before['usuario_id'],$gymId,$membershipId]);if((int)$stmt->fetchColumn()>0)ApiResponder::error(409,'active_membership_exists','El socio ya tiene otra membresía activa.');}
        $this->pdo->beginTransaction();try{$this->pdo->prepare('UPDATE membresias SET estado=? WHERE id=? AND gimnasio_id=?')->execute([$status,$membershipId,$gymId]);$this->pdo->prepare('INSERT INTO membresia_historial (membresia_id,estado_anterior,estado_nuevo,motivo,actor_usuario_id) VALUES (?,?,?,?,?)')->execute([$membershipId,$before['estado'],$status,$reason,$actorId]);$this->pdo->commit();return ['before'=>$before,'after'=>$this->membership($gymId,$membershipId)];}catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function insertLocation(int $gymId,array $data): void{$stmt=$this->pdo->prepare('INSERT INTO gimnasio_sedes (gimnasio_id,nombre,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,horarios_json,es_principal,estado,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$gymId,$data['nombre'],$data['direccion'],$data['ciudad'],$data['departamento'],$data['pais'],$data['latitud'],$data['longitud'],$data['zona_horaria'],$data['telefono'],$data['email'],$this->json($data['horarios']),!empty($data['es_principal'])?1:0,$data['estado']??'activa',$this->demoFlag(),$this->datasetId]);}
    private function location(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT * FROM gimnasio_sedes WHERE id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?$this->normalizeLocation($row):null;}
    private function primaryLocation(int $gymId): ?array{$stmt=$this->pdo->prepare('SELECT * FROM gimnasio_sedes WHERE gimnasio_id=? AND es_principal=1 LIMIT 1');$stmt->execute([$gymId]);$row=$stmt->fetch();return $row?$this->normalizeLocation($row):null;}
    private function normalizeLocation(array $row): array{$row['id']=(int)$row['id'];$row['gimnasio_id']=(int)$row['gimnasio_id'];$row['latitud']=(float)$row['latitud'];$row['longitud']=(float)$row['longitud'];$row['es_principal']=(bool)$row['es_principal'];$row['horarios']=json_decode((string)$row['horarios_json'],true)?:[];unset($row['horarios_json']);return $row;}
    private function assignRole(int $userId,int $gymId,string $role): int{$stmt=$this->pdo->prepare('INSERT INTO usuario_gimnasio_roles (usuario_id,gimnasio_id,rol_id,activo,is_demo,demo_dataset_id) VALUES (?,?,?,1,?,?) ON DUPLICATE KEY UPDATE activo=1,is_demo=VALUES(is_demo),demo_dataset_id=VALUES(demo_dataset_id)');$stmt->execute([$userId,$gymId,$this->roleId($role),$this->demoFlag(),$this->datasetId]);$stmt=$this->pdo->prepare('SELECT id FROM usuario_gimnasio_roles WHERE usuario_id=? AND gimnasio_id=? AND rol_id=?');$stmt->execute([$userId,$gymId,$this->roleId($role)]);return (int)$stmt->fetchColumn();}
    private function ensureMemberProfile(int $assignmentId,int $gymId,int $userId): void{$this->pdo->prepare('INSERT INTO socio_perfiles (usuario_gimnasio_rol_id,numero_socio,estado,fecha_alta) VALUES (?,? ,"activo",CURRENT_DATE()) ON DUPLICATE KEY UPDATE estado="activo"')->execute([$assignmentId,$this->memberNumber($gymId,$userId)]);}
    private function ensureEmployeeProfile(int $assignmentId,string $cargo): void{$this->pdo->prepare('INSERT INTO empleado_perfiles (usuario_gimnasio_rol_id,cargo,estado,fecha_ingreso) VALUES (?,?,"activo",CURRENT_DATE()) ON DUPLICATE KEY UPDATE cargo=VALUES(cargo),estado="activo"')->execute([$assignmentId,$cargo]);}
    private function ensureTrainer(int $userId,int $gymId,array $profile): void{$stmt=$this->pdo->prepare('INSERT INTO entrenador_perfiles (usuario_id,biografia,especialidades_json,disponibilidad_json,estado,is_demo,demo_dataset_id) VALUES (?,?,?,?,"activo",?,?) ON DUPLICATE KEY UPDATE biografia=VALUES(biografia),especialidades_json=VALUES(especialidades_json),disponibilidad_json=VALUES(disponibilidad_json),estado="activo"');$stmt->execute([$userId,$profile['biografia']??null,$this->json($profile['especialidades']??[]),$this->json($profile['disponibilidad']??[]),$this->demoFlag(),$this->datasetId]);$stmt=$this->pdo->prepare('SELECT id FROM entrenador_perfiles WHERE usuario_id=?');$stmt->execute([$userId]);$profileId=(int)$stmt->fetchColumn();$this->pdo->prepare('INSERT INTO entrenador_gimnasios (entrenador_perfil_id,gimnasio_id,activo,is_demo,demo_dataset_id) VALUES (?,?,1,?,?) ON DUPLICATE KEY UPDATE activo=1')->execute([$profileId,$gymId,$this->demoFlag(),$this->datasetId]);}
    private function replacePermissions(int $assignmentId,array $permissions): void{$this->pdo->prepare('DELETE FROM usuario_gimnasio_permisos WHERE usuario_gimnasio_rol_id=?')->execute([$assignmentId]);$placeholders=implode(',',array_fill(0,count(self::MANAGEABLE_PERMISSIONS),'?'));$stmt=$this->pdo->prepare("SELECT id,codigo FROM permisos WHERE codigo IN ({$placeholders})");$stmt->execute(self::MANAGEABLE_PERMISSIONS);$insert=$this->pdo->prepare('INSERT INTO usuario_gimnasio_permisos (usuario_gimnasio_rol_id,permiso_id,permitido) VALUES (?,?,?)');foreach($stmt->fetchAll() as $permission)$insert->execute([$assignmentId,(int)$permission['id'],in_array($permission['codigo'],$permissions,true)?1:0]);}
    private function trainer(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT ep.id,u.id usuario_id,u.nombre,u.apellido,u.email,ep.biografia,ep.especialidades_json,ep.disponibilidad_json,ep.estado FROM entrenador_perfiles ep JOIN usuarios u ON u.id=ep.usuario_id JOIN entrenador_gimnasios eg ON eg.entrenador_perfil_id=ep.id WHERE ep.id=? AND eg.gimnasio_id=? AND ep.is_demo=? AND (ep.demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();if(!$row)return null;$row['especialidades']=json_decode((string)$row['especialidades_json'],true)?:[];$row['disponibilidad']=json_decode((string)$row['disponibilidad_json'],true)?:[];unset($row['especialidades_json'],$row['disponibilidad_json']);return $row;}
    private function plan(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT * FROM planes_membresia WHERE id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();if(!$row)return null;$row['id']=(int)$row['id'];$row['duracion_dias']=(int)$row['duracion_dias'];$row['precio']=number_format((float)$row['precio'],2,'.','');$row['beneficios']=json_decode((string)$row['beneficios_json'],true)?:[];unset($row['beneficios_json']);return $row;}
    private function membership(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT m.*,COALESCE(pm.nombre,m.plan) plan_nombre,COALESCE(pm.moneda,"UYU") moneda,u.email usuario_email,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre FROM membresias m JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE m.id=? AND m.gimnasio_id=? AND m.is_demo=? AND (m.demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?:null;}
    private function paged(string $select,string $count,array $params,array $query,bool $decodeJson=false): array{$c=$this->pdo->prepare($count);$c->execute($params);$total=(int)$c->fetchColumn();$offset=($query['page']-1)*$query['per_page'];$s=$this->pdo->prepare($select.' LIMIT '.$query['per_page'].' OFFSET '.$offset);$s->execute($params);$items=$s->fetchAll();if($decodeJson)foreach($items as &$item)foreach(['especialidades_json'=>'especialidades','disponibilidad_json'=>'disponibilidad','beneficios_json'=>'beneficios'] as $source=>$target)if(array_key_exists($source,$item)){$item[$target]=json_decode((string)$item[$source],true)?:[];unset($item[$source]);}return ['items'=>$items,'pagination'=>['page'=>$query['page'],'per_page'=>$query['per_page'],'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$query['per_page']))]];}
    private function roleId(string $role): int{$stmt=$this->pdo->prepare('SELECT id FROM roles WHERE nombre=?');$stmt->execute([$role]);$id=$stmt->fetchColumn();if($id===false)throw new RuntimeException('Rol no configurado.');return (int)$id;}
    private function memberNumber(int $gymId,int $userId): string{return sprintf('GT-%04d-%06d',$gymId,$userId);}
    private function uniqueSlug(string $name): string{$base=$this->slug($name);$slug=$base;$index=2;$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM gimnasios WHERE slug=?');while(true){$stmt->execute([$slug]);if((int)$stmt->fetchColumn()===0)return $slug;$slug=$base.'-'.$index++;}}
    private function slug(string $value): string{$value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=strtolower($value);$value=preg_replace('/[^a-z0-9]+/','-',$value)?:'gimnasio';return trim($value,'-');}
    private function json(array $value): string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function demoFlag(): int{return $this->datasetId===null?0:1;}
}
