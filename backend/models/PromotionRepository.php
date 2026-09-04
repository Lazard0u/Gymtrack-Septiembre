<?php
/**
 * Acceso a datos PromotionRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class PromotionRepository
{
    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
    }

    public function list(int $gymId, array $query): array
    {
        $page = $this->queryInteger($query['page'] ?? 1,1,100000,1);
        $perPage = $this->queryInteger($query['per_page'] ?? 20,10,50,20);
        $where = [
            'pg.gimnasio_id=?',
            'p.eliminado_en IS NULL',
            'p.is_demo=?',
            '(p.demo_dataset_id <=> ?)',
            'pg.is_demo=?',
            '(pg.demo_dataset_id <=> ?)',
        ];
        $params = [$gymId,$this->demoFlag(),$this->datasetId,$this->demoFlag(),$this->datasetId];
        $rawStatus=$query['status']??'';if(!is_string($rawStatus))ApiResponder::error(422,'validation_error','Revisá los filtros.',['status'=>'Seleccioná un estado válido.']);$status = trim($rawStatus);
        if ($status !== '') {
            if (!in_array($status, ['borrador','programada','activa','pausada','finalizada'], true)) {
                ApiResponder::error(422,'validation_error','Revisá los filtros.',['status'=>'Seleccioná un estado válido.']);
            }
            $where[] = 'p.estado=?';
            $params[] = $status;
        }
        $rawSearch=$query['q']??'';if(!is_string($rawSearch))ApiResponder::error(422,'validation_error','Revisá los filtros.',['q'=>'Ingresá una búsqueda válida.']);$search = mb_substr(trim($rawSearch), 0, 100);
        if ($search !== '') {
            $where[] = '(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.codigo_descuento LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params,$like,$like,$like);
        }
        $base = ' FROM promociones p JOIN promocion_gimnasios pg ON pg.promocion_id=p.id WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(DISTINCT p.id)' . $base);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.*,
                    (SELECT COUNT(DISTINCT e.usuario_id) FROM notificacion_envios e
                     WHERE e.promocion_id=p.id AND e.is_demo=p.is_demo
                       AND (e.demo_dataset_id <=> p.demo_dataset_id)) destinatarios_total'
            . $base . ' ORDER BY p.actualizado_en DESC,p.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $items = array_map(fn(array $row): array => $this->normalize($row, true), $stmt->fetchAll());
        return ['items'=>$items,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int) ceil($total/$perPage))]];
    }

    public function find(int $gymId, int $promotionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.* FROM promociones p
             JOIN promocion_gimnasios pg ON pg.promocion_id=p.id
             WHERE p.id=? AND pg.gimnasio_id=? AND p.eliminado_en IS NULL
               AND p.is_demo=? AND (p.demo_dataset_id <=> ?)
               AND pg.is_demo=? AND (pg.demo_dataset_id <=> ?)
             LIMIT 1'
        );
        $stmt->execute([$promotionId,$gymId,$this->demoFlag(),$this->datasetId,$this->demoFlag(),$this->datasetId]);
        $row = $stmt->fetch();
        return $row ? $this->normalize($row, true) : null;
    }

    public function create(int $primaryGymId, array $data, int $actorId): array
    {
        $this->assertGymsInScope($data['gym_ids']);
        $this->assertImage($data['gym_ids'], $data['image_file_id']);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO promociones
                 (gimnasio_id,nombre,descripcion,estado,audiencia,inicio_en,fin_en,zona_horaria,tipo_descuento,valor_descuento,moneda,codigo_descuento,imagen_archivo_id,canales_json,version,creado_por,is_demo,demo_dataset_id)
                 VALUES (?,?,?,"borrador",?,?,?,?,?,?,?,?,?,?,1,?,?,?)'
            );
            $stmt->execute([
                $primaryGymId,$data['name'],$data['description'],$data['audience'],$data['start_at'],$data['end_at'],$data['timezone'],
                $data['discount_type'],$data['discount_value'],$data['currency'],$data['discount_code'],$data['image_file_id'],
                $this->json($data['channels']),$actorId,$this->demoFlag(),$this->datasetId,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->replaceGyms($id,$data['gym_ids']);
            $this->pdo->commit();
            return $this->find($primaryGymId,$id) ?? throw new RuntimeException('No se pudo leer la promoción creada.');
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function update(int $gymId, int $promotionId, array $data): array
    {
        $before = $this->find($gymId,$promotionId);
        if (!$before) ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');
        if (!in_array($before['estado'], ['borrador','pausada'], true)) {
            ApiResponder::error(409,'promotion_not_editable','Sólo se pueden editar promociones en borrador o pausadas.');
        }
        $this->assertGymsInScope($data['gym_ids']);
        $this->assertImage($data['gym_ids'],$data['image_file_id']);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE promociones SET nombre=?,descripcion=?,audiencia=?,inicio_en=?,fin_en=?,zona_horaria=?,tipo_descuento=?,valor_descuento=?,moneda=?,codigo_descuento=?,imagen_archivo_id=?,canales_json=?,version=version+1
                 WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([
                $data['name'],$data['description'],$data['audience'],$data['start_at'],$data['end_at'],$data['timezone'],$data['discount_type'],
                $data['discount_value'],$data['currency'],$data['discount_code'],$data['image_file_id'],$this->json($data['channels']),
                $promotionId,$this->demoFlag(),$this->datasetId,
            ]);
            $this->replaceGyms($promotionId,$data['gym_ids']);
            $this->pdo->commit();
            return ['before'=>$before,'after'=>$this->find($gymId,$promotionId)];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function delete(int $gymId, int $promotionId): array
    {
        $before = $this->find($gymId,$promotionId);
        if (!$before) ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');
        if (!in_array($before['estado'], ['borrador','pausada','finalizada'], true)) {
            ApiResponder::error(409,'promotion_not_deletable','Pausá o finalizá la promoción antes de eliminarla.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->omitPending($promotionId,'promotion_deleted','La promoción fue eliminada.');
            $this->pdo->prepare('UPDATE promociones SET eliminado_en=NOW(),estado="finalizada" WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)')->execute([$promotionId,$this->demoFlag(),$this->datasetId]);
            $this->pdo->commit();
            return $before;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function schedule(int $gymId, int $promotionId): array
    {
        $promotion = $this->find($gymId,$promotionId);
        if (!$promotion) ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');
        if (!in_array($promotion['estado'], ['borrador','pausada'], true)) {
            ApiResponder::error(409,'promotion_transition_invalid','Sólo una promoción en borrador o pausada se puede programar.');
        }
        $zone = new DateTimeZone((string) $promotion['zona_horaria']);
        $now = new DateTimeImmutable('now',$zone);
        $end = new DateTimeImmutable((string) $promotion['fin_en'],$zone);
        if ($end <= $now) ApiResponder::error(409,'promotion_expired','La promoción ya finalizó. Actualizá sus fechas antes de programarla.');
        $start = new DateTimeImmutable((string) $promotion['inicio_en'],$zone);
        $state = $start <= $now ? 'activa' : 'programada';
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE promociones SET estado=? WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)')
                ->execute([$state,$promotionId,$this->demoFlag(),$this->datasetId]);
            $resumed=0;
            if($promotion['estado']==='pausada'){
                $resume=$this->pdo->prepare('UPDATE notificacion_envios SET estado="pendiente",error_codigo=NULL,error_seguro=NULL,proximo_intento_en=?,programado_en=? WHERE promocion_id=? AND estado="omitido" AND error_codigo="promotion_paused" AND intentos<max_intentos AND idempotency_key LIKE ?');
                $programmed=$this->utc((string)$promotion['inicio_en'],(string)$promotion['zona_horaria']);
                $versionPrefix=sprintf('promotion:%d:v%d:%%',$promotionId,(int)$promotion['version']);
                $resume->execute([$programmed,$programmed,$promotionId,$versionPrefix]);$resumed=$resume->rowCount();
            }
            $queued = $resumed + $this->enqueue($promotionId,(int)$promotion['version'],$promotion);
            $this->pdo->commit();
            return ['promotion'=>$this->find($gymId,$promotionId),'queued'=>$queued];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function pause(int $gymId, int $promotionId): array
    {
        return $this->transition($gymId,$promotionId,['programada','activa'],'pausada','promotion_paused','La promoción fue pausada.');
    }

    public function finish(int $gymId, int $promotionId): array
    {
        return $this->transition($gymId,$promotionId,['programada','activa','pausada'],'finalizada','promotion_finished','La promoción finalizó.');
    }

    public function results(int $gymId, int $promotionId): array
    {
        $promotion = $this->find($gymId,$promotionId);
        if (!$promotion) ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');
        $stmt = $this->pdo->prepare(
            'SELECT canal,estado,COUNT(*) total FROM notificacion_envios
             WHERE promocion_id=? AND is_demo=? AND (demo_dataset_id <=> ?) GROUP BY canal,estado ORDER BY canal,estado'
        );
        $stmt->execute([$promotionId,$this->demoFlag(),$this->datasetId]);
        $breakdown = $stmt->fetchAll();
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) total,COUNT(DISTINCT usuario_id) recipients,
                    SUM(estado="enviado") sent,SUM(estado IN ("pendiente","procesando")) pending,SUM(estado="fallido") failed,SUM(estado="omitido") skipped,
                    MAX(enviado_en) last_sent_at
             FROM notificacion_envios WHERE promocion_id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
        );
        $stmt->execute([$promotionId,$this->demoFlag(),$this->datasetId]);
        $totals = $stmt->fetch() ?: [];
        foreach (['total','recipients','sent','pending','failed','skipped'] as $key) $totals[$key] = (int) ($totals[$key] ?? 0);
        return ['promotion'=>$promotion,'totals'=>$totals,'by_channel_status'=>array_map(static function(array $row): array {
            $row['total']=(int)$row['total'];return $row;
        },$breakdown)];
    }

    public function gymTimezone(int $gymId): string
    {
        $stmt=$this->pdo->prepare('SELECT zona_horaria FROM gimnasios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)');
        $stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);
        return (string) ($stmt->fetchColumn() ?: 'America/Montevideo');
    }

    private function transition(int $gymId,int $promotionId,array $allowed,string $state,string $code,string $reason): array
    {
        $promotion=$this->find($gymId,$promotionId);
        if(!$promotion)ApiResponder::error(404,'promotion_not_found','La promoción no pertenece al gimnasio activo.');
        if(!in_array($promotion['estado'],$allowed,true))ApiResponder::error(409,'promotion_transition_invalid','La transición solicitada no corresponde al estado actual.');
        $this->pdo->beginTransaction();
        try{$this->omitPending($promotionId,$code,$reason);$this->pdo->prepare('UPDATE promociones SET estado=? WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)')->execute([$state,$promotionId,$this->demoFlag(),$this->datasetId]);$this->pdo->commit();return $this->find($gymId,$promotionId)??[];}
        catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function enqueue(int $promotionId,int $version,array $promotion): int
    {
        $recipients=$this->recipients($promotionId,(string)$promotion['audiencia']);$channels=$promotion['canales'];$count=0;
        $insert=$this->pdo->prepare(
            'INSERT IGNORE INTO notificacion_envios
             (promocion_id,gimnasio_id,usuario_id,canal,categoria,estado,idempotency_key,intentos,max_intentos,proximo_intento_en,programado_en,payload_json,is_demo,demo_dataset_id)
             VALUES (?,?,?, ?,"marketing","pendiente",?,0,3,?, ?,?, ?,?)'
        );
        $programmed=$this->utc((string)$promotion['inicio_en'],(string)$promotion['zona_horaria']);
        foreach($recipients as $recipient){foreach($channels as $channel){
            $key=sprintf('promotion:%d:v%d:user:%d:channel:%s',$promotionId,$version,(int)$recipient['usuario_id'],$channel);
            $payload=['title'=>$promotion['nombre'],'body'=>$promotion['descripcion'],'link_url'=>'/dashboard','timezone'=>$promotion['zona_horaria']];
            $deliveryGym=(int)$recipient['gym_count']>1?null:(int)$recipient['gimnasio_id'];
            $insert->execute([$promotionId,$deliveryGym,(int)$recipient['usuario_id'],$channel,$key,$programmed,$programmed,$this->json($payload),$this->demoFlag(),$this->datasetId]);
            $count += $insert->rowCount();
        }}
        return $count;
    }

    private function recipients(int $promotionId,string $audience): array
    {
        $where=['u.activo=1','u.is_demo=?','(u.demo_dataset_id <=> ?)','ugr.activo=1','ugr.is_demo=?','(ugr.demo_dataset_id <=> ?)','ro.nombre="socio"'];$params=[$this->demoFlag(),$this->datasetId,$this->demoFlag(),$this->datasetId];
        if($audience==='socios_activos')$where[]='EXISTS (SELECT 1 FROM membresias m WHERE m.usuario_id=u.id AND m.gimnasio_id=ugr.gimnasio_id AND m.is_demo=u.is_demo AND (m.demo_dataset_id <=> u.demo_dataset_id) AND m.estado="activa" AND m.fecha_inicio<=CURRENT_DATE AND m.fecha_vencimiento>=CURRENT_DATE)';
        elseif($audience==='socios_con_deuda')$where[]='EXISTS (SELECT 1 FROM membresias m JOIN pagos pa ON pa.membresia_id=m.id WHERE m.usuario_id=u.id AND m.gimnasio_id=ugr.gimnasio_id AND m.is_demo=u.is_demo AND (m.demo_dataset_id <=> u.demo_dataset_id) AND pa.is_demo=u.is_demo AND (pa.demo_dataset_id <=> u.demo_dataset_id) AND pa.estado IN ("pendiente","vencido"))';
        elseif($audience==='socios_inactivos')$where[]='NOT EXISTS (SELECT 1 FROM membresias m WHERE m.usuario_id=u.id AND m.gimnasio_id=ugr.gimnasio_id AND m.is_demo=u.is_demo AND (m.demo_dataset_id <=> u.demo_dataset_id) AND m.estado="activa" AND m.fecha_inicio<=CURRENT_DATE AND m.fecha_vencimiento>=CURRENT_DATE)';
        $where[]='pg.is_demo=?';$where[]='(pg.demo_dataset_id <=> ?)';array_push($params,$this->demoFlag(),$this->datasetId);
        $stmt=$this->pdo->prepare('SELECT u.id usuario_id,MIN(ugr.gimnasio_id) gimnasio_id,COUNT(DISTINCT ugr.gimnasio_id) gym_count FROM usuarios u JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id JOIN roles ro ON ro.id=ugr.rol_id JOIN promocion_gimnasios pg ON pg.gimnasio_id=ugr.gimnasio_id AND pg.promocion_id=? WHERE '.implode(' AND ',$where).' GROUP BY u.id ORDER BY u.id');
        $stmt->execute([$promotionId,...$params]);return $stmt->fetchAll();
    }

    private function omitPending(int $promotionId,string $code,string $reason): void
    {
        $stmt=$this->pdo->prepare('UPDATE notificacion_envios SET estado="omitido",error_codigo=?,error_seguro=?,proximo_intento_en=NULL WHERE promocion_id=? AND estado IN ("pendiente","fallido")');
        $stmt->execute([$code,$reason,$promotionId]);
    }

    private function replaceGyms(int $promotionId,array $gymIds): void
    {
        $this->pdo->prepare('DELETE FROM promocion_gimnasios WHERE promocion_id=?')->execute([$promotionId]);
        $stmt=$this->pdo->prepare('INSERT INTO promocion_gimnasios(promocion_id,gimnasio_id,is_demo,demo_dataset_id) VALUES (?,?,?,?)');
        foreach($gymIds as $gymId)$stmt->execute([$promotionId,$gymId,$this->demoFlag(),$this->datasetId]);
    }

    private function assertGymsInScope(array $gymIds): void
    {
        if($gymIds===[])ApiResponder::error(422,'validation_error','Seleccioná al menos un gimnasio.',['gimnasio_ids'=>'La promoción requiere un gimnasio.']);
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM gimnasios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND archivado_en IS NULL');
        foreach($gymIds as $gymId){$stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);if((int)$stmt->fetchColumn()!==1)ApiResponder::error(403,'gym_scope_mismatch','Uno de los gimnasios no pertenece al conjunto de datos de tu sesión.');}
    }

    private function assertImage(array $gymIds,?int $fileId): void
    {
        if($fileId===null)return;$placeholders=implode(',',array_fill(0,count($gymIds),'?'));$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM archivos WHERE id=? AND gimnasio_id IN ('.$placeholders.') AND categoria="promocion_imagen" AND estado="activo" AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$fileId,...$gymIds,$this->demoFlag(),$this->datasetId]);if((int)$stmt->fetchColumn()!==1)ApiResponder::error(422,'promotion_image_invalid','La imagen no pertenece a los gimnasios seleccionados.',['imagen_archivo_id'=>'Seleccioná una imagen promocional válida.']);
    }

    private function normalize(array $row,bool $full): array
    {
        foreach(['id','gimnasio_id','version','creado_por','imagen_archivo_id','destinatarios_total'] as $key)if(isset($row[$key]))$row[$key]=(int)$row[$key];
        $row['valor_descuento']=$row['valor_descuento']===null?null:(float)$row['valor_descuento'];$row['is_demo']=(bool)$row['is_demo'];$row['canales']=json_decode((string)$row['canales_json'],true)?:[];unset($row['canales_json']);
        $row['imagen_url']=$row['imagen_archivo_id']?'/api/admin/files/'.$row['imagen_archivo_id']:null;
        if($full){$stmt=$this->pdo->prepare('SELECT g.id,g.nombre,g.slug FROM promocion_gimnasios pg JOIN gimnasios g ON g.id=pg.gimnasio_id WHERE pg.promocion_id=? AND pg.is_demo=? AND (pg.demo_dataset_id <=> ?) AND g.is_demo=? AND (g.demo_dataset_id <=> ?) ORDER BY g.nombre');$stmt->execute([$row['id'],$this->demoFlag(),$this->datasetId,$this->demoFlag(),$this->datasetId]);$row['gimnasios']=array_map(static function(array $gym):array{$gym['id']=(int)$gym['id'];return$gym;},$stmt->fetchAll());}
        return $row;
    }

    private function json(array $value): string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function utc(string $value,string $timezone): string{return(new DateTimeImmutable($value,new DateTimeZone($timezone)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
    private function queryInteger(mixed $value,int $min,int $max,int $fallback): int{if(!(is_int($value)||(is_string($value)&&ctype_digit($value))))return$fallback;return max($min,min($max,(int)$value));}
    private function demoFlag(): int{return $this->datasetId===null?0:1;}
}
