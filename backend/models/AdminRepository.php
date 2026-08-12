<?php

declare(strict_types=1);

final class AdminRepository
{
    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
    }

    public function gym(int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,slug,direccion,ciudad,departamento,zona_horaria,estado,is_demo,imagen_path
             FROM gimnasios WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$gymId, $this->datasetId === null ? 0 : 1, $this->datasetId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['id'] = (int) $row['id'];
        $row['is_demo'] = (bool) $row['is_demo'];
        return $row;
    }

    public function summary(int $gymId): array
    {
        $widgets = [];
        $widgets[] = $this->widget('members', 'Socios activos', function () use ($gymId): int {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT u.id) FROM usuarios u
                 JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id AND ugr.gimnasio_id=? AND ugr.activo=1
                 JOIN roles r ON r.id=ugr.rol_id AND r.nombre="socio"
                 WHERE u.activo=1 AND u.is_demo=? AND (u.demo_dataset_id <=> ?)'
            );
            $stmt->execute([$gymId, $this->demoFlag(), $this->datasetId]);
            return (int) $stmt->fetchColumn();
        });
        $widgets[] = $this->widget('memberships', 'Membresías activas', function () use ($gymId): int {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM membresias WHERE gimnasio_id=? AND estado="activa"
                 AND fecha_inicio<=CURRENT_DATE() AND fecha_vencimiento>=CURRENT_DATE()
                 AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([$gymId, $this->demoFlag(), $this->datasetId]);
            return (int) $stmt->fetchColumn();
        });
        $widgets[] = $this->widget('classes_today', 'Clases de hoy', function () use ($gymId): int {
            $days = [1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'];
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM clases WHERE gimnasio_id=? AND dia_semana=? AND activa=1
                 AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([$gymId, $days[(int) date('N')], $this->demoFlag(), $this->datasetId]);
            return (int) $stmt->fetchColumn();
        });
        $widgets[] = $this->widget('reservations', 'Reservas registradas', function () use ($gymId): int {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM reservas r JOIN clases c ON c.id=r.clase_id
                 WHERE c.gimnasio_id=? AND r.estado IN ("confirmada","asistio")
                 AND r.is_demo=? AND (r.demo_dataset_id <=> ?)'
            );
            $stmt->execute([$gymId, $this->demoFlag(), $this->datasetId]);
            return (int) $stmt->fetchColumn();
        });
        $widgets[] = $this->widget('pending_payments', 'Pagos pendientes', function () use ($gymId): int {
            $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM pagos WHERE gimnasio_id=? AND estado IN ("pendiente","vencido") AND is_demo=? AND (demo_dataset_id <=> ?)');
            $stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);return (int)$stmt->fetchColumn();
        });

        $alerts = [];
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM membresias WHERE gimnasio_id=? AND estado="activa"
             AND fecha_vencimiento BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
             AND is_demo=? AND (demo_dataset_id <=> ?)'
        );
        $stmt->execute([$gymId, $this->demoFlag(), $this->datasetId]);
        $expiring = (int) $stmt->fetchColumn();
        if ($expiring > 0) {
            $alerts[] = ['tone' => 'warning', 'title' => 'Membresías próximas a vencer', 'detail' => "{$expiring} membresía(s) vencen en los próximos 7 días.", 'route' => '/administracion/membresias'];
        }
        $gym = $this->gym($gymId);
        if (($gym['estado'] ?? '') === 'temporalmente_cerrado') {
            $alerts[] = ['tone' => 'warning', 'title' => 'Gimnasio temporalmente cerrado', 'detail' => 'El estado público del gimnasio informa un cierre temporal.', 'route' => '/administracion/configuracion'];
        }

        return ['widgets' => $widgets, 'alerts' => $alerts];
    }

    public function members(int $gymId, array $query): array
    {
        $where = ['ugr.gimnasio_id=?', 'r.nombre="socio"', 'u.is_demo=?', '(u.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['u.nombre', 'u.apellido', 'u.email']);
        if ($query['status'] !== '') {
            $where[] = 'COALESCE(sp.estado,IF(ugr.activo=1,"activo","inactivo"))=?';
            $params[] = in_array($query['status'], ['activo','inactivo','archivado'], true) ? $query['status'] : 'activo';
        }
        $sort = $this->sort($query, ['name' => 'u.nombre', 'email' => 'u.email', 'created_at' => 'u.creado_en'], 'u.nombre');
        $base = ' FROM usuarios u JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id JOIN roles r ON r.id=ugr.rol_id LEFT JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT DISTINCT u.id,ugr.id asignacion_id,sp.id perfil_id,sp.numero_socio,COALESCE(sp.estado,IF(ugr.activo=1,"activo","inactivo")) estado_socio,sp.fecha_alta,u.nombre,u.apellido,u.email,u.telefono,u.activo,u.email_verificado_en,u.creado_en' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(DISTINCT u.id)' . $base,
            $params,
            $query
        );
    }

    public function staff(int $gymId, array $query): array
    {
        $where = ['ugr.gimnasio_id=?', 'r.nombre="empleado"', 'u.is_demo=?', '(u.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['u.nombre', 'u.apellido', 'u.email']);
        $sort = $this->sort($query, ['name' => 'u.nombre', 'email' => 'u.email', 'created_at' => 'ugr.creado_en'], 'u.nombre');
        if ($query['status'] !== '') { $where[] = 'COALESCE(ep.estado,IF(ugr.activo=1,"activo","inactivo"))=?'; $params[] = in_array($query['status'], ['activo','inactivo','archivado'], true) ? $query['status'] : 'activo'; }
        $base = ' FROM usuarios u JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id JOIN roles r ON r.id=ugr.rol_id LEFT JOIN empleado_perfiles ep ON ep.usuario_gimnasio_rol_id=ugr.id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT DISTINCT u.id,ugr.id asignacion_id,ep.id perfil_id,ep.cargo,COALESCE(ep.estado,IF(ugr.activo=1,"activo","inactivo")) estado_empleado,ep.fecha_ingreso,u.nombre,u.apellido,u.email,u.telefono,u.activo,u.email_verificado_en,ugr.creado_en' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(DISTINCT u.id)' . $base,
            $params,
            $query
        );
    }

    public function classes(int $gymId, array $query): array
    {
        $where = ['c.gimnasio_id=?', 'c.is_demo=?', '(c.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['c.nombre', 'u.nombre', 'u.apellido']);
        if ($query['status'] !== '') { $where[] = 'c.activa=?'; $params[] = $query['status'] === 'activa' ? 1 : 0; }
        if ($query['day'] !== '') { $where[] = 'c.dia_semana=?'; $params[] = $query['day']; }
        $sort = $this->sort($query, ['name' => 'c.nombre', 'day' => 'FIELD(c.dia_semana,"lunes","martes","miercoles","jueves","viernes","sabado","domingo")', 'time' => 'c.hora_inicio'], 'c.nombre');
        $base = ' FROM clases c JOIN usuarios u ON u.id=c.instructor_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT c.id,c.nombre,c.dia_semana,c.hora_inicio,c.hora_fin,c.cupo_maximo,c.cupos_disponibles,c.activa,CONCAT_WS(" ",u.nombre,u.apellido) instructor_nombre' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public function reservations(int $gymId, array $query): array
    {
        $where = ['c.gimnasio_id=?', 'r.is_demo=?', '(r.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['u.nombre', 'u.apellido', 'u.email', 'c.nombre']);
        if ($query['status'] !== '') { $where[] = 'r.estado=?'; $params[] = $query['status']; }
        if ($query['from'] !== '') { $where[] = 'DATE(r.fecha_reserva)>=?'; $params[] = $query['from']; }
        if ($query['to'] !== '') { $where[] = 'DATE(r.fecha_reserva)<=?'; $params[] = $query['to']; }
        $sort = $this->sort($query, ['member' => 'u.nombre', 'class' => 'c.nombre', 'created_at' => 'r.fecha_reserva', 'status' => 'r.estado'], 'r.fecha_reserva');
        $base = ' FROM reservas r JOIN clases c ON c.id=r.clase_id JOIN usuarios u ON u.id=r.usuario_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT r.id,r.estado,r.fecha_reserva,c.id clase_id,c.nombre clase_nombre,c.dia_semana,c.hora_inicio,u.id usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email usuario_email' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public function memberships(int $gymId, array $query): array
    {
        $where = ['m.gimnasio_id=?', 'm.is_demo=?', '(m.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['u.nombre', 'u.apellido', 'u.email', 'm.plan', 'pm.nombre']);
        if ($query['status'] !== '') { $where[] = 'm.estado=?'; $params[] = $query['status']; }
        $sort = $this->sort($query, ['member' => 'u.nombre', 'plan' => 'm.plan', 'expires_at' => 'm.fecha_vencimiento', 'status' => 'm.estado'], 'm.fecha_vencimiento');
        $base = ' FROM membresias m JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN planes_membresia pm ON pm.id=m.plan_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT m.id,m.plan_id,COALESCE(pm.nombre,m.plan) plan,m.numero_socio,m.fecha_inicio,m.fecha_vencimiento,m.estado,m.precio_pagado,COALESCE(pm.moneda,"UYU") moneda,m.creado_en,u.id usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email usuario_email' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public function payments(int $gymId, array $query): array
    {
        $where = ['m.gimnasio_id=?', 'm.is_demo=?', '(m.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['u.nombre', 'u.apellido', 'u.email', 'p.metodo']);
        if ($query['method'] !== '') { $where[] = 'p.metodo=?'; $params[] = $query['method']; }
        if ($query['from'] !== '') { $where[] = 'DATE(p.fecha_pago)>=?'; $params[] = $query['from']; }
        if ($query['to'] !== '') { $where[] = 'DATE(p.fecha_pago)<=?'; $params[] = $query['to']; }
        $sort = $this->sort($query, ['member' => 'u.nombre', 'amount' => 'p.monto', 'paid_at' => 'p.fecha_pago', 'method' => 'p.metodo'], 'p.fecha_pago');
        $base = ' FROM pagos p JOIN membresias m ON m.id=p.membresia_id JOIN usuarios u ON u.id=m.usuario_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT p.id,p.monto,p.metodo,p.fecha_pago,"registrado" estado,m.id membresia_id,m.plan,u.id usuario_id,CONCAT_WS(" ",u.nombre,u.apellido) usuario_nombre,u.email usuario_email' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public function activity(int $gymId, array $query): array
    {
        $where = ['a.gimnasio_id=?', 'a.is_demo=?', '(a.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        $this->like($where, $params, $query['q'], ['a.accion', 'a.entidad', 'a.motivo', 'u.email']);
        if ($query['status'] !== '') { $where[] = 'a.resultado=?'; $params[] = $query['status']; }
        $sort = $this->sort($query, ['created_at' => 'a.creado_en', 'action' => 'a.accion', 'result' => 'a.resultado'], 'a.creado_en');
        $base = ' FROM audit_logs a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT a.id,a.accion,a.entidad,a.entidad_id,a.resultado,a.motivo,a.request_id,a.creado_en,u.nombre usuario_nombre,u.email usuario_email' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public function exports(int $gymId, array $query): array
    {
        $where = ['e.gimnasio_id=?', 'e.is_demo=?', '(e.demo_dataset_id <=> ?)'];
        $params = [$gymId, $this->demoFlag(), $this->datasetId];
        if ($query['status'] !== '') { $where[] = 'e.estado=?'; $params[] = $query['status']; }
        $sort = $this->sort($query, ['created_at' => 'e.creado_en', 'module' => 'e.modulo', 'status' => 'e.estado'], 'e.creado_en');
        $base = ' FROM exports e LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE ' . implode(' AND ', $where);
        return $this->page(
            'SELECT e.id,e.tipo,e.modulo,e.estado,e.archivo_nombre,e.mime_type,e.tamano_bytes,e.error_seguro,e.request_id,e.creado_en,e.completado_en,e.expira_en,IF(e.estado="completado" AND e.expira_en>NOW(),1,0) descargable,u.email usuario_email' . $base . " ORDER BY {$sort}",
            'SELECT COUNT(*)' . $base,
            $params,
            $query
        );
    }

    public static function query(array $input): array
    {
        $page = max(1, min(100000, (int) ($input['page'] ?? 1)));
        $perPage = max(10, min(50, (int) ($input['per_page'] ?? 20)));
        $direction = strtolower((string) ($input['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $date = static fn(string $key): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input[$key] ?? '')) ? (string) $input[$key] : '';
        return [
            'page' => $page,
            'per_page' => $perPage,
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 100),
            'sort' => mb_substr((string) ($input['sort'] ?? ''), 0, 40),
            'direction' => $direction,
            'status' => mb_substr((string) ($input['status'] ?? ''), 0, 30),
            'day' => in_array((string) ($input['day'] ?? ''), ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'], true) ? (string) $input['day'] : '',
            'method' => in_array((string) ($input['method'] ?? ''), ['efectivo','transferencia','tarjeta'], true) ? (string) $input['method'] : '',
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    private function widget(string $key, string $label, callable $resolver): array
    {
        try {
            return ['key' => $key, 'label' => $label, 'status' => 'ready', 'value' => $resolver(), 'detail' => null];
        } catch (Throwable $error) {
            error_log("[GymTrack summary {$key}] " . $error->getMessage());
            return ['key' => $key, 'label' => $label, 'status' => 'error', 'value' => null, 'detail' => 'No se pudo cargar este indicador.'];
        }
    }

    private function page(string $selectSql, string $countSql, array $params, array $query): array
    {
        $count = $this->pdo->prepare($countSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($query['page'] - 1) * $query['per_page'];
        $stmt = $this->pdo->prepare($selectSql . ' LIMIT ' . $query['per_page'] . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return [
            'items' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $query['page'],
                'per_page' => $query['per_page'],
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $query['per_page'])),
            ],
        ];
    }

    private function like(array &$where, array &$params, string $value, array $columns): void
    {
        if ($value === '') return;
        $where[] = '(' . implode(' OR ', array_map(static fn(string $column): string => "{$column} LIKE ?", $columns)) . ')';
        foreach ($columns as $_) $params[] = '%' . addcslashes($value, '%_\\') . '%';
    }

    private function sort(array $query, array $allowed, string $fallback): string
    {
        $column = $allowed[$query['sort']] ?? $fallback;
        return $column . ' ' . strtoupper($query['direction']);
    }

    private function demoFlag(): int
    {
        return $this->datasetId === null ? 0 : 1;
    }
}
