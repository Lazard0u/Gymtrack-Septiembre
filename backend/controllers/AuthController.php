<?php

declare(strict_types=1);

final class AuthController
{
    private Usuario $users;
    private PDO $pdo;
    public function __construct(){ $this->users=new Usuario();$this->pdo=Database::conectar(); }

    public function registrar(): void { $this->register(false); }
    public function registrarDueno(): void { $this->register(true); }

    public function login(): void
    {
        $data=$this->body();$email=Security::normalizeEmail((string)($data['email']??''));$password=(string)($data['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){$this->respond(400,['error'=>true,'mensaje'=>'Correo electrónico y contraseña son obligatorios.']);return;}
        $limit=new RateLimiter();$limit->ensureAllowed('login',$email);$user=$this->users->buscarPorEmail($email);
        if(!$user||!password_verify($password,(string)$user['password_hash'])){$limit->fail('login',$email);SecurityLogger::record('auth.login','failed',is_array($user)?(int)$user['id']:null);usleep(random_int(80000,160000));$this->respond(401,['error'=>true,'mensaje'=>'Credenciales incorrectas.']);return;}
        if(!(bool)$user['activo']){$limit->fail('login',$email);SecurityLogger::record('auth.login','disabled',(int)$user['id']);$this->respond(401,['error'=>true,'mensaje'=>'Credenciales incorrectas.']);return;}
        $limit->clear('login',$email);
        if(password_needs_rehash((string)$user['password_hash'],PASSWORD_BCRYPT,['cost'=>12])){$this->users->rehashPassword((int)$user['id'],password_hash($password,PASSWORD_BCRYPT,['cost'=>12]));}
        $assignments=(new AuthorizationService())->gymAssignments((int)$user['id']);$gymId=$user['rol_nombre']===AuthorizationService::ADMIN?null:($assignments[0]['gimnasio_id']??null);
        $session=(new SessionManager())->create($user,$gymId);SessionManager::configureCookie();session_start();
        $payload=(new UserContextService())->payload((int)$user['id']);session_write_close();SecurityLogger::record('auth.login','success',(int)$user['id']);
        $this->respond(200,['error'=>false,'mensaje'=>'Sesión iniciada correctamente.','usuario'=>$payload,'csrf_token'=>$session['csrf_token']]);
    }

    public function logout(): void
    {
        $manager=new SessionManager();
        if($manager->start(false)){$userId=(int)($_SESSION['usuario_id']??0);$manager->verifyCsrf();$manager->destroy(true,'logout');SecurityLogger::record('auth.logout','success',$userId);}
        $this->respond(200,['error'=>false,'mensaje'=>'Sesión cerrada correctamente.']);
    }

    public function resendEmail(): void
    {
        $data=$this->body();$email=Security::normalizeEmail((string)($data['email']??''));$message='Si la cuenta existe y aún necesita verificación, recibirás un enlace.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->respond(202,['error'=>false,'mensaje'=>$message]);return;}
        $limit=new RateLimiter();$limit->ensureAllowed('email_resend',$email);$limit->fail('email_resend',$email);$user=$this->users->buscarPorEmail($email);
        if($user&&$user['email_verificado_en']===null){$token=(new TokenService())->issueEmailVerification((int)$user['id']);$this->sendLink($email,'Verificá tu correo de GymTrack','/verificar-email?token='.rawurlencode($token));}
        $this->respond(202,['error'=>false,'mensaje'=>$message]);
    }

    public function verifyEmail(): void
    {
        $data=$this->body();$token=(string)($data['token']??'');$userId=(new TokenService())->consume('email_verification_tokens',$token);
        if($userId===null){$this->respond(400,['error'=>true,'mensaje'=>'El enlace de verificación no es válido o venció.']);return;}
        $this->users->verifyEmail($userId);SecurityLogger::record('auth.email_verified','success',$userId);$this->respond(200,['error'=>false,'mensaje'=>'Correo verificado correctamente. Ya podés continuar.']);
    }

    public function forgotPassword(): void
    {
        $data=$this->body();$email=Security::normalizeEmail((string)($data['email']??''));$message='Si la cuenta existe, recibirás instrucciones para restablecer la contraseña.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->respond(202,['error'=>false,'mensaje'=>$message]);return;}
        $limit=new RateLimiter();$limit->ensureAllowed('password_forgot',$email);$limit->fail('password_forgot',$email);$user=$this->users->buscarPorEmail($email);
        if($user&&(bool)$user['activo']){$token=(new TokenService())->issuePasswordReset((int)$user['id']);$this->sendLink($email,'Restablecé tu contraseña de GymTrack','/restablecer/'.rawurlencode($token));SecurityLogger::record('auth.password_forgot','accepted',(int)$user['id']);}
        $this->respond(202,['error'=>false,'mensaje'=>$message]);
    }

    public function resetPassword(): void
    {
        $data=$this->body();$token=(string)($data['token']??'');$password=(string)($data['password']??'');$confirmation=(string)($data['password_confirmation']??'');
        $subject=hash('sha256',$token);$limit=new RateLimiter();$limit->ensureAllowed('password_reset',$subject);
        if($password!==$confirmation){$limit->fail('password_reset',$subject);$this->respond(422,['error'=>true,'mensaje'=>'Las contraseñas no coinciden.']);return;}
        if($error=Security::passwordError($password)){$limit->fail('password_reset',$subject);$this->respond(422,['error'=>true,'mensaje'=>$error]);return;}
        $userId=(new TokenService())->consume('password_reset_tokens',$token);
        if($userId===null){$limit->fail('password_reset',$subject);$this->respond(400,['error'=>true,'mensaje'=>'El enlace no es válido o venció. Solicitá uno nuevo.']);return;}
        $this->users->updatePassword($userId,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]));(new SessionManager())->revokeAll($userId,null,'password_reset');$limit->clear('password_reset',$subject);SecurityLogger::record('auth.password_reset','success',$userId);
        $this->respond(200,['error'=>false,'mensaje'=>'Contraseña actualizada. Iniciá sesión nuevamente.']);
    }

