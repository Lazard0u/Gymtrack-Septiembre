<?php

declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];
$requiredRoutes=['GET /api/auth/session','GET /api/me','GET /api/me/sessions','POST /api/auth/logout-all','POST /api/auth/email/resend','POST /api/auth/email/verify','POST /api/auth/password/forgot','POST /api/auth/password/reset','POST /api/auth/password/change','POST /api/me/gym-context','DELETE /api/me/gym-context'];
$routes=file_get_contents($root.'/backend/routes/api.php');foreach($requiredRoutes as $route){if(!str_contains($routes,"'{$route}'"))$failures[]="Falta {$route}";}
$frontend=file_get_contents($root.'/frontend/src/stores/auth.js').file_get_contents($root.'/frontend/src/services/api.js');
foreach(['localStorage','Authorization =','Bearer '] as $forbidden){if(str_contains($frontend,$forbidden))$failures[]="El frontend conserva {$forbidden}";}
$login=file_get_contents($root.'/backend/controllers/AuthController.php');if(str_contains($login,"'token' => session_id()")||str_contains($login,"'token'=>session_id()"))$failures[]='El login expone session_id.';
$middleware=file_get_contents($root.'/backend/middleware/AuthMiddleware.php');if(preg_match('/verificarRol\s*\(\s*[0-9]/',$middleware))$failures[]='El middleware usa IDs rígidos.';
$migration=file_get_contents($root.'/database/migrations/003_phase3_identity_security.sql');foreach(['user_sessions','email_verification_tokens','password_reset_tokens','rate_limit_attempts','user_consents','security_events','usuario_gimnasio_permisos'] as $table){if(!str_contains($migration,$table))$failures[]="Falta {$table} en la migración";}
foreach(['SET NAMES utf8mb4','debe_cambiar_password=1'] as $required){if(!str_contains($migration,$required))$failures[]="Falta el contrato de migración {$required}";}
$tenantSources=file_get_contents($root.'/backend/models/Clase.php').file_get_contents($root.'/backend/models/Reserva.php').file_get_contents($root.'/backend/models/Membresia.php');
if(!str_contains($tenantSources,'gimnasio_id'))$failures[]='Los modelos operativos no filtran por gimnasio.';
if(!is_file($root.'/database/migrations/003_phase3_identity_security.down.sql'))$failures[]='Falta rollback de la migración 003.';
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "Contratos de identidad de Fase 3 correctos.\n";
