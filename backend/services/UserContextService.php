<?php

declare(strict_types=1);

final class UserContextService
{
    public function payload(int $userId): array
    {
        $user=(new Usuario())->buscarPorId($userId);
        if(!$user) throw new RuntimeException('La cuenta ya no está disponible.');
        $gymId=AuthMiddleware::obtenerGimnasioContextoId();
        $authz=new AuthorizationService();
        return [
            'id'=>(int)$user['id'],'nombre'=>$user['nombre'],'apellido'=>$user['apellido'],'email'=>$user['email'],'telefono'=>$user['telefono'],
            'role'=>$authz->effectiveRole($userId,$gymId),'global_role'=>$user['rol_nombre'],
            'email_verified'=>$user['email_verificado_en']!==null,'must_change_password'=>(bool)$user['debe_cambiar_password'],
            'is_demo'=>(bool)$user['is_demo'],'gimnasios'=>$authz->gymAssignments($userId),'active_gym_id'=>$gymId,
            'permissions'=>$authz->permissions($userId,$gymId),
        ];
    }
}
