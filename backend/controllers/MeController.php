<?php

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
        $csrf=(new SessionManager())->rotateContext($gymId);$payload=(new UserContextService())->payload(AuthMiddleware::obtenerUsuarioId());SecurityLogger::record('auth.gym_context_changed','success',AuthMiddleware::obtenerUsuarioId(),['gym_id'=>$gymId]);
        $this->respond(200,['error'=>false,'mensaje'=>'Contexto de gimnasio actualizado.','usuario'=>$payload,'csrf_token'=>$csrf]);
    }

    public function clearGym(): void
    {
        AuthMiddleware::verificarSesion();$csrf=(new SessionManager())->rotateContext(null);$payload=(new UserContextService())->payload(AuthMiddleware::obtenerUsuarioId());
        $this->respond(200,['error'=>false,'mensaje'=>'Contexto de gimnasio desactivado.','usuario'=>$payload,'csrf_token'=>$csrf]);
    }

    private function respond(int $status,array $data): void { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
