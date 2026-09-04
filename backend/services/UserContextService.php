<?php

declare(strict_types=1);

/**
 * Construye la identidad pública de una sesión.
 *
 * Login, /me y cambio de gimnasio llaman este servicio para que Vue siempre
 * reciba la misma forma de usuario, rol efectivo, tenant y permisos.
 */
final class UserContextService
{
    /**
     * @return array<string,mixed> Datos seguros; password_hash nunca se incluye.
     */
    public function payload(int $userId): array
    {
        $user = (new Usuario())->buscarPorId($userId);
        if (!$user) {
            throw new RuntimeException('La cuenta ya no está disponible.');
        }

        // El contexto procede de la sesión validada, no de parámetros del cliente.
        $gymId = AuthMiddleware::obtenerGimnasioContextoId();
        $authorization = new AuthorizationService();

        return [
            'id' => (int) $user['id'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'email' => $user['email'],
            'telefono' => $user['telefono'],
            'role' => $authorization->effectiveRole($userId, $gymId),
            'global_role' => $user['rol_nombre'],
            'email_verified' => $user['email_verificado_en'] !== null,
            'must_change_password' => (bool) $user['debe_cambiar_password'],
            'is_demo' => (bool) $user['is_demo'],
            'gimnasios' => $authorization->gymAssignments($userId),
            'active_gym_id' => $gymId,
            'permissions' => $authorization->permissions($userId, $gymId),
        ];
    }
}
