<?php

declare(strict_types=1);

final class AuthorizationService
{
    public const SOCIO = 'socio';
    public const EMPLEADO = 'empleado';
    public const DUENO = 'dueño';
    public const ADMIN = 'admin_general';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function globalRole(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT r.nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1 LIMIT 1');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        return is_string($role) ? $role : null;
    }

    public function gymAssignments(int $userId): array
    {
        if ($this->globalRole($userId) === self::ADMIN) {
            $scope = $this->demoScope($userId);
            $stmt = $this->pdo->prepare(
                'SELECT NULL AS asignacion_id, g.id AS gimnasio_id, g.nombre, g.slug, g.ciudad, g.departamento,
                        g.estado, g.imagen_path, g.is_demo, "admin_general" AS rol_nombre
                 FROM gimnasios g WHERE g.is_demo=? AND (g.demo_dataset_id <=> ?) AND g.archivado_en IS NULL ORDER BY g.nombre'
            );
            $stmt->execute([$scope['is_demo'], $scope['dataset_id']]);
            return array_map(static fn(array $row): array => [
                'assignment_id' => null, 'gimnasio_id' => (int)$row['gimnasio_id'], 'nombre' => $row['nombre'],
                'slug' => $row['slug'], 'ciudad' => $row['ciudad'], 'departamento' => $row['departamento'],
                'estado' => $row['estado'], 'imagen_path' => $row['imagen_path'], 'is_demo' => (bool)$row['is_demo'],
                'rol_nombre' => self::ADMIN,
            ], $stmt->fetchAll());
        }
        $stmt = $this->pdo->prepare(
            'SELECT ugr.id AS asignacion_id, g.id AS gimnasio_id, g.nombre, g.slug, g.ciudad, g.departamento,
                    g.estado, g.imagen_path, g.is_demo, r.nombre AS rol_nombre
             FROM usuario_gimnasio_roles ugr
             JOIN gimnasios g ON g.id=ugr.gimnasio_id
             JOIN roles r ON r.id=ugr.rol_id
             WHERE ugr.usuario_id=? AND ugr.activo=1 AND g.archivado_en IS NULL
             ORDER BY g.nombre, r.nombre'
        );
        $stmt->execute([$userId]);
        return array_map(static fn(array $row): array => [
            'assignment_id' => (int) $row['asignacion_id'],
            'gimnasio_id' => (int) $row['gimnasio_id'],
            'nombre' => $row['nombre'],
            'slug' => $row['slug'],
            'ciudad' => $row['ciudad'],
            'departamento' => $row['departamento'],
            'estado' => $row['estado'],
            'imagen_path' => $row['imagen_path'],
            'is_demo' => (bool) $row['is_demo'],
            'rol_nombre' => $row['rol_nombre'],
        ], $stmt->fetchAll());
    }

    public function canAccessGym(int $userId, int $gymId): bool
    {
        if ($this->globalRole($userId) === self::ADMIN) {
            $scope = $this->demoScope($userId);
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM gimnasios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND archivado_en IS NULL');
            $stmt->execute([$gymId, $scope['is_demo'], $scope['dataset_id']]);
            return (int) $stmt->fetchColumn() === 1;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuario_gimnasio_roles WHERE usuario_id=? AND gimnasio_id=? AND activo=1');
        $stmt->execute([$userId, $gymId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function effectiveRole(int $userId, ?int $gymId): ?string
    {
        $global = $this->globalRole($userId);
        if ($global === self::ADMIN || $gymId === null) {
            return $global;
        }
        $stmt = $this->pdo->prepare(
            'SELECT r.nombre FROM usuario_gimnasio_roles ugr JOIN roles r ON r.id=ugr.rol_id
             WHERE ugr.usuario_id=? AND ugr.gimnasio_id=? AND ugr.activo=1
             ORDER BY FIELD(r.nombre, "dueño", "empleado", "socio") LIMIT 1'
        );
        $stmt->execute([$userId, $gymId]);
        $role = $stmt->fetchColumn();
        return is_string($role) ? $role : $global;
    }

    public function permissions(int $userId, ?int $gymId): array
    {
        $role = $this->effectiveRole($userId, $gymId);
        if ($role === null) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.codigo FROM rol_permisos rp JOIN roles r ON r.id=rp.rol_id JOIN permisos p ON p.id=rp.permiso_id
             WHERE r.nombre=? ORDER BY p.codigo'
        );
        $stmt->execute([$role]);
        $permissions = array_values(array_unique(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));

        if ($gymId !== null && $role !== self::ADMIN) {
            $stmt = $this->pdo->prepare(
                'SELECT p.codigo, ugp.permitido
                 FROM usuario_gimnasio_roles ugr
                 JOIN roles r ON r.id=ugr.rol_id
                 JOIN usuario_gimnasio_permisos ugp ON ugp.usuario_gimnasio_rol_id=ugr.id
                 JOIN permisos p ON p.id=ugp.permiso_id
                 WHERE ugr.usuario_id=? AND ugr.gimnasio_id=? AND ugr.activo=1 AND r.nombre=?'
            );
            $stmt->execute([$userId, $gymId, $role]);
            foreach ($stmt->fetchAll() as $override) {
                $code = (string) $override['codigo'];
                if ((bool) $override['permitido']) {
                    $permissions[] = $code;
                } else {
                    $permissions = array_values(array_filter($permissions, static fn(string $item): bool => $item !== $code));
                }
            }
        }
        sort($permissions);
        return array_values(array_unique($permissions));
    }

    public function hasPermission(int $userId, string $permission, ?int $gymId): bool
    {
        return in_array($permission, $this->permissions($userId, $gymId), true);
    }

    private function demoScope(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT is_demo,demo_dataset_id FROM usuarios WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: ['is_demo' => 0, 'demo_dataset_id' => null];
        return ['is_demo' => (int) $row['is_demo'], 'dataset_id' => $row['demo_dataset_id'] === null ? null : (int) $row['demo_dataset_id']];
    }
}
