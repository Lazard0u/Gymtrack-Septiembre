<?php

declare(strict_types=1);

final class AuthMiddleware
{
    public static function verificarSesion(): void
    {
        (new SessionManager())->start(true);
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST','PUT','PATCH','DELETE'], true)) {
            (new SessionManager())->verifyCsrf();
        }
    }

    public static function verificarEmail(): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
    }

    private static function asegurarEmailVerificado(): void
    {
        $stmt=Database::conectar()->prepare('SELECT email_verificado_en FROM usuarios WHERE id=? AND activo=1');
        $stmt->execute([self::obtenerUsuarioId()]);
        $verifiedAt=$stmt->fetchColumn();
        if ($verifiedAt===false || $verifiedAt===null) self::denegar(403,'Verificá tu correo electrónico para continuar.','email_not_verified');
    }

    public static function verificarRol(string $role): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
        $effective=(new AuthorizationService())->effectiveRole(self::obtenerUsuarioId(),self::obtenerGimnasioContextoId());
        if ($effective!==$role) self::denegar(403,'No tenés permisos para acceder a este recurso.');
    }

    public static function verificarRoles(array $roles): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
        $effective=(new AuthorizationService())->effectiveRole(self::obtenerUsuarioId(),self::obtenerGimnasioContextoId());
        if (!in_array($effective,$roles,true)) self::denegar(403,'No tenés permisos para acceder a este recurso.');
    }

    public static function verificarPermiso(string $permission, ?int $ignoredClientGymId = null): void
    {
        self::verificarSesion();
        self::asegurarEmailVerificado();
        $gymId=self::obtenerGimnasioContextoId();
        if (!(new AuthorizationService())->hasPermission(self::obtenerUsuarioId(),$permission,$gymId)) self::denegar(403,'No tenés el permiso requerido para esta acción.');
    }

    public static function destruirSesionActual(): void
    {
        $manager=new SessionManager();
        if ($manager->start(false)) { $manager->verifyCsrf(); $manager->destroy(true,'logout'); }
    }

    public static function obtenerUsuarioId(): int { return (int)($_SESSION['usuario_id']??0); }
    public static function obtenerRolId(): int { return 0; }
    public static function obtenerRolNombre(): ?string { return (new AuthorizationService())->effectiveRole(self::obtenerUsuarioId(),self::obtenerGimnasioContextoId()); }
    public static function obtenerGimnasioContextoId(): ?int { return empty($_SESSION['gimnasio_contexto_id'])?null:(int)$_SESSION['gimnasio_contexto_id']; }
    public static function requerirContextoGimnasio(): int
    {
        $gymId=self::obtenerGimnasioContextoId();
        if($gymId===null) self::denegar(409,'Seleccioná un gimnasio antes de realizar esta acción.','gym_context_required');
        return $gymId;
    }
    public static function esCuentaDemo(): bool { return (bool)($_SESSION['is_demo']??false); }
    public static function obtenerDemoDatasetId(): ?int { return self::esCuentaDemo()&&!empty($_SESSION['demo_dataset_id'])?(int)$_SESSION['demo_dataset_id']:null; }
    public static function impedirMutacionDemo(): void { if(self::esCuentaDemo()) self::denegar(409,'El panel de presentación es de solo lectura. El CRUD operativo se habilitará en la Fase 5.'); }

    private static function denegar(int $status,string $message,string $code='forbidden'): never
    {
        http_response_code($status); echo json_encode(['error'=>true,'codigo'=>$code,'mensaje'=>$message],JSON_UNESCAPED_UNICODE); exit;
    }
}
