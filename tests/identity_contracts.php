<?php
/**
 * Contrato automatizado identity_contracts. Inspecciona archivos o comportamiento y finaliza con error si se rompe una garantía del proyecto.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];
$requiredRoutes=['GET /api/auth/session','GET /api/me','GET /api/me/sessions','POST /api/auth/logout-all','POST /api/auth/email/resend','POST /api/auth/email/verify','POST /api/auth/password/forgot','POST /api/auth/password/reset','POST /api/auth/password/change','POST /api/me/gym-context','DELETE /api/me/gym-context'];
$routes=file_get_contents($root.'/backend/routes/api.php');foreach($requiredRoutes as $route){if(!str_contains($routes,"'{$route}'"))$failures[]="Falta {$route}";}
$frontend=file_get_contents($root.'/frontend/src/stores/auth.js').file_get_contents($root.'/frontend/src/services/api.js');
foreach(['localStorage','Authorization =','Bearer '] as $forbidden){if(str_contains($frontend,$forbidden))$failures[]="El frontend conserva {$forbidden}";}
$login=file_get_contents($root.'/backend/controllers/AuthController.php');if(str_contains($login,"'token' => session_id()")||str_contains($login,"'token'=>session_id()"))$failures[]='El login expone session_id.';
foreach (['email_verification_expired','email_verification_used','email_already_verified','deliverVerificationEmail','/verificar-email#token='] as $verificationContract) {
    if (!str_contains($login, $verificationContract)) $failures[]="Falta el contrato de verificación {$verificationContract}.";
}
$tokens=file_get_contents($root.'/backend/services/TokenService.php');
foreach (['FOR UPDATE','hash(\'sha256\'','already_verified','confirmEmailDelivery','discardEmailVerification'] as $tokenContract) {
    if (!str_contains($tokens, $tokenContract)) $failures[]="Falta la protección de token {$tokenContract}.";
}
$mail=file_get_contents($root.'/backend/services/MailService.php');
$template=file_get_contents($root.'/backend/services/EmailTemplate.php');
foreach (['PHPMailer::class','isHTML(true)','AltBody','configureSmtp','ENCRYPTION_STARTTLS','MAIL_TRANSPORT=log no está permitido en producción'] as $mailContract) {
    if (!str_contains($mail, $mailContract)) $failures[]="Falta el contrato de correo {$mailContract}.";
}
foreach (['Verificar mi correo','Logo de GymTrack','FRONTEND_URL','@media only screen'] as $templateContract) {
    if (!str_contains($template, $templateContract)) $failures[]="Falta el contenido de plantilla {$templateContract}.";
}
$composer=json_decode((string)file_get_contents($root.'/backend/composer.lock'),true);
$composerPackages=array_column($composer['packages']??[],'version','name');
if (!isset($composerPackages['phpmailer/phpmailer'])) $failures[]='Composer no fija una versión de PHPMailer.';
$productionImage=file_get_contents($root.'/backend/Dockerfile.prod');
if (!str_contains($productionImage,'composer install') || !str_contains($productionImage,'/vendor/autoload.php')) {
    $failures[]='La imagen de producción no instala o valida las dependencias de Composer.';
}
$middleware=file_get_contents($root.'/backend/middleware/AuthMiddleware.php');if(preg_match('/verificarRol\s*\(\s*[0-9]/',$middleware))$failures[]='El middleware usa IDs rígidos.';
$migration=file_get_contents($root.'/database/migrations/003_phase3_identity_security.sql');foreach(['user_sessions','email_verification_tokens','password_reset_tokens','rate_limit_attempts','user_consents','security_events','usuario_gimnasio_permisos'] as $table){if(!str_contains($migration,$table))$failures[]="Falta {$table} en la migración";}
foreach(['SET NAMES utf8mb4','debe_cambiar_password=1'] as $required){if(!str_contains($migration,$required))$failures[]="Falta el contrato de migración {$required}";}
$tenantSources=file_get_contents($root.'/backend/models/Clase.php').file_get_contents($root.'/backend/models/Reserva.php').file_get_contents($root.'/backend/models/Membresia.php');
if(!str_contains($tenantSources,'gimnasio_id'))$failures[]='Los modelos operativos no filtran por gimnasio.';
if(!is_file($root.'/database/migrations/003_phase3_identity_security.down.sql'))$failures[]='Falta rollback de la migración 003.';
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "Contratos de identidad correctos.\n";
