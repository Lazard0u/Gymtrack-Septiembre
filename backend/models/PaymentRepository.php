<?php

declare(strict_types=1);

final class PaymentRepository
{
    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo=Database::conectar();$this->datasetId=$datasetId;
    }

    public function adminPayments(int $gymId,array $query): array
    {
        $query=self::query($query);$where=['p.gimnasio_id=?','p.is_demo=?','(p.demo_dataset_id <=> ?)'];$params=[$gymId,$this->demoFlag(),$this->datasetId];
        if($query['q']!==''){$like='%'.addcslashes($query['q'],'%_\\').'%';$where[]='(u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR p.referencia_externa LIKE ? OR p.concepto LIKE ?)';array_push($params,$like,$like,$like,$like,$like);}
        if($query['status']!==''){$where[]='p.estado=?';$params[]=$query['status'];}
        if($query['method']!==''){$where[]='p.metodo=?';$params[]=$query['method'];}
        if($query['from']!==''){$where[]='DATE(COALESCE(p.fecha_pago,p.creado_en))>=?';$params[]=$query['from'];}
        if($query['to']!==''){$where[]='DATE(COALESCE(p.fecha_pago,p.creado_en))<=?';$params[]=$query['to'];}
        $sort=['member'=>'u.nombre','amount'=>'p.monto','paid_at'=>'COALESCE(p.fecha_pago,p.creado_en)','method'=>'p.metodo','status'=>'p.estado'][$query['sort']]??'p.creado_en';
        $base=' FROM pagos p JOIN membresias m ON m.id=p.membresia_id JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE '.implode(' AND ',$where);
        return $this->page('SELECT p.id,p.membresia_id,p.monto,p.moneda,p.metodo,p.estado,p.proveedor,p.referencia_externa,p.modo,p.concepto,p.fecha_pago,p.fecha_vencimiento,p.aprobado_en,p.reembolsado_en,p.creado_en,COALESCE(pm.nombre,m.plan) plan,u.id usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email usuario_email'.$base.' ORDER BY '.$sort.' '.strtoupper($query['direction']),'SELECT COUNT(*)'.$base,$params,$query);
    }

    public function options(int $gymId): array
    {
        $stmt=$this->pdo->prepare('SELECT m.id,m.estado,m.precio_pagado,COALESCE(pm.precio,m.precio_pagado) monto,COALESCE(pm.moneda,"UYU") moneda,COALESCE(pm.nombre,m.plan) plan,u.id usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email FROM membresias m JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE m.gimnasio_id=? AND m.is_demo=? AND (m.demo_dataset_id <=> ?) ORDER BY u.nombre,u.apellido,m.creado_en DESC');$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);
        return ['memberships'=>$stmt->fetchAll(),'provider'=>['name'=>'mercado_pago','configured'=>(new MercadoPagoProvider())->isConfigured(),'mode'=>$this->mode()]];
    }

    public function createManual(int $gymId,int $actorId,array $data,string $key): array
    {
        $this->pdo->beginTransaction();
        try{
            $existing=$this->paymentByKey($gymId,$key,true);if($existing){$this->pdo->commit();return $existing;}
            $stmt=$this->pdo->prepare('SELECT m.*,COALESCE(pm.nombre,m.plan) plan_nombre,COALESCE(pm.moneda,"UYU") moneda,COALESCE(pm.duracion_dias,30) duracion_dias FROM membresias m LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE m.id=? AND m.gimnasio_id=? AND m.is_demo=? AND (m.demo_dataset_id <=> ?) FOR UPDATE');$stmt->execute([$data['membership_id'],$gymId,$this->demoFlag(),$this->datasetId]);$membership=$stmt->fetch();if(!$membership)ApiResponder::error(422,'membership_not_found','La membresía no pertenece al gimnasio activo.',['membership_id'=>'Seleccioná una membresía válida.']);
            $reference='GT-MAN-'.strtoupper(str_replace('-','',$key));$paidAt=$data['paid_at'].' '.($data['paid_time']??'12:00').':00';
            $stmt=$this->pdo->prepare('INSERT INTO pagos (membresia_id,gimnasio_id,monto,moneda,metodo,estado,proveedor,referencia_externa,idempotency_key,modo,concepto,fecha_pago,aprobado_en,metadata_json,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,"aprobado","manual",?,?,?, ?,?,?,?, ?,?,?)');
            $metadata=json_encode(['notes'=>$data['notes']?:null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $stmt->execute([(int)$membership['id'],$gymId,$data['amount'],$data['currency'],$data['method'],$reference,$key,$this->mode(),$data['concept'],$paidAt,$paidAt,$metadata,$this->demoFlag(),$this->datasetId,$actorId]);$paymentId=(int)$this->pdo->lastInsertId();
            $this->activateMembership($membership,$paidAt,$actorId,'Pago manual aprobado');
            $this->event($paymentId,'manual',null,'manual.approved',null,'aprobado',['reference'=>$reference]);
            $this->pdo->commit();return $this->payment($gymId,$paymentId)??throw new RuntimeException('No se pudo leer el pago creado.');
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function finance(int $gymId): array
    {
        $this->expirePending($gymId);$scope=[$gymId,$this->demoFlag(),$this->datasetId];
        $sum=function(string $where,array $extra=[])use($scope):float{$stmt=$this->pdo->prepare('SELECT COALESCE(SUM(monto),0) FROM pagos WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND '.$where);$stmt->execute(array_merge($scope,$extra));return (float)$stmt->fetchColumn();};
        $current=$sum('estado="aprobado" AND fecha_pago>=DATE_FORMAT(CURRENT_DATE,"%Y-%m-01") AND fecha_pago<DATE_ADD(DATE_FORMAT(CURRENT_DATE,"%Y-%m-01"),INTERVAL 1 MONTH)');
        $previous=$sum('estado="aprobado" AND fecha_pago>=DATE_SUB(DATE_FORMAT(CURRENT_DATE,"%Y-%m-01"),INTERVAL 1 MONTH) AND fecha_pago<DATE_FORMAT(CURRENT_DATE,"%Y-%m-01")');
        $stmt=$this->pdo->prepare('SELECT estado,COUNT(*) cantidad,COALESCE(SUM(monto),0) monto FROM pagos WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) GROUP BY estado');$stmt->execute($scope);$statuses=[];foreach($stmt->fetchAll() as $row)$statuses[$row['estado']]=['count'=>(int)$row['cantidad'],'amount'=>(float)$row['monto']];
        $stmt=$this->pdo->prepare('SELECT DATE_FORMAT(fecha_pago,"%Y-%m") periodo,COALESCE(SUM(monto),0) total FROM pagos WHERE gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND estado="aprobado" AND fecha_pago>=DATE_SUB(DATE_FORMAT(CURRENT_DATE,"%Y-%m-01"),INTERVAL 5 MONTH) GROUP BY periodo ORDER BY periodo');$stmt->execute($scope);$found=[];foreach($stmt->fetchAll() as $row)$found[$row['periodo']]=(float)$row['total'];$months=[];for($i=5;$i>=0;$i--){$date=(new DateTimeImmutable('first day of this month'))->modify('-'.$i.' months');$key=$date->format('Y-m');$months[]=['period'=>$key,'label'=>$date->format('m/Y'),'total'=>$found[$key]??0.0];}
        return [
            'currency'=>$this->currency($gymId),'current_month'=>$current,'previous_month'=>$previous,
            'change_percent'=>$previous>0?round((($current-$previous)/$previous)*100,1):null,
            'debt_total'=>$sum('estado IN ("pendiente","vencido")'),'statuses'=>$statuses,'months'=>$months,
            'by_membership'=>$this->breakdown($gymId,'COALESCE(pm.nombre,m.plan)','JOIN membresias m ON m.id=p.membresia_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id'),
            'by_method'=>$this->breakdown($gymId,'p.metodo',''),
            'by_gym'=>$this->byGym($gymId),
        ];
    }

    public function memberPayments(int $gymId,int $userId): array
    {
        $this->expirePending($gymId);$stmt=$this->pdo->prepare('SELECT p.id,p.monto,p.moneda,p.metodo,p.estado,p.proveedor,p.referencia_externa,p.concepto,p.fecha_pago,p.fecha_vencimiento,p.creado_en,m.id membresia_id,m.estado membresia_estado,COALESCE(pm.nombre,m.plan) plan FROM pagos p JOIN membresias m ON m.id=p.membresia_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE p.gimnasio_id=? AND m.usuario_id=? AND p.is_demo=? AND (p.demo_dataset_id <=> ?) ORDER BY p.creado_en DESC');$stmt->execute([$gymId,$userId,$this->demoFlag(),$this->datasetId]);
        $plans=$this->pdo->prepare('SELECT id,nombre,descripcion,duracion_dias,precio,moneda,beneficios_json FROM planes_membresia WHERE gimnasio_id=? AND estado="activo" AND is_demo=? AND (demo_dataset_id <=> ?) ORDER BY precio');$plans->execute([$gymId,$this->demoFlag(),$this->datasetId]);$available=$plans->fetchAll();foreach($available as &$plan){$plan['beneficios']=json_decode((string)$plan['beneficios_json'],true)?:[];unset($plan['beneficios_json']);}
        return ['items'=>$stmt->fetchAll(),'plans'=>$available,'provider'=>['name'=>'mercado_pago','configured'=>(new MercadoPagoProvider())->isConfigured(),'mode'=>$this->mode()]];
    }

    public function createCheckout(int $gymId,int $userId,int $planId,string $key,PaymentProviderInterface $provider): array
    {
        if(!$provider->isConfigured())ApiResponder::error(503,'payment_provider_unavailable','Mercado Pago todavía no está configurado. Podés consultar los planes sin iniciar cobros.');
        $this->pdo->beginTransaction();
        try{
            $existing=$this->paymentByKey($gymId,$key,true);if($existing){$this->pdo->commit();return $this->checkoutPayload($existing);}
            $lock=$this->pdo->prepare('SELECT id,email FROM usuarios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) FOR UPDATE');$lock->execute([$userId,$this->demoFlag(),$this->datasetId]);$user=$lock->fetch();if(!$user)ApiResponder::error(403,'dataset_scope_mismatch','La cuenta no pertenece al conjunto de datos activo.');
            $assigned=$this->pdo->prepare('SELECT COUNT(*) FROM usuario_gimnasio_roles ugr JOIN roles r ON r.id=ugr.rol_id WHERE ugr.usuario_id=? AND ugr.gimnasio_id=? AND ugr.activo=1 AND r.nombre="socio"');$assigned->execute([$userId,$gymId]);if((int)$assigned->fetchColumn()!==1)ApiResponder::error(403,'gym_scope_mismatch','No tenés una asociación de socio con este gimnasio.');
            $pending=$this->pdo->prepare('SELECT p.* FROM pagos p JOIN membresias m ON m.id=p.membresia_id WHERE p.gimnasio_id=? AND m.usuario_id=? AND m.plan_id=? AND p.estado="pendiente" AND p.fecha_vencimiento>NOW() ORDER BY p.id DESC LIMIT 1');$pending->execute([$gymId,$userId,$planId]);if($row=$pending->fetch()){$this->pdo->commit();return $this->checkoutPayload($row);}
            $planStmt=$this->pdo->prepare('SELECT * FROM planes_membresia WHERE id=? AND gimnasio_id=? AND estado="activo" AND is_demo=? AND (demo_dataset_id <=> ?)');$planStmt->execute([$planId,$gymId,$this->demoFlag(),$this->datasetId]);$plan=$planStmt->fetch();if(!$plan)ApiResponder::error(422,'plan_not_available','El plan seleccionado no está disponible.',['plan_id'=>'Elegí un plan activo del gimnasio.']);
            $start=new DateTimeImmutable('today');$end=$start->modify('+'.(int)$plan['duracion_dias'].' days');$legacy=(int)$plan['duracion_dias']>=300?'anual':((int)$plan['duracion_dias']>=80?'trimestral':'mensual');
            $stmt=$this->pdo->prepare('INSERT INTO membresias (usuario_id,numero_socio,gimnasio_id,plan_id,plan,fecha_inicio,fecha_vencimiento,estado,precio_pagado,is_demo,demo_dataset_id) VALUES (?,NULL,?,?,?,?,?,"pendiente_pago",?,?,?)');$stmt->execute([$userId,$gymId,$planId,$legacy,$start->format('Y-m-d'),$end->format('Y-m-d'),$plan['precio'],$this->demoFlag(),$this->datasetId]);$membershipId=(int)$this->pdo->lastInsertId();
            $reference='GT-'.strtoupper(str_replace('-','',$key));$expires=(new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');$concept='Membresía '.$plan['nombre'];
            $stmt=$this->pdo->prepare('INSERT INTO pagos (membresia_id,gimnasio_id,monto,moneda,metodo,estado,proveedor,referencia_externa,idempotency_key,modo,concepto,fecha_vencimiento,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?, ?,"mercado_pago","pendiente","mercado_pago",?,?,?,?,?,?,?,?)');$stmt->execute([$membershipId,$gymId,$plan['precio'],$plan['moneda'],$reference,$key,$this->mode(),$concept,$expires,$this->demoFlag(),$this->datasetId,$userId]);$paymentId=(int)$this->pdo->lastInsertId();$this->event($paymentId,'mercado_pago',null,'checkout.created',null,'pendiente',['reference'=>$reference]);
            $this->pdo->commit();
            $checkout=$provider->createCheckout(['plan_id'=>$planId,'concepto'=>$concept,'monto'=>$plan['precio'],'moneda'=>$plan['moneda'],'email'=>$user['email'],'referencia_externa'=>$reference,'fecha_vencimiento'=>$expires,'idempotency_key'=>$key]);
            $metadata=json_encode(['checkout_url'=>$checkout['checkout_url'],'production_url'=>$checkout['production_url']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$stmt=$this->pdo->prepare('UPDATE pagos SET preferencia_externa=?,metadata_json=? WHERE id=?');$stmt->execute([$checkout['preference_id'],$metadata,$paymentId]);
            return ['payment'=>$this->payment($gymId,$paymentId),'checkout_url'=>$checkout['checkout_url']];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();if(isset($paymentId)){$this->pdo->prepare('UPDATE pagos SET estado="rechazado",rechazado_en=NOW() WHERE id=? AND estado="pendiente"')->execute([$paymentId]);}throw $error;}
    }

    public function applyProviderUpdate(array $providerData,string $eventId,array $payload): array
    {
        $reference=trim((string)$providerData['external_reference']);if($reference==='')throw new RuntimeException('La notificación no contiene referencia externa.');
        $this->pdo->beginTransaction();
        try{
            $dup=$this->pdo->prepare('SELECT pago_id FROM pago_eventos WHERE proveedor="mercado_pago" AND evento_externo_id=?');$dup->execute([$eventId]);if($id=$dup->fetchColumn()){$this->pdo->commit();return ['duplicate'=>true,'payment_id'=>(int)$id];}
            $stmt=$this->pdo->prepare('SELECT p.*,m.usuario_id,m.estado membresia_estado,COALESCE(pm.duracion_dias,30) duracion_dias FROM pagos p JOIN membresias m ON m.id=p.membresia_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE p.referencia_externa=? AND p.proveedor="mercado_pago" FOR UPDATE');$stmt->execute([$reference]);$payment=$stmt->fetch();if(!$payment)throw new RuntimeException('La referencia externa no pertenece a GymTrack.');
            if(abs((float)$payment['monto']-(float)$providerData['amount'])>0.01||strtoupper((string)$payment['moneda'])!==strtoupper((string)$providerData['currency']))throw new RuntimeException('El importe o la moneda de la notificación no coincide.');
            $map=['approved'=>'aprobado','pending'=>'pendiente','in_process'=>'pendiente','rejected'=>'rechazado','cancelled'=>'cancelado','refunded'=>'reembolsado','charged_back'=>'reembolsado'];$new=$map[$providerData['status']]??'pendiente';$old=(string)$payment['estado'];$new=$this->providerState($old,$new);
            $approved=$new==='aprobado'?($providerData['approved_at']?:date('Y-m-d H:i:s')):null;$rejected=$new==='rechazado'?date('Y-m-d H:i:s'):null;$refunded=$new==='reembolsado'?date('Y-m-d H:i:s'):null;$metadata=json_encode(['provider_payment_id'=>$providerData['provider_payment_id'],'status_detail'=>$providerData['status_detail'],'live_mode'=>$providerData['live_mode']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $update=$this->pdo->prepare('UPDATE pagos SET estado=?,metodo=?,modo=?,fecha_pago=IF(?="aprobado",COALESCE(fecha_pago,?),fecha_pago),aprobado_en=COALESCE(?,aprobado_en),rechazado_en=COALESCE(?,rechazado_en),reembolsado_en=COALESCE(?,reembolsado_en),metadata_json=? WHERE id=?');$update->execute([$new,$providerData['method'],$providerData['live_mode']?'produccion':'prueba',$new,$approved,$approved,$rejected,$refunded,$metadata,(int)$payment['id']]);
            if($new==='aprobado')$this->activateMembership($payment,$approved??date('Y-m-d H:i:s'),null,'Pago de Mercado Pago confirmado');
            $this->event((int)$payment['id'],'mercado_pago',$eventId,'payment.'.$providerData['status'],$old,$new,$payload);
            $this->pdo->commit();return ['duplicate'=>false,'payment_id'=>(int)$payment['id'],'state'=>$new];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function refund(int $gymId,int $paymentId,int $actorId,string $reason,string $key,PaymentProviderInterface $provider): array
    {
        $payment=$this->payment($gymId,$paymentId);if(!$payment)ApiResponder::error(404,'payment_not_found','El pago no pertenece al gimnasio activo.');$check=$this->pdo->prepare('SELECT id FROM pago_reembolsos WHERE pago_id=? AND idempotency_key=?');$check->execute([$paymentId,$key]);if($check->fetchColumn())return $payment;if($payment['estado']!=='aprobado')ApiResponder::error(409,'payment_not_refundable','Sólo se puede reembolsar un pago aprobado.');
        $providerReference=null;if($payment['proveedor']==='mercado_pago'){$metadata=json_decode((string)($payment['metadata_json']??'{}'),true)?:[];$providerId=(string)($metadata['provider_payment_id']??'');if($providerId==='')ApiResponder::error(409,'provider_payment_missing','El pago aún no tiene una referencia confirmada del proveedor.');$remote=$provider->refund($providerId,(float)$payment['monto'],$key);$providerReference=$remote['id'];}
        $this->pdo->beginTransaction();try{$stmt=$this->pdo->prepare('INSERT INTO pago_reembolsos (pago_id,monto,estado,referencia_externa,idempotency_key,motivo,solicitado_por,completado_en) VALUES (?, ?,"aprobado",?,?,?,?,NOW())');$stmt->execute([$paymentId,$payment['monto'],$providerReference,$key,$reason,$actorId]);$this->pdo->prepare('UPDATE pagos SET estado="reembolsado",reembolsado_en=NOW() WHERE id=? AND gimnasio_id=?')->execute([$paymentId,$gymId]);$this->event($paymentId,(string)$payment['proveedor'],$providerReference,'refund.approved','aprobado','reembolsado',['reason'=>$reason]);$this->pdo->commit();return $this->payment($gymId,$paymentId)??[];}catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function reportRows(int $gymId,string $module,array $filters): array
    {
        if($module==='finance'){$finance=$this->finance($gymId);$rows=array_map(fn(array $m):array=>[$m['label'],number_format((float)$m['total'],2,'.','')],$finance['months']);return ['title'=>'Ingresos de los últimos seis meses','headers'=>['Período','Ingresos '.$finance['currency']],'rows'=>$rows];}
        $rows=[];$page=1;do{$result=$this->adminPayments($gymId,array_merge($filters,['page'=>$page,'per_page'=>50]));foreach($result['items'] as $row)$rows[]=[(string)$row['referencia_externa'],(string)$row['usuario_nombre'],(string)$row['plan'],(string)$row['estado'],(string)$row['metodo'],(string)$row['monto'],(string)$row['moneda'],(string)($row['fecha_pago']??$row['creado_en'])];$page++;}while($page<=(int)$result['pagination']['total_pages']);return ['title'=>'Transacciones','headers'=>['Referencia','Socio','Plan','Estado','Método','Importe','Moneda','Fecha'],'rows'=>$rows];
    }

    public static function query(array $input): array
    {
        $date=static fn(string $key):string=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($input[$key]??''))?(string)$input[$key]:'';$statuses=['pendiente','aprobado','rechazado','vencido','reembolsado','cancelado'];
        return ['page'=>max(1,(int)($input['page']??1)),'per_page'=>max(10,min(50,(int)($input['per_page']??20))),'q'=>mb_substr(trim((string)($input['q']??'')),0,100),'status'=>in_array((string)($input['status']??''),$statuses,true)?(string)$input['status']:'','method'=>mb_substr(trim((string)($input['method']??'')),0,40),'from'=>$date('from'),'to'=>$date('to'),'sort'=>mb_substr((string)($input['sort']??''),0,30),'direction'=>(string)($input['direction']??'desc')==='asc'?'asc':'desc'];
    }

    private function activateMembership(array $membership,string $paidAt,?int $actor,string $reason): void
    {
        if(($membership['membresia_estado']??$membership['estado']??'')==='activa')return;$start=new DateTimeImmutable(substr($paidAt,0,10));$end=$start->modify('+'.(int)($membership['duracion_dias']??30).' days');$id=(int)($membership['membresia_id']??$membership['id']);$old=(string)($membership['membresia_estado']??$membership['estado']??'pendiente_pago');$this->pdo->prepare('UPDATE membresias SET estado="activa",fecha_inicio=?,fecha_vencimiento=? WHERE id=?')->execute([$start->format('Y-m-d'),$end->format('Y-m-d'),$id]);$this->pdo->prepare('INSERT INTO membresia_historial (membresia_id,estado_anterior,estado_nuevo,motivo,actor_usuario_id) VALUES (?, ?,"activa",?,?)')->execute([$id,$old,$reason,$actor]);
    }

    private function payment(int $gymId,int $id): ?array{$stmt=$this->pdo->prepare('SELECT p.*,m.usuario_id,m.estado membresia_estado,COALESCE(pm.nombre,m.plan) plan,COALESCE(pm.duracion_dias,30) duracion_dias,u.email usuario_email,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre FROM pagos p JOIN membresias m ON m.id=p.membresia_id JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE p.id=? AND p.gimnasio_id=? AND p.is_demo=? AND (p.demo_dataset_id <=> ?)');$stmt->execute([$id,$gymId,$this->demoFlag(),$this->datasetId]);$row=$stmt->fetch();return $row?:null;}
    private function paymentByKey(int $gymId,string $key,bool $lock=false): ?array{$sql='SELECT id FROM pagos WHERE gimnasio_id=? AND idempotency_key=?'.($lock?' FOR UPDATE':'');$stmt=$this->pdo->prepare($sql);$stmt->execute([$gymId,$key]);$id=$stmt->fetchColumn();return $id?$this->payment($gymId,(int)$id):null;}
    private function checkoutPayload(array $payment): array{$metadata=json_decode((string)($payment['metadata_json']??'{}'),true)?:[];return ['payment'=>$payment,'checkout_url'=>$metadata['checkout_url']??null];}
    private function providerState(string $current,string $incoming): string
    {
        if($current==='reembolsado')return $current;
        if($current==='aprobado'&&$incoming!=='reembolsado')return $current;
        if(in_array($current,['rechazado','cancelado','vencido'],true)&&$incoming==='pendiente')return $current;
        return $incoming;
    }
    private function event(int $paymentId,string $provider,?string $eventId,string $type,?string $old,string $new,array $payload): void{$safe=['type'=>$type,'provider_reference'=>$payload['provider_payment_id']??$payload['id']??$payload['reference']??null,'status'=>$payload['status']??null];$raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$stmt=$this->pdo->prepare('INSERT INTO pago_eventos (pago_id,proveedor,evento_externo_id,tipo,estado_anterior,estado_nuevo,payload_hash,payload_json,request_id) VALUES (?,?,?,?,?,?,?,?,?)');$stmt->execute([$paymentId,$provider,$eventId,$type,$old,$new,hash('sha256',$raw),json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),ApiResponder::requestId()]);}
    private function expirePending(int $gymId): void{$stmt=$this->pdo->prepare('UPDATE pagos SET estado="vencido" WHERE gimnasio_id=? AND estado="pendiente" AND fecha_vencimiento<NOW() AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);}
    private function breakdown(int $gymId,string $label,string $joins): array{$stmt=$this->pdo->prepare('SELECT '.$label.' label,COALESCE(SUM(p.monto),0) total,COUNT(*) cantidad FROM pagos p '.$joins.' WHERE p.gimnasio_id=? AND p.estado="aprobado" AND p.is_demo=? AND (p.demo_dataset_id <=> ?) GROUP BY label ORDER BY total DESC');$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);return array_map(static fn(array $r):array=>['label'=>(string)$r['label'],'total'=>(float)$r['total'],'count'=>(int)$r['cantidad']],$stmt->fetchAll());}
    private function byGym(int $gymId): array{$stmt=$this->pdo->prepare('SELECT g.nombre label,COALESCE(SUM(p.monto),0) total,COUNT(p.id) cantidad FROM gimnasios g LEFT JOIN pagos p ON p.gimnasio_id=g.id AND p.estado="aprobado" AND p.is_demo=? AND (p.demo_dataset_id <=> ?) WHERE g.id=? GROUP BY g.id,g.nombre');$stmt->execute([$this->demoFlag(),$this->datasetId,$gymId]);return array_map(static fn(array $r):array=>['label'=>(string)$r['label'],'total'=>(float)$r['total'],'count'=>(int)$r['cantidad']],$stmt->fetchAll());}
    private function currency(int $gymId): string{$stmt=$this->pdo->prepare('SELECT moneda FROM pagos WHERE gimnasio_id=? AND estado="aprobado" AND is_demo=? AND (demo_dataset_id <=> ?) GROUP BY moneda ORDER BY COUNT(*) DESC LIMIT 1');$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);return (string)($stmt->fetchColumn()?:'UYU');}
    private function page(string $select,string $count,array $params,array $query): array{$c=$this->pdo->prepare($count);$c->execute($params);$total=(int)$c->fetchColumn();$offset=($query['page']-1)*$query['per_page'];$s=$this->pdo->prepare($select.' LIMIT '.$query['per_page'].' OFFSET '.$offset);$s->execute($params);return ['items'=>$s->fetchAll(),'pagination'=>['page'=>$query['page'],'per_page'=>$query['per_page'],'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$query['per_page']))]];}
    private function mode(): string{return strtolower((string)(getenv('PAYMENT_MODE')?:'test'))==='production'?'produccion':'prueba';}
    private function demoFlag(): int{return $this->datasetId===null?0:1;}
}
