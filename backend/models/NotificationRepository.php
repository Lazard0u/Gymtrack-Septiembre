<?php
/**
 * Acceso a datos NotificationRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class NotificationRepository
{
    private PDO $pdo;
    private ?int $datasetId;
    private bool $allDatasets;

    public function __construct(?int $datasetId, bool $allDatasets = false)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
        $this->allDatasets = $allDatasets;
    }

    public function inbox(int $userId, ?int $gymId, array $query): array
    {
        $this->assertUserScope($userId);
        $page=$this->queryInteger($query['page']??1,1,100000,1);$perPage=$this->queryInteger($query['per_page']??20,10,50,20);
        $where=['usuario_id=?','is_demo=?','(demo_dataset_id <=> ?)'];
        $params=[$userId,$this->demoFlag(),$this->datasetId];
        if($gymId!==null){$where[]='(gimnasio_id IS NULL OR gimnasio_id=?)';$params[]=$gymId;}
        if(filter_var($query['unread']??false,FILTER_VALIDATE_BOOL))$where[]='leida=0';
        $base=' FROM notificaciones WHERE '.implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base);$count->execute($params);$total=(int)$count->fetchColumn();
        $unread=$this->pdo->prepare('SELECT COUNT(*)'.$base.' AND leida=0');$unread->execute($params);$unreadTotal=(int)$unread->fetchColumn();
        $offset=($page-1)*$perPage;$stmt=$this->pdo->prepare('SELECT id,gimnasio_id,COALESCE(titulo,"Notificación") titulo,COALESCE(cuerpo,mensaje) cuerpo,categoria,link_url,leida,leida_en,creado_en'.$base.' ORDER BY creado_en DESC,id DESC LIMIT '.$perPage.' OFFSET '.$offset);$stmt->execute($params);
        $items=array_map(static function(array $row):array{$row['id']=(int)$row['id'];$row['gimnasio_id']=$row['gimnasio_id']===null?null:(int)$row['gimnasio_id'];$row['leida']=(bool)$row['leida'];return$row;},$stmt->fetchAll());
        return ['items'=>$items,'unread_count'=>$unreadTotal,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage))]];
    }

    public function markRead(int $userId, ?int $gymId, int $notificationId): ?array
    {
        $this->assertUserScope($userId);
        $where=['id=?','usuario_id=?','is_demo=?','(demo_dataset_id <=> ?)'];
        $params=[$notificationId,$userId,$this->demoFlag(),$this->datasetId];
        if($gymId!==null){$where[]='(gimnasio_id IS NULL OR gimnasio_id=?)';$params[]=$gymId;}
        $this->pdo->prepare('UPDATE notificaciones SET leida=1,leida_en=COALESCE(leida_en,NOW()) WHERE '.implode(' AND ',$where))->execute($params);
        $stmt=$this->pdo->prepare('SELECT id,gimnasio_id,COALESCE(titulo,"Notificación") titulo,COALESCE(cuerpo,mensaje) cuerpo,categoria,link_url,leida,leida_en,creado_en FROM notificaciones WHERE '.implode(' AND ',$where).' LIMIT 1');$stmt->execute($params);$row=$stmt->fetch();if(!$row)return null;$row['id']=(int)$row['id'];$row['gimnasio_id']=$row['gimnasio_id']===null?null:(int)$row['gimnasio_id'];$row['leida']=(bool)$row['leida'];return$row;
    }

    public function markAllRead(int $userId, ?int $gymId): int
    {
        $this->assertUserScope($userId);
        $sql='UPDATE notificaciones SET leida=1,leida_en=COALESCE(leida_en,NOW()) WHERE usuario_id=? AND leida=0 AND is_demo=? AND (demo_dataset_id <=> ?)';
        $params=[$userId,$this->demoFlag(),$this->datasetId];
        if($gymId!==null){$sql.=' AND (gimnasio_id IS NULL OR gimnasio_id=?)';$params[]=$gymId;}$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return$stmt->rowCount();
    }

    public function preferences(int $userId): array
    {
        $scope=$this->assertUserScope($userId);
        $stmt=$this->pdo->prepare('SELECT internal_transactional,internal_marketing,email_transactional,email_marketing,whatsapp_marketing,marketing_unsubscribed_at FROM notificacion_preferencias WHERE usuario_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([$userId,$scope['is_demo'],$scope['dataset_id']]);
        $row=$stmt->fetch()?:['internal_transactional'=>1,'internal_marketing'=>0,'email_transactional'=>1,'email_marketing'=>0,'whatsapp_marketing'=>0,'marketing_unsubscribed_at'=>null];
        foreach(['internal_transactional','internal_marketing','email_transactional','email_marketing','whatsapp_marketing'] as $key)$row[$key]=(bool)$row[$key];
        $row['marketing_consent']=$this->marketingConsent($userId);return$row;
    }

    public function updatePreferences(int $userId, array $data): array
    {
        $scope=$this->assertUserScope($userId);$consent=(bool)$data['marketing_consent'];
        $this->pdo->beginTransaction();
        try{
            if($consent){
                $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM user_consents WHERE usuario_id=? AND tipo="marketing" AND revocado_en IS NULL');$stmt->execute([$userId]);
                if((int)$stmt->fetchColumn()===0)$this->pdo->prepare('INSERT INTO user_consents(usuario_id,tipo,version_documento,aceptado_en,ip_hash) VALUES (?,"marketing","2026-08-12",NOW(),?)')->execute([$userId,Security::ipHash()]);
                $this->pdo->prepare('UPDATE usuarios SET marketing_consentido_en=COALESCE(marketing_consentido_en,NOW()) WHERE id=?')->execute([$userId]);
            }else{
                $this->pdo->prepare('UPDATE user_consents SET revocado_en=COALESCE(revocado_en,NOW()) WHERE usuario_id=? AND tipo="marketing" AND revocado_en IS NULL')->execute([$userId]);
                $this->pdo->prepare('UPDATE usuarios SET marketing_consentido_en=NULL WHERE id=?')->execute([$userId]);
                $data['internal_marketing']=false;$data['email_marketing']=false;$data['whatsapp_marketing']=false;
            }
            $stmt=$this->pdo->prepare('INSERT INTO notificacion_preferencias(usuario_id,internal_transactional,internal_marketing,email_transactional,email_marketing,whatsapp_marketing,marketing_unsubscribed_at,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?, ?,?) ON DUPLICATE KEY UPDATE internal_transactional=VALUES(internal_transactional),internal_marketing=VALUES(internal_marketing),email_transactional=VALUES(email_transactional),email_marketing=VALUES(email_marketing),whatsapp_marketing=VALUES(whatsapp_marketing),marketing_unsubscribed_at=VALUES(marketing_unsubscribed_at),is_demo=VALUES(is_demo),demo_dataset_id=VALUES(demo_dataset_id)');
            $stmt->execute([$userId,$data['internal_transactional']?1:0,$data['internal_marketing']?1:0,$data['email_transactional']?1:0,$data['email_marketing']?1:0,$data['whatsapp_marketing']?1:0,$consent?null:gmdate('Y-m-d H:i:s'),$scope['is_demo'],$scope['dataset_id']]);
            $this->pdo->commit();return$this->preferences($userId);
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }

    public function unsubscribe(string $token): void
    {
        $hash=hash('sha256',$token);$this->pdo->beginTransaction();
        try{$stmt=$this->pdo->prepare('SELECT id,usuario_id FROM marketing_unsubscribe_tokens WHERE token_hash=? AND usado_en IS NULL AND expira_en>NOW() LIMIT 1 FOR UPDATE');$stmt->execute([$hash]);$row=$stmt->fetch();if(!$row)ApiResponder::error(400,'unsubscribe_token_invalid','El enlace para dejar de recibir publicidad no es válido o venció.');$userId=(int)$row['usuario_id'];$scope=$this->userScope($userId);
            $this->pdo->prepare('UPDATE marketing_unsubscribe_tokens SET usado_en=NOW() WHERE id=?')->execute([$row['id']]);
            $this->pdo->prepare('UPDATE user_consents SET revocado_en=COALESCE(revocado_en,NOW()) WHERE usuario_id=? AND tipo="marketing" AND revocado_en IS NULL')->execute([$userId]);
            $this->pdo->prepare('UPDATE usuarios SET marketing_consentido_en=NULL WHERE id=?')->execute([$userId]);
            $this->pdo->prepare('INSERT INTO notificacion_preferencias(usuario_id,internal_marketing,email_marketing,whatsapp_marketing,marketing_unsubscribed_at,is_demo,demo_dataset_id) VALUES (?,0,0,0,NOW(),?,?) ON DUPLICATE KEY UPDATE internal_marketing=0,email_marketing=0,whatsapp_marketing=0,marketing_unsubscribed_at=NOW(),is_demo=VALUES(is_demo),demo_dataset_id=VALUES(demo_dataset_id)')->execute([$userId,$scope['is_demo'],$scope['dataset_id']]);
            $this->pdo->prepare('UPDATE notificacion_envios SET estado="omitido",error_codigo="marketing_unsubscribed",error_seguro="El usuario revocó el consentimiento promocional.",proximo_intento_en=NULL WHERE usuario_id=? AND categoria="marketing" AND estado IN ("pendiente","fallido")')->execute([$userId]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }

    public function claimNext(?int $promotionId=null): ?array
    {
        $this->pdo->beginTransaction();
        try{$where=['e.estado IN ("pendiente","fallido")','e.intentos<e.max_intentos','e.programado_en<=UTC_TIMESTAMP()','(e.proximo_intento_en IS NULL OR e.proximo_intento_en<=UTC_TIMESTAMP())','e.is_demo=u.is_demo','(e.demo_dataset_id <=> u.demo_dataset_id)'];$params=[];
            if($promotionId!==null){$where[]='e.promocion_id=?';$params[]=$promotionId;}if(!$this->allDatasets){$where[]='e.is_demo=?';$where[]='(e.demo_dataset_id <=> ?)';array_push($params,$this->demoFlag(),$this->datasetId);}
            $stmt=$this->pdo->prepare('SELECT e.id FROM notificacion_envios e JOIN usuarios u ON u.id=e.usuario_id WHERE '.implode(' AND ',$where).' ORDER BY e.programado_en,e.id LIMIT 1 FOR UPDATE SKIP LOCKED');$stmt->execute($params);$id=$stmt->fetchColumn();if($id===false){$this->pdo->commit();return null;}
            $this->pdo->prepare('UPDATE notificacion_envios SET estado="procesando",intentos=intentos+1,procesando_en=UTC_TIMESTAMP(),error_codigo=NULL,error_seguro=NULL WHERE id=?')->execute([$id]);
            $stmt=$this->pdo->prepare('SELECT e.*,u.email recipient_email,CONCAT_WS(" ",u.nombre,u.apellido) recipient_name FROM notificacion_envios e JOIN usuarios u ON u.id=e.usuario_id WHERE e.id=?');$stmt->execute([$id]);$row=$stmt->fetch();$this->pdo->commit();return$row?:null;
        }catch(Throwable$error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }

    public function refreshPromotionStates(): array
    {
        $where=['eliminado_en IS NULL','estado IN ("programada","activa")'];$params=[];if(!$this->allDatasets){$where[]='is_demo=?';$where[]='(demo_dataset_id <=> ?)';array_push($params,$this->demoFlag(),$this->datasetId);}
        $stmt=$this->pdo->prepare('SELECT id,estado,inicio_en,fin_en,zona_horaria FROM promociones WHERE '.implode(' AND ',$where));$stmt->execute($params);$activated=0;$finished=0;
        foreach($stmt->fetchAll() as $promotion){try{$zone=new DateTimeZone((string)$promotion['zona_horaria']);}catch(Throwable){$zone=new DateTimeZone('America/Montevideo');}$now=new DateTimeImmutable('now',$zone);$end=new DateTimeImmutable((string)$promotion['fin_en'],$zone);
            if($end<=$now){$this->pdo->prepare('UPDATE promociones SET estado="finalizada" WHERE id=? AND estado IN ("programada","activa")')->execute([$promotion['id']]);$omit=$this->pdo->prepare('UPDATE notificacion_envios SET estado="omitido",error_codigo="promotion_expired",error_seguro="La promoción finalizó antes del envío.",proximo_intento_en=NULL WHERE promocion_id=? AND estado IN ("pendiente","fallido")');$omit->execute([$promotion['id']]);$finished++;continue;}
            $start=new DateTimeImmutable((string)$promotion['inicio_en'],$zone);if($promotion['estado']==='programada'&&$start<=$now){$this->pdo->prepare('UPDATE promociones SET estado="activa" WHERE id=? AND estado="programada"')->execute([$promotion['id']]);$activated++;}
        }
        return['activated'=>$activated,'finished'=>$finished];
    }

    public function consentAllows(array $delivery): bool
    {
        $scope=$this->userScope((int)$delivery['usuario_id']);
        if(!$scope['active']||$scope['is_demo']!==(int)$delivery['is_demo']||$scope['dataset_id']!==($delivery['demo_dataset_id']===null?null:(int)$delivery['demo_dataset_id']))return false;
        if($delivery['categoria']==='marketing'&&$delivery['promocion_id']!==null){$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM promociones WHERE id=? AND estado="activa" AND eliminado_en IS NULL AND is_demo=? AND (demo_dataset_id <=> ?)');$stmt->execute([(int)$delivery['promocion_id'],(int)$delivery['is_demo'],$delivery['demo_dataset_id']]);if((int)$stmt->fetchColumn()!==1)return false;}
        $preferences=$this->preferences((int)$delivery['usuario_id']);$channel=(string)$delivery['canal'];$category=(string)$delivery['categoria'];
        if($category==='marketing'&&!$preferences['marketing_consent'])return false;
        $key=$channel.'_'.$category;return(bool)($preferences[$key]??false);
    }

    public function createInternal(array $delivery,array $payload): int
    {
        $stmt=$this->pdo->prepare('INSERT IGNORE INTO notificaciones(usuario_id,gimnasio_id,mensaje,titulo,cuerpo,categoria,link_url,leida,idempotency_key,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,0,?,?,?)');
        $stmt->execute([(int)$delivery['usuario_id'],$delivery['gimnasio_id']===null?null:(int)$delivery['gimnasio_id'],(string)$payload['body'],(string)$payload['title'],(string)$payload['body'],(string)$delivery['categoria'],$payload['link_url']??null,(string)$delivery['idempotency_key'],(int)$delivery['is_demo'],$delivery['demo_dataset_id']]);
        $id=(int)$this->pdo->lastInsertId();if($id===0){$find=$this->pdo->prepare('SELECT id FROM notificaciones WHERE idempotency_key=? AND usuario_id=? AND is_demo=? AND (demo_dataset_id <=> ?)');$find->execute([$delivery['idempotency_key'],$delivery['usuario_id'],$delivery['is_demo'],$delivery['demo_dataset_id']]);$id=(int)$find->fetchColumn();}
        if($id<1)throw new RuntimeException('internal_notification_idempotency_conflict');
        $this->pdo->prepare('UPDATE notificacion_envios SET notificacion_id=? WHERE id=?')->execute([$id,$delivery['id']]);return$id;
    }

    public function unsubscribeUrl(int $userId,string $idempotencyKey): string
    {
        $secret=(string)(getenv('APP_KEY')?:'');if(strlen($secret)<16)throw new RuntimeException('APP_KEY no está configurada para generar enlaces seguros.');$raw=hash_hmac('sha256','unsubscribe:'.$userId.':'.$idempotencyKey,$secret);$scope=$this->userScope($userId);$this->pdo->prepare('INSERT INTO marketing_unsubscribe_tokens(usuario_id,token_hash,expira_en,is_demo,demo_dataset_id) VALUES (?, ?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),?,?) ON DUPLICATE KEY UPDATE expira_en=GREATEST(expira_en,VALUES(expira_en))')->execute([$userId,hash('sha256',$raw),$scope['is_demo'],$scope['dataset_id']]);$frontend=rtrim((string)(getenv('FRONTEND_URL')?:'http://localhost:5173'),'/');return$frontend.'/notificaciones/unsubscribe?token='.rawurlencode($raw);
    }

    public function delivered(int $id,?string $providerId): void{$this->pdo->prepare('UPDATE notificacion_envios SET estado="enviado",provider_message_id=?,enviado_en=UTC_TIMESTAMP(),fallido_en=NULL,proximo_intento_en=NULL WHERE id=? AND estado="procesando"')->execute([$providerId,$id]);}
    public function skipped(int $id,string $code,string $message): void{$this->pdo->prepare('UPDATE notificacion_envios SET estado="omitido",error_codigo=?,error_seguro=?,proximo_intento_en=NULL WHERE id=?')->execute([$code,mb_substr($message,0,255),$id]);}
    public function failed(array $delivery,string $code,string $message): void{$attempt=(int)$delivery['intentos'];$max=(int)$delivery['max_intentos'];$next=$attempt<$max?gmdate('Y-m-d H:i:s',time()+min(3600,300*(2**max(0,$attempt-1)))):null;$this->pdo->prepare('UPDATE notificacion_envios SET estado="fallido",error_codigo=?,error_seguro=?,fallido_en=UTC_TIMESTAMP(),proximo_intento_en=? WHERE id=?')->execute([$code,mb_substr($message,0,255),$next,$delivery['id']]);}
    public function recoverStale(): int
    {
        $sql='UPDATE notificacion_envios SET estado="fallido",error_codigo="worker_interrupted",error_seguro="El proceso de envío se interrumpió.",fallido_en=UTC_TIMESTAMP(),proximo_intento_en=IF(intentos<max_intentos,UTC_TIMESTAMP(),NULL) WHERE estado="procesando" AND procesando_en<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)';
        $params=[];
        if(!$this->allDatasets){$sql.=' AND is_demo=? AND (demo_dataset_id <=> ?)';$params=[$this->demoFlag(),$this->datasetId];}
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return$stmt->rowCount();
    }

    private function marketingConsent(int $userId): bool{$stmt=$this->pdo->prepare('SELECT COUNT(*) FROM usuarios u WHERE u.id=? AND u.activo=1 AND u.marketing_consentido_en IS NOT NULL AND EXISTS(SELECT 1 FROM user_consents c WHERE c.usuario_id=u.id AND c.tipo="marketing" AND c.revocado_en IS NULL)');$stmt->execute([$userId]);return(int)$stmt->fetchColumn()===1;}
    private function userScope(int $userId): array{$stmt=$this->pdo->prepare('SELECT activo,is_demo,demo_dataset_id FROM usuarios WHERE id=? LIMIT 1');$stmt->execute([$userId]);$row=$stmt->fetch();if(!$row)throw new RuntimeException('Usuario no encontrado.');return['active'=>(bool)$row['activo'],'is_demo'=>(int)$row['is_demo'],'dataset_id'=>$row['demo_dataset_id']===null?null:(int)$row['demo_dataset_id']];}
    private function assertUserScope(int $userId): array{$scope=$this->userScope($userId);if(!$this->allDatasets&&($scope['is_demo']!==$this->demoFlag()||$scope['dataset_id']!==$this->datasetId))throw new RuntimeException('El usuario no pertenece al conjunto de datos activo.');return$scope;}
    private function queryInteger(mixed $value,int $min,int $max,int $fallback): int{if(!(is_int($value)||(is_string($value)&&ctype_digit($value))))return$fallback;return max($min,min($max,(int)$value));}
    private function demoFlag(): int{return$this->datasetId===null?0:1;}
}