    public function changePassword(): void
    {
        AuthMiddleware::verificarSesion();$data=$this->body();$userId=AuthMiddleware::obtenerUsuarioId();$user=$this->users->buscarPorEmail((string)$this->users->buscarPorId($userId)['email']);
        $current=(string)($data['current_password']??'');$password=(string)($data['password']??'');$confirmation=(string)($data['password_confirmation']??'');
        $limit=new RateLimiter();$subject=(string)$user['email_normalizado'];$limit->ensureAllowed('password_change',$subject);
        if(!password_verify($current,(string)$user['password_hash'])){$limit->fail('password_change',$subject);$this->respond(422,['error'=>true,'mensaje'=>'La contraseña actual no es correcta.']);return;}
        if($password!==$confirmation){$this->respond(422,['error'=>true,'mensaje'=>'Las contraseñas nuevas no coinciden.']);return;}
        if($error=Security::passwordError($password,(string)$user['email'])){$this->respond(422,['error'=>true,'mensaje'=>$error]);return;}
        $this->users->updatePassword($userId,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]));$limit->clear('password_change',$subject);
        if((bool)($data['logout_other_sessions']??true)){(new SessionManager())->revokeAll($userId,(string)($_SESSION['session_public_id']??''),'password_changed');}
        SecurityLogger::record('auth.password_changed','success',$userId);$this->respond(200,['error'=>false,'mensaje'=>'Contraseña actualizada correctamente.']);
    }

    private function register(bool $owner): void
    {
        $data=$this->body();$email=Security::normalizeEmail((string)($data['email']??''));$limit=new RateLimiter();$limit->ensureAllowed($owner?'owner_register':'register',$email?:'invalid');
        $first=trim((string)($data['nombre']??''));$last=trim((string)($data['apellido']??''));$password=(string)($data['password']??'');$confirmation=(string)($data['password_confirmation']??'');
        if(!(new TurnstileService())->verify((string)($data['turnstileToken']??''))){$limit->fail($owner?'owner_register':'register',$email?:'invalid');$this->respond(400,['error'=>true,'mensaje'=>'No se pudo validar la verificación anti robot.']);return;}
        $errors=[];if(mb_strlen($first)<2||mb_strlen($first)>100)$errors['nombre']='Ingresá un nombre de 2 a 100 caracteres.';if(mb_strlen($last)<2||mb_strlen($last)>100)$errors['apellido']='Ingresá un apellido de 2 a 100 caracteres.';if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Ingresá un correo electrónico válido.';if($password!==$confirmation)$errors['password_confirmation']='Las contraseñas no coinciden.';if($error=Security::passwordError($password,$email))$errors['password']=$error;if(($data['terms_accepted']??false)!==true)$errors['terms_accepted']='Debés aceptar los términos.';if(($data['privacy_accepted']??false)!==true)$errors['privacy_accepted']='Debés aceptar la política de privacidad.';
        if($owner&&mb_strlen(trim((string)($data['gym_name']??'')))<2)$errors['gym_name']='Ingresá el nombre del gimnasio.';
        if($errors!==[]){$limit->fail($owner?'owner_register':'register',$email?:'invalid');$this->respond(422,['error'=>true,'mensaje'=>'Revisá los campos marcados.','fields'=>$errors]);return;}
        if($this->users->emailExiste($email)){$limit->fail($owner?'owner_register':'register',$email);$this->respond(409,['error'=>true,'mensaje'=>'No se pudo crear la cuenta con esos datos.']);return;}
        $this->pdo->beginTransaction();
        try{$userId=$this->users->crear($first,$email,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),null,$last,true,true,($data['marketing_accepted']??false)===true?new DateTimeImmutable():null);
            foreach(['terminos','privacidad'] as $type){$this->pdo->prepare('INSERT INTO user_consents(usuario_id,tipo,version_documento,aceptado_en,ip_hash) VALUES(?,?,?,NOW(),?)')->execute([$userId,$type,'2026-08-06',Security::ipHash()]);}
            if(($data['marketing_accepted']??false)===true)$this->pdo->prepare('INSERT INTO user_consents(usuario_id,tipo,version_documento,aceptado_en,ip_hash) VALUES(?,"marketing",?,NOW(),?)')->execute([$userId,'2026-08-06',Security::ipHash()]);
            if($owner)$this->pdo->prepare('INSERT INTO owner_registration_requests(usuario_id,gimnasio_nombre,mensaje) VALUES(?,?,?)')->execute([$userId,trim((string)$data['gym_name']),mb_substr(trim((string)($data['message']??'')),0,500)]);
            $this->pdo->commit();$token=(new TokenService())->issueEmailVerification($userId);$mailSent=$this->sendLink($email,'Verificá tu correo de GymTrack','/verificar-email?token='.rawurlencode($token));$limit->clear($owner?'owner_register':'register',$email);SecurityLogger::record($owner?'auth.owner_requested':'auth.register','success',$userId);
            if(!$mailSent){$this->respond(503,['error'=>true,'codigo'=>'verification_email_failed','mensaje'=>'La cuenta quedó creada, pero no pudimos enviar el correo. Intentá reenviarlo en unos minutos.','email'=>$email]);return;}
            $this->respond(201,['error'=>false,'mensaje'=>$owner?'Solicitud recibida. Verificá tu correo; no se asignaron permisos de dueño.':'Cuenta creada. Revisá tu correo para verificarla.','email'=>$email]);
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }

    private function sendLink(string $email,string $subject,string $path): bool
    {
        $frontend=rtrim((string)(getenv('FRONTEND_URL')?:'http://localhost:5173'),'/');
        try{(new MailService())->send($email,$subject,"Abrí este enlace para continuar:\n\n{$frontend}{$path}\n\nSi no solicitaste esta acción, ignorá el mensaje.");return true;}catch(Throwable $error){error_log('[GymTrack mail] '.$error->getMessage());return false;}
    }
    private function body(): array { $data=json_decode((string)file_get_contents('php://input'),true);return is_array($data)?$data:[]; }
    private function respond(int $status,array $data): void { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
