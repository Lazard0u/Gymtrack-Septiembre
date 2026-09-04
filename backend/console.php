<?php
/**
 * Consola de mantenimiento de GymTrack. Despacha comandos explícitos para datos demo, correo local, limpieza y roles.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// La consola comparte las dependencias instaladas por Composer con la API HTTP.
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function(string $class): void {
    foreach(['config','models','services','seeders'] as $directory){$path=__DIR__."/{$directory}/{$class}.php";if(is_file($path)){require_once $path;return;}}
});

$command=$argv[1]??'';$option=$argv[2]??'';

try {
    if($command==='seed:demo'&&in_array($option,['','--reset','--remove','--status'],true)){
        $seeder=new DemoDatasetSeeder();match($option){'--reset'=>$seeder->seed(true),'--remove'=>$seeder->remove(),'--status'=>$seeder->status(),default=>$seeder->seed(false)};exit(0);
    }
    if($command==='auth:cleanup'){$limits=(new RateLimiter())->cleanup();$tokens=(new TokenService())->cleanup();echo "Limpieza completa: {$limits} límites y {$tokens} tokens eliminados.\n";exit(0);}
    if($command==='notifications:dispatch'){
        $limit=max(1,min(1000,(int)($option?:100)));$promotionId=isset($argv[3])?max(1,(int)$argv[3]):null;$allDatasets=strtolower((string)(getenv('APP_ENV')?:'production'))!=='production';$result=(new NotificationDispatcher(null,$allDatasets))->dispatch($limit,$promotionId);echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit(0);
    }
    if($command==='auth:mail:latest'){
        if((getenv('APP_ENV')?:'production')==='production')throw new RuntimeException('El buzón local no puede consultarse en producción.');
        $email=strtolower(trim($option));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Indicá un correo válido.');$file=(string)(getenv('MAIL_LOG_PATH')?:'/tmp/gymtrack-mail/mail.log');
        if(!is_file($file))throw new RuntimeException('Todavía no hay correos locales.');$lines=file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
        for($i=count($lines)-1;$i>=0;$i--){$mail=json_decode($lines[$i],true);if(($mail['para']??'')===$email){echo ($mail['asunto']??'Correo')."\n".($mail['mensaje']??'')."\n";exit(0);}}
        throw new RuntimeException('No se encontró correo local para esa cuenta.');
    }
    if($command==='roles:report'||$command==='roles:ambiguous'){
        $sql='SELECT email,rol_anterior,rol_nuevo,estado,detalle FROM role_migration_report';if($command==='roles:ambiguous')$sql.=' WHERE estado="ambiguo"';$sql.=' ORDER BY email';
        foreach(Database::conectar()->query($sql)->fetchAll() as $row)echo implode(' | ',[$row['email'],$row['rol_anterior'],$row['rol_nuevo']??'-',$row['estado'],$row['detalle']??''])."\n";exit(0);
    }
    if($command==='roles:resolve'){
        $email=strtolower(trim($option));$role=$argv[3]??'';$gymSlug=$argv[4]??null;if(!in_array($role,['socio','empleado','dueño','admin_general'],true))throw new RuntimeException('Rol inválido.');
        $pdo=Database::conectar();$stmt=$pdo->prepare('SELECT u.id FROM usuarios u WHERE u.email_normalizado=?');$stmt->execute([$email]);$userId=$stmt->fetchColumn();if(!$userId)throw new RuntimeException('Cuenta no encontrada.');
        $stmt=$pdo->prepare('SELECT id FROM roles WHERE nombre=?');$stmt->execute([$role]);$roleId=$stmt->fetchColumn();
        if($gymSlug===null){$pdo->prepare('UPDATE usuarios SET rol_id=? WHERE id=?')->execute([(int)$roleId,(int)$userId]);}
        else{$stmt=$pdo->prepare('SELECT id FROM gimnasios WHERE slug=?');$stmt->execute([$gymSlug]);$gymId=$stmt->fetchColumn();if(!$gymId)throw new RuntimeException('Gimnasio no encontrado.');$pdo->prepare('INSERT INTO usuario_gimnasio_roles(usuario_id,gimnasio_id,rol_id,activo) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE activo=1')->execute([(int)$userId,(int)$gymId,(int)$roleId]);}
        $pdo->prepare('UPDATE role_migration_report SET rol_nuevo=?,estado="resuelto",detalle="Resuelto por CLI",resuelto_en=NOW() WHERE usuario_id=?')->execute([$role,(int)$userId]);echo "Rol resuelto sin usar IDs rígidos.\n";exit(0);
    }
    fwrite(STDERR,"Comandos:\n  seed:demo [--reset|--remove|--status]\n  auth:cleanup\n  auth:mail:latest <email>\n  notifications:dispatch [limite] [promocion_id]\n  roles:report\n  roles:ambiguous\n  roles:resolve <email> <socio|empleado|dueño|admin_general> [gym_slug]\n");exit(2);
} catch(Throwable $error){fwrite(STDERR,"No se pudo completar {$command}: {$error->getMessage()}\n");exit(1);}
