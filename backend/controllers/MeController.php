<?php
/**
 * Rutas de la cuenta propia: sesiones activas y su revocación, cambio de gimnasio activo y solicitud de cuenta
 * de dueño.
 */

declare(strict_types=1);

final class MeController
{
    public function probe(): void
    {
        if(!(new SessionManager())->start(false)){$this->respond(200,['error'=>false,'authenticated'=>false]);return;}
        $this->respond(200,['error'=>false,'authenticated'=>true,'usuario'=>(new UserContextService())->payload(AuthMiddleware::obtenerUsuarioId()),'csrf_token'=>(string)($_SESSION['csrf_token']??'')]);
    }

    public function show(): void
    {
        AuthMiddleware::verificarSesion();
        $this->respond(200,['error'=>false,'usuario'=>(new UserContextService())->payload(AuthMiddleware::obtenerUsuarioId()),'csrf_token'=>(string)($_SESSION['csrf_token']??'')]);
    }

    public function sessions(): void
    {
        AuthMiddleware::verificarSesion();$current=(string)($_SESSION['session_public_id']??'');
        $this->respond(200,['error'=>false,'sessions'=>(new SessionManager())->listForUser(AuthMiddleware::obtenerUsuarioId(),$current)]);
    }

    public function revokeSession(string $publicId): void
    {
        AuthMiddleware::verificarSesion();
        if(!preg_match('/^[a-f0-9-]{36}$/i',$publicId)){$this->respond(404,['error'=>true,'mensaje'=>'Sesión no encontrada.']);return;}
        $ok=(new SessionManager())->revokeOne(AuthMiddleware::obtenerUsuarioId(),$publicId,(string)($_SESSION['session_public_id']??''));
        if(!$ok){$this->respond(409,['error'=>true,'mensaje'=>'No podés revocar la sesión actual desde esta acción.']);return;}
        SecurityLogger::record('auth.session_revoked','success',AuthMiddleware::obtenerUsuarioId(),['session_public_id'=>$publicId]);$this->respond(200,['error'=>false,'mensaje'=>'Sesión revocada.']);
    }

    public function logoutAll(): void
    {
        AuthMiddleware::verificarSesion();$userId=AuthMiddleware::obtenerUsuarioId();$manager=new SessionManager();$count=$manager->revokeAll($userId,null,'logout_all');$manager->destroy(true,'logout_all');SecurityLogger::record('auth.logout_all','success',$userId,['sessions'=>$count]);
        $this->respond(200,['error'=>false,'mensaje'=>'Se cerraron todas las sesiones.']);
    }

    public function switchGym(): void
    {
        AuthMiddleware::verificarSesion();$data=json_decode((string)file_get_contents('php://input'),true);$gymId=(int)($data['gym_id']??0);
        if($gymId<1){$this->respond(422,['error'=>true,'mensaje'=>'Seleccioná un gimnasio válido.']);return;}
        if((new AuthorizationService())->globalRole(AuthMiddleware::obtenerUsuarioId())===AuthorizationService::ADMIN){$this->respond(409,['error'=>true,'codigo'=>'admin_context_endpoint_required','mensaje'=>'Usá Administración para seleccionar un gimnasio e indicar el motivo de soporte.']);return;}
        $csrf=(new SessionManager())->rotateContext($gymId);$payload=(new UserContextService())->payload(AuthMiddleware::obtenerUsuarioId());SecurityLogger::record('auth.gym_context_changed','success',AuthMiddleware::obtenerUsuarioId(),['gym_id'=>$gymId]);
        $this->respond(200,['error'=>false,'mensaje'=>'Contexto de gimnasio actualizado.','usuario'=>$payload,'csrf_token'=>$csrf]);
    }

    public function clearGym(): void
    {
        AuthMiddleware::verificarSesion();
        $userId=AuthMiddleware::obtenerUsuarioId();
        if((new AuthorizationService())->gymAssignments($userId)!==[]){
            $this->respond(409,['error'=>true,'codigo'=>'gym_context_required','mensaje'=>'Tu cuenta necesita un gimnasio activo. Seleccioná otro gimnasio en lugar de desactivar el contexto.']);
            return;
        }
        $csrf=(new SessionManager())->rotateContext(null);$payload=(new UserContextService())->payload($userId);
        $this->respond(200,['error'=>false,'mensaje'=>'Contexto de gimnasio desactivado.','usuario'=>$payload,'csrf_token'=>$csrf]);
    }

    /** Un socio con sesión iniciada pide acceso como dueño sin crear otra cuenta: queda una solicitud pendiente que revisa un administrador. */
    public function ownerRequest(): void
    {
        AuthMiddleware::verificarRol(AuthorizationService::SOCIO);
        $userId=AuthMiddleware::obtenerUsuarioId();
        $limit=new RateLimiter();$limit->ensureAllowed('owner_request',(string)$userId);
        $data=json_decode((string)file_get_contents('php://input'),true);$data=is_array($data)?$data:[];
        $gym=trim((string)($data['gym_name']??''));$message=mb_substr(trim((string)($data['message']??'')),0,500);
        if(mb_strlen($gym)<2||mb_strlen($gym)>120){$limit->fail('owner_request',(string)$userId);$this->respond(422,['error'=>true,'mensaje'=>'Revisá los campos marcados.','fields'=>['gym_name'=>'Ingresá el nombre del gimnasio (2 a 120 caracteres).']]);return;}
        try{
            Database::conectar()->prepare('INSERT INTO owner_registration_requests(usuario_id,gimnasio_nombre,mensaje) VALUES(?,?,?)')->execute([$userId,$gym,$message]);
        }catch(PDOException $error){
            if($error->getCode()!=='23000')throw $error;
            $limit->fail('owner_request',(string)$userId);$this->respond(409,['error'=>true,'mensaje'=>'Ya tenés una solicitud pendiente. Un administrador la va a revisar.']);return;
        }
        SecurityLogger::record('auth.owner_requested','success',$userId);
        $this->respond(201,['error'=>false,'mensaje'=>'Recibimos tu solicitud. Un administrador la va a revisar y vincular el gimnasio a tu cuenta.']);
    }

    private function respond(int $status,array $data): void { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
