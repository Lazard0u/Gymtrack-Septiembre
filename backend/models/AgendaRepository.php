<?php
/**
 * Acceso a datos AgendaRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AgendaRepository
{
    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
    }

    public function sessions(int $gymId, array $query, ?int $userId = null): array
    {
        $from = $this->validDate((string)($query['from'] ?? '')) ?: (new DateTimeImmutable('today'))->format('Y-m-d');
        $to = $this->validDate((string)($query['to'] ?? '')) ?: (new DateTimeImmutable($from))->modify('+30 days')->format('Y-m-d');
        $where = ['s.gimnasio_id=?','s.is_demo=?','(s.demo_dataset_id <=> ?)','s.inicio_en>=?','s.inicio_en<?'];
        $params = [$gymId,$this->demoFlag(),$this->datasetId,$from.' 00:00:00',(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        if ($query['q'] !== '') { $where[]='(c.nombre LIKE ? OR c.descripcion LIKE ? OR CONCAT_WS(" ",u.nombre,u.apellido) LIKE ?)';$like='%'.addcslashes($query['q'],'%_\\').'%';array_push($params,$like,$like,$like); }
        if ($query['status'] !== '') { $where[]='s.estado=?';$params[]=$query['status']; }
        $bookingSelect = $userId === null
            ? 'NULL reserva_id,NULL reserva_estado,NULL posicion_espera'
            : 'r.id reserva_id,r.estado reserva_estado,r.posicion_espera';
        $bookingJoin = $userId === null ? '' : ' LEFT JOIN reservas r ON r.sesion_clase_id=s.id AND r.usuario_id='.(int)$userId;
        $base = ' FROM sesiones_clase s JOIN clases c ON c.id=s.clase_id JOIN usuarios u ON u.id=s.instructor_id LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id'.$bookingJoin.' WHERE '.implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base);$count->execute($params);$total=(int)$count->fetchColumn();
        $page=$query['page'];$perPage=$query['per_page'];$offset=($page-1)*$perPage;
        $stmt=$this->pdo->prepare('SELECT s.id,s.clase_id,c.nombre,c.descripcion,c.color,s.sede_id,gs.nombre sede_nombre,s.instructor_id,CONCAT_WS(" ",u.nombre,u.apellido) instructor_nombre,s.inicio_en,s.fin_en,s.zona_horaria,s.cupo_maximo,s.cupos_reservados,(s.cupo_maximo-s.cupos_reservados) cupos_disponibles,s.espera_total,s.estado,s.motivo_cancelacion,s.version,'.$bookingSelect.$base.' ORDER BY s.inicio_en ASC LIMIT '.$perPage.' OFFSET '.$offset);
        $stmt->execute($params);$items=array_map([$this,'normalizeSession'],$stmt->fetchAll());
        return ['items'=>$items,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage))],'range'=>['from'=>$from,'to'=>$to]];
    }

    public function options(int $gymId): array
    {
        $scope=[$gymId,$this->demoFlag(),$this->datasetId];
        $stmt=$this->pdo->prepare('SELECT id,nombre,es_principal FROM gimnasio_sedes WHERE gimnasio_id=? AND estado="activa" AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY es_principal DESC,nombre');$stmt->execute($scope);$locations=$stmt->fetchAll();
        $stmt=$this->pdo->prepare('SELECT u.id,CONCAT_WS(" ",u.nombre,u.apellido) nombre,u.email FROM entrenador_gimnasios eg JOIN entrenador_perfiles ep ON ep.id=eg.entrenador_perfil_id JOIN usuarios u ON u.id=ep.usuario_id WHERE eg.gimnasio_id=? AND eg.activo=1 AND ep.estado="activo" AND ep.is_demo=? AND (ep.demo_dataset_id <=> ?) ORDER BY u.nombre,u.apellido');$stmt->execute($scope);$trainers=$stmt->fetchAll();
        $stmt=$this->pdo->prepare('SELECT u.id,CONCAT_WS(" ",u.nombre,u.apellido) nombre,u.email,sp.numero_socio FROM usuario_gimnasio_roles ugr JOIN roles ro ON ro.id=ugr.rol_id JOIN usuarios u ON u.id=ugr.usuario_id JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id WHERE ugr.gimnasio_id=? AND ugr.activo=1 AND sp.estado="activo" AND u.is_demo=? AND (u.demo_dataset_id <=> ?) AND ro.nombre="socio" ORDER BY u.nombre,u.apellido');$stmt->execute($scope);$members=$stmt->fetchAll();
        $stmt=$this->pdo->prepare('SELECT id,nombre,descripcion,sede_id,instructor_id,dia_semana,hora_inicio,hora_fin,cupo_maximo,color,cancelacion_minutos,activa FROM clases WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY activa DESC,nombre');$stmt->execute($scope);$classes=$stmt->fetchAll();
        return ['locations'=>$locations,'trainers'=>$trainers,'members'=>$members,'classes'=>$classes];
    }

    public function createClass(int $gymId, array $data, int $actorId): array
    {
        $this->assertLocation($gymId,$data['sede_id']);$this->assertTrainer($gymId,$data['instructor_id']);
        $first=new DateTimeImmutable($data['first_date']);$weekday=$this->weekday($first);
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('INSERT INTO clases (nombre,descripcion,instructor_id,gimnasio_id,sede_id,dia_semana,hora_inicio,hora_fin,cupo_maximo,cupos_disponibles,color,cancelacion_minutos,activa,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
            $stmt->execute([$data['name'],$data['description'],$data['instructor_id'],$gymId,$data['sede_id'],$weekday,$data['start_time'],$data['end_time'],$data['capacity'],$data['capacity'],$data['color'],$data['cancellation_minutes'],$this->demoFlag(),$this->datasetId]);
            $classId=(int)$this->pdo->lastInsertId();$sessionIds=[];
            for($week=0;$week<$data['repeat_weeks'];$week++){$date=$first->modify('+'.$week.' weeks')->format('Y-m-d');$sessionIds[]=$this->insertSession($classId,$gymId,$data['sede_id'],$data['instructor_id'],$date.' '.$data['start_time'].':00',$date.' '.$data['end_time'].':00',$data['capacity'],$actorId,$data['notes']);}
            $this->pdo->commit();return ['id'=>$classId,'session_ids'=>$sessionIds,'sessions_created'=>count($sessionIds)];
        } catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function updateClass(int $gymId,int $classId,array $data): array
    {
        $before=$this->classDefinition($gymId,$classId);if(!$before)ApiResponder::error(404,'class_not_found','La clase no pertenece al gimnasio activo.');
        $this->assertLocation($gymId,$data['sede_id']);$this->assertTrainer($gymId,$data['instructor_id']);
        $stmt=$this->pdo->prepare('UPDATE clases SET nombre=?,descripcion=?,instructor_id=?,sede_id=?,dia_semana=?,hora_inicio=?,hora_fin=?,cupo_maximo=?,cupos_disponibles=LEAST(cupos_disponibles,?),color=?,cancelacion_minutos=?,activa=? WHERE id=? AND gimnasio_id=?');
        $stmt->execute([$data['name'],$data['description'],$data['instructor_id'],$data['sede_id'],$data['weekday'],$data['start_time'],$data['end_time'],$data['capacity'],$data['capacity'],$data['color'],$data['cancellation_minutes'],$data['active']?1:0,$classId,$gymId]);
        return ['before'=>$before,'after'=>$this->classDefinition($gymId,$classId)];
    }

    public function createSession(int $gymId,int $classId,array $data,int $actorId): array
    {
        $class=$this->classDefinition($gymId,$classId);if(!$class)ApiResponder::error(404,'class_not_found','La clase no pertenece al gimnasio activo.');
        $location=$data['sede_id']?:$class['sede_id'];$trainer=$data['instructor_id']?:$class['instructor_id'];$this->assertLocation($gymId,(int)$location);$this->assertTrainer($gymId,(int)$trainer);
        $id=$this->insertSession($classId,$gymId,(int)$location,(int)$trainer,$data['start_at'],$data['end_at'],$data['capacity'],$actorId,$data['notes']);
        return $this->session($gymId,$id)??[];
    }

    public function updateSession(int $gymId,int $sessionId,array $data): array
    {
        $before=$this->session($gymId,$sessionId,true);if(!$before)ApiResponder::error(404,'session_not_found','La sesión no pertenece al gimnasio activo.');
        if($before['estado']!=='programada')ApiResponder::error(409,'session_not_editable','Sólo se pueden editar sesiones programadas.');
        if($data['capacity']<(int)$before['cupos_reservados'])ApiResponder::error(409,'capacity_below_bookings','El cupo no puede ser menor a las reservas confirmadas.',['capacity'=>'Aumentá el cupo o cancelá reservas antes de reducirlo.']);
        $this->assertLocation($gymId,$data['sede_id']);$this->assertTrainer($gymId,$data['instructor_id']);$this->assertNoOverlap($gymId,$data['instructor_id'],$data['start_at'],$data['end_at'],$sessionId);
        $stmt=$this->pdo->prepare('UPDATE sesiones_clase SET sede_id=?,instructor_id=?,inicio_en=?,fin_en=?,cupo_maximo=?,notas=?,version=version+1 WHERE id=? AND gimnasio_id=?');$stmt->execute([$data['sede_id'],$data['instructor_id'],$data['start_at'],$data['end_at'],$data['capacity'],$data['notes'],$sessionId,$gymId]);
        return ['before'=>$before,'after'=>$this->session($gymId,$sessionId)];
    }

    public function cancelSession(int $gymId,int $sessionId,string $reason,int $actorId): array
    {
        $this->pdo->beginTransaction();try{$session=$this->session($gymId,$sessionId,true);if(!$session)ApiResponder::error(404,'session_not_found','La sesión no pertenece al gimnasio activo.');if($session['estado']==='cancelada'){$this->pdo->commit();return $session;}
            $this->pdo->prepare('UPDATE sesiones_clase SET estado="cancelada",motivo_cancelacion=?,cupos_reservados=0,espera_total=0,version=version+1 WHERE id=?')->execute([$reason,$sessionId]);
            $stmt=$this->pdo->prepare('SELECT id,estado FROM reservas WHERE sesion_clase_id=? AND estado IN ("confirmada","lista_espera") FOR UPDATE');$stmt->execute([$sessionId]);foreach($stmt->fetchAll() as $booking){$this->pdo->prepare('UPDATE reservas SET estado="cancelada",cancelada_en=NOW(),posicion_espera=NULL WHERE id=?')->execute([$booking['id']]);$this->event((int)$booking['id'],'cancelada',(string)$booking['estado'],'cancelada',$actorId,$reason);}
            $this->pdo->commit();return $this->session($gymId,$sessionId)??[];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function roster(int $gymId,int $sessionId): array
    {
        $session=$this->session($gymId,$sessionId);if(!$session)ApiResponder::error(404,'session_not_found','La sesión no pertenece al gimnasio activo.');
        $stmt=$this->pdo->prepare('SELECT r.id,r.usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email,sp.numero_socio,r.estado,r.posicion_espera,r.fecha_reserva,a.estado asistencia_estado,a.fecha_asist,a.notas asistencia_notas FROM reservas r JOIN usuarios u ON u.id=r.usuario_id LEFT JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id AND ugr.gimnasio_id=? LEFT JOIN roles ro ON ro.id=ugr.rol_id AND ro.nombre="socio" LEFT JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id LEFT JOIN asistencias a ON a.reserva_id=r.id WHERE r.sesion_clase_id=? ORDER BY FIELD(r.estado,"confirmada","asistio","no_asistio","lista_espera","cancelada"),r.posicion_espera,r.fecha_reserva');$stmt->execute([$gymId,$sessionId]);
        return ['session'=>$session,'items'=>$stmt->fetchAll()];
    }

    public function book(int $gymId,int $sessionId,int $memberId,int $actorId,string $origin,?string $idempotencyKey): array
    {
        $this->pdo->beginTransaction();
        try {
            $session=$this->session($gymId,$sessionId,true);if(!$session)ApiResponder::error(404,'session_not_found','La sesión no pertenece al gimnasio activo.');
            if($session['estado']!=='programada'||$this->isPast((string)$session['inicio_en'],(string)$session['zona_horaria']))ApiResponder::error(409,'session_unavailable','La sesión ya no admite reservas.');
            $this->assertActiveMember($gymId,$memberId);
            if($idempotencyKey){$stmt=$this->pdo->prepare('SELECT id FROM reservas WHERE usuario_id=? AND idempotency_key=? LIMIT 1');$stmt->execute([$memberId,$idempotencyKey]);$existingId=$stmt->fetchColumn();if($existingId!==false){$this->pdo->commit();return ['booking'=>$this->booking($gymId,(int)$existingId),'idempotent'=>true];}}
            $stmt=$this->pdo->prepare('SELECT * FROM reservas WHERE usuario_id=? AND sesion_clase_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$memberId,$sessionId]);$existing=$stmt->fetch();if($existing&&$existing['estado']!=='cancelada')ApiResponder::error(409,'booking_exists','Ya existe una reserva para esta sesión.');
            $confirmed=(int)$session['cupos_reservados']<(int)$session['cupo_maximo'];$state=$confirmed?'confirmada':'lista_espera';$position=$confirmed?null:(int)$session['espera_total']+1;
            if($existing){$bookingId=(int)$existing['id'];$this->pdo->prepare('UPDATE reservas SET estado=?,posicion_espera=?,origen=?,idempotency_key=?,fecha_reserva=NOW(),confirmada_en=?,cancelada_en=NULL WHERE id=?')->execute([$state,$position,$origin,$idempotencyKey,$confirmed?date('Y-m-d H:i:s'):null,$bookingId]);}
            else{$stmt=$this->pdo->prepare('INSERT INTO reservas (usuario_id,clase_id,sesion_clase_id,fecha_reserva,confirmada_en,estado,posicion_espera,origen,idempotency_key,is_demo,demo_dataset_id) VALUES (?,?,?,NOW(),?,?,?,?,?,?,?)');$stmt->execute([$memberId,$session['clase_id'],$sessionId,$confirmed?date('Y-m-d H:i:s'):null,$state,$position,$origin,$idempotencyKey,$this->demoFlag(),$this->datasetId]);$bookingId=(int)$this->pdo->lastInsertId();}
            $field=$confirmed?'cupos_reservados':'espera_total';$this->pdo->prepare("UPDATE sesiones_clase SET {$field}={$field}+1 WHERE id=?")->execute([$sessionId]);$this->event($bookingId,$confirmed?'creada':'espera',$existing['estado']??null,$state,$actorId,$confirmed?'Reserva confirmada':'Agregado a lista de espera');
            $this->pdo->commit();return ['booking'=>$this->booking($gymId,$bookingId),'idempotent'=>false];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function cancelBooking(int $gymId,int $bookingId,int $actorId,?int $ownerId,string $reason,bool $override=false): array
    {
        $this->pdo->beginTransaction();try{$booking=$this->booking($gymId,$bookingId,true);if(!$booking)ApiResponder::error(404,'booking_not_found','La reserva no pertenece al gimnasio activo.');if($ownerId!==null&&(int)$booking['usuario_id']!==$ownerId)ApiResponder::error(404,'booking_not_found','La reserva no te pertenece.');if($booking['estado']==='cancelada'){$this->pdo->commit();return ['booking'=>$booking,'promoted'=>null,'idempotent'=>true];}if(in_array($booking['estado'],['asistio','no_asistio'],true))ApiResponder::error(409,'booking_closed','La asistencia ya fue cerrada.');
            if(!$override){$timezone=new DateTimeZone((string)($booking['zona_horaria']?:'America/Montevideo'));$deadline=(new DateTimeImmutable($booking['inicio_en'],$timezone))->modify('-'.(int)$booking['cancelacion_minutos'].' minutes');if(new DateTimeImmutable('now',$timezone) >= $deadline)ApiResponder::error(409,'cancellation_closed','El plazo de cancelación de esta reserva ya finalizó.');}
            $old=(string)$booking['estado'];$this->pdo->prepare('UPDATE reservas SET estado="cancelada",posicion_espera=NULL,cancelada_en=NOW() WHERE id=?')->execute([$bookingId]);$this->event($bookingId,'cancelada',$old,'cancelada',$actorId,$reason);
            $promoted=null;if($old==='confirmada'){$this->pdo->prepare('UPDATE sesiones_clase SET cupos_reservados=GREATEST(0,cupos_reservados-1) WHERE id=?')->execute([$booking['sesion_clase_id']]);$promoted=$this->promoteWaitlist((int)$booking['sesion_clase_id'],$actorId);}
            else{$this->pdo->prepare('UPDATE sesiones_clase SET espera_total=GREATEST(0,espera_total-1) WHERE id=?')->execute([$booking['sesion_clase_id']]);$this->reindexWaitlist((int)$booking['sesion_clase_id']);}
            $this->pdo->commit();return ['booking'=>$this->booking($gymId,$bookingId),'promoted'=>$promoted,'idempotent'=>false];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function attendance(int $gymId,int $bookingId,string $state,?string $notes,int $actorId): array
    {
        $this->pdo->beginTransaction();try{$booking=$this->booking($gymId,$bookingId,true);if(!$booking)ApiResponder::error(404,'booking_not_found','La reserva no pertenece al gimnasio activo.');if($booking['estado']==='lista_espera'||$booking['estado']==='cancelada')ApiResponder::error(409,'attendance_not_allowed','Sólo una reserva confirmada admite asistencia.');
            if($booking['sesion_estado']==='cancelada')ApiResponder::error(409,'attendance_not_allowed','No se puede registrar asistencia en una sesión cancelada.');$timezone=new DateTimeZone((string)($booking['zona_horaria']?:'America/Montevideo'));$opens=(new DateTimeImmutable($booking['inicio_en'],$timezone))->modify('-30 minutes');if(new DateTimeImmutable('now',$timezone)<$opens)ApiResponder::error(409,'attendance_not_open','La asistencia se habilita 30 minutos antes del inicio.');
            $bookingState=in_array($state,['presente','tarde'],true)?'asistio':'no_asistio';$stmt=$this->pdo->prepare('INSERT INTO asistencias (reserva_id,estado,fecha_asist,registrada_por,notas) VALUES (?,?,NOW(),?,?) ON DUPLICATE KEY UPDATE estado=VALUES(estado),fecha_asist=NOW(),registrada_por=VALUES(registrada_por),notas=VALUES(notas)');$stmt->execute([$bookingId,$state,$actorId,$notes]);$old=$booking['estado'];$this->pdo->prepare('UPDATE reservas SET estado=? WHERE id=?')->execute([$bookingState,$bookingId]);$this->event($bookingId,'asistencia',$old,$bookingState,$actorId,$state);
            $this->pdo->commit();return $this->booking($gymId,$bookingId)??[];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function myBookings(int $gymId,int $userId): array
    {
        $stmt=$this->pdo->prepare('SELECT r.id,r.estado,r.posicion_espera,r.fecha_reserva,s.id sesion_clase_id,s.inicio_en,s.fin_en,s.zona_horaria,s.estado sesion_estado,c.nombre,c.color,gs.nombre sede_nombre,CONCAT_WS(" ",u.nombre,u.apellido) instructor_nombre,a.estado asistencia_estado FROM reservas r JOIN sesiones_clase s ON s.id=r.sesion_clase_id JOIN clases c ON c.id=s.clase_id JOIN usuarios u ON u.id=s.instructor_id LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id LEFT JOIN asistencias a ON a.reserva_id=r.id WHERE r.usuario_id=? AND s.gimnasio_id=? AND r.is_demo=? AND (r.demo_dataset_id <=> ?) ORDER BY s.inicio_en DESC');$stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);return $stmt->fetchAll();
    }

    private function insertSession(int $classId,int $gymId,int $locationId,int $trainerId,string $start,string $end,int $capacity,int $actorId,?string $notes): int
    {
        $this->assertNoOverlap($gymId,$trainerId,$start,$end);$timezone=$this->gymTimezone($gymId);try{$stmt=$this->pdo->prepare('INSERT INTO sesiones_clase (clase_id,gimnasio_id,sede_id,instructor_id,inicio_en,fin_en,zona_horaria,cupo_maximo,notas,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$classId,$gymId,$locationId,$trainerId,$start,$end,$timezone,$capacity,$notes,$this->demoFlag(),$this->datasetId,$actorId]);return (int)$this->pdo->lastInsertId();}catch(PDOException $error){if($error->getCode()==='23000')ApiResponder::error(409,'session_exists','Ya existe una sesión de esta clase en esa fecha y hora.');throw $error;}
    }
    private function session(int $gymId,int $id,bool $lock=false): ?array{$stmt=$this->pdo->prepare('SELECT s.*,c.nombre,c.descripcion,c.color,c.cancelacion_minutos,gs.nombre sede_nombre,CONCAT_WS(" ",u.nombre,u.apellido) instructor_nombre FROM sesiones_clase s JOIN clases c ON c.id=s.clase_id JOIN usuarios u ON u.id=s.instructor_id LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id WHERE s.id=? AND s.gimnasio_id=? AND s.is_demo=? AND (s.demo_dataset_id <=> ?)'.($lock?' FOR UPDATE':''));$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?$this->normalizeSession($row):null;}
    private function booking(int $gymId,int $id,bool $lock=false): ?array{$stmt=$this->pdo->prepare('SELECT r.*,s.gimnasio_id,s.inicio_en,s.fin_en,s.zona_horaria,s.estado sesion_estado,c.nombre,c.cancelacion_minutos FROM reservas r JOIN sesiones_clase s ON s.id=r.sesion_clase_id JOIN clases c ON c.id=s.clase_id WHERE r.id=? AND s.gimnasio_id=? AND r.is_demo=? AND (r.demo_dataset_id <=> ?)'.($lock?' FOR UPDATE':''));$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?:null;}
    private function classDefinition(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT * FROM clases WHERE id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?:null;}
    private function assertLocation(int $gymId,int $id): void{$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM gimnasio_sedes WHERE id=? AND gimnasio_id=? AND estado="activa"');$stmt->execute([$id,$gymId]);if((int)$stmt->fetchColumn()!==1)ApiResponder::error(422,'location_not_found','Seleccioná una sede activa del gimnasio.',['sede_id'=>'La sede no pertenece al gimnasio activo.']);}
    private function assertTrainer(int $gymId,int $id): void{$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM entrenador_gimnasios eg JOIN entrenador_perfiles ep ON ep.id=eg.entrenador_perfil_id WHERE eg.gimnasio_id=? AND ep.usuario_id=? AND eg.activo=1 AND ep.estado="activo"');$stmt->execute([$gymId,$id]);if((int)$stmt->fetchColumn()!==1)ApiResponder::error(422,'trainer_not_found','Seleccioná un entrenador activo del gimnasio.',['instructor_id'=>'El entrenador no pertenece al gimnasio activo.']);}
    private function assertActiveMember(int $gymId,int $userId): void{$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM usuario_gimnasio_roles ugr JOIN roles ro ON ro.id=ugr.rol_id JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id WHERE ugr.usuario_id=? AND ugr.gimnasio_id=? AND ugr.activo=1 AND sp.estado="activo" AND ro.nombre="socio"');$stmt->execute([$userId,$gymId]);if((int)$stmt->fetchColumn()!==1)ApiResponder::error(403,'member_inactive','Tu perfil de socio no está activo en este gimnasio.');$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM membresias WHERE usuario_id=? AND gimnasio_id=? AND estado="activa" AND fecha_inicio<=CURRENT_DATE AND fecha_vencimiento>=CURRENT_DATE');$stmt->execute([$userId,$gymId]);if((int)$stmt->fetchColumn()===0)ApiResponder::error(403,'membership_required','Necesitás una membresía activa para reservar.');}
    private function assertNoOverlap(int $gymId,int $trainerId,string $start,string $end,?int $ignore=null): void{$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM sesiones_clase WHERE gimnasio_id=? AND instructor_id=? AND estado="programada" AND inicio_en<? AND fin_en>? AND (? IS NULL OR id<>?)');$stmt->execute([$gymId,$trainerId,$end,$start,$ignore,$ignore]);if((int)$stmt->fetchColumn()>0)ApiResponder::error(409,'trainer_schedule_conflict','El entrenador ya tiene otra sesión en ese horario.',['inicio_en'=>'Elegí otro horario o entrenador.']);}
    private function promoteWaitlist(int $sessionId,int $actorId): ?array{$stmt=$this->pdo->prepare('SELECT id FROM reservas WHERE sesion_clase_id=? AND estado="lista_espera" ORDER BY posicion_espera,fecha_reserva,id LIMIT 1 FOR UPDATE');$stmt->execute([$sessionId]);$id=$stmt->fetchColumn();if($id===false)return null;$this->pdo->prepare('UPDATE reservas SET estado="confirmada",posicion_espera=NULL,confirmada_en=NOW() WHERE id=?')->execute([$id]);$this->pdo->prepare('UPDATE sesiones_clase SET cupos_reservados=cupos_reservados+1,espera_total=GREATEST(0,espera_total-1) WHERE id=?')->execute([$sessionId]);$this->event((int)$id,'promovida','lista_espera','confirmada',$actorId,'Cupo liberado');$this->reindexWaitlist($sessionId);return ['booking_id'=>(int)$id];}
    private function reindexWaitlist(int $sessionId): void{$stmt=$this->pdo->prepare('SELECT id FROM reservas WHERE sesion_clase_id=? AND estado="lista_espera" ORDER BY posicion_espera,fecha_reserva,id');$stmt->execute([$sessionId]);$update=$this->pdo->prepare('UPDATE reservas SET posicion_espera=? WHERE id=?');$position=1;foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id)$update->execute([$position++,$id]);}
    private function event(int $bookingId,string $type,?string $before,string $after,int $actorId,?string $reason): void{$this->pdo->prepare('INSERT INTO reserva_eventos (reserva_id,tipo,estado_anterior,estado_nuevo,actor_usuario_id,motivo) VALUES (?,?,?,?,?,?)')->execute([$bookingId,$type,$before,$after,$actorId,$reason]);}
    private function gymTimezone(int $gymId): string{$stmt=$this->pdo->prepare('SELECT zona_horaria FROM gimnasios WHERE id=?');$stmt->execute([$gymId]);return (string)($stmt->fetchColumn()?:'America/Montevideo');}
    private function weekday(DateTimeImmutable $date): string{return [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'][(int)$date->format('N')];}
    private function validDate(string $date): ?string{$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date?$date:null;}
    private function isPast(string $dateTime,string $timezone): bool{$zone=new DateTimeZone($timezone?:'America/Montevideo');return new DateTimeImmutable($dateTime,$zone)<=new DateTimeImmutable('now',$zone);}
    private function normalizeSession(array $row): array{foreach(['id','clase_id','sede_id','instructor_id','cupo_maximo','cupos_reservados','cupos_disponibles','espera_total','version','reserva_id','posicion_espera'] as $key)if(array_key_exists($key,$row)&&$row[$key]!==null)$row[$key]=(int)$row[$key];$row['asistencia_habilitada']=$this->attendanceOpen((string)$row['inicio_en'],(string)$row['zona_horaria'])&&($row['estado']??'programada')!=='cancelada';return $row;}
    private function attendanceOpen(string $dateTime,string $timezone): bool{$zone=new DateTimeZone($timezone?:'America/Montevideo');return new DateTimeImmutable('now',$zone)>=(new DateTimeImmutable($dateTime,$zone))->modify('-30 minutes');}
    private function demoFlag(): int{return $this->datasetId===null?0:1;}
}
