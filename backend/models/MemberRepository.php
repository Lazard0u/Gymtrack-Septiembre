<?php
/**
 * Acceso a datos MemberRepository. Sus consultas preparadas leen o modifican MySQL y devuelven estructuras que consumen los controladores.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class MemberRepository
{
    private const BMI_NOTICE = 'El IMC es orientativo y no constituye un diagnostico medico.';

    private PDO $pdo;
    private ?int $datasetId;

    public function __construct(?int $datasetId)
    {
        $this->pdo = Database::conectar();
        $this->datasetId = $datasetId;
    }

    public function memberContext(int $userId, int $gymId, bool $requireActive = true): ?array
    {
        $active = $requireActive
            ? ' AND ugr.activo=1 AND COALESCE(sp.estado,"activo")="activo"'
            : '';
        $stmt = $this->pdo->prepare(
            'SELECT u.id usuario_id,u.nombre,u.apellido,u.email,u.telefono,u.fecha_nacimiento,u.foto_archivo_id,
                    ugr.id asignacion_id,ugr.activo asignacion_activa,COALESCE(sp.estado,"activo") socio_estado,
                    sp.numero_socio,g.id gimnasio_id,g.nombre gimnasio_nombre,g.zona_horaria
             FROM usuarios u
             JOIN usuario_gimnasio_roles ugr ON ugr.usuario_id=u.id AND ugr.gimnasio_id=?
             JOIN roles ro ON ro.id=ugr.rol_id AND ro.nombre="socio"
             JOIN gimnasios g ON g.id=ugr.gimnasio_id
             LEFT JOIN socio_perfiles sp ON sp.usuario_gimnasio_rol_id=ugr.id
             WHERE u.id=? AND u.activo=1
               AND u.is_demo=? AND (u.demo_dataset_id <=> ?)
               AND ugr.is_demo=? AND (ugr.demo_dataset_id <=> ?)
               AND g.is_demo=? AND (g.demo_dataset_id <=> ?)' . $active . '
             LIMIT 1'
        );
        $scope = [$this->demoFlag(), $this->datasetId];
        $stmt->execute([$gymId,$userId,...$scope,...$scope,...$scope]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeContext($row) : null;
    }

    public function gymInScope(int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,slug,estado,is_demo,demo_dataset_id FROM gimnasios
             WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) AND archivado_en IS NULL LIMIT 1'
        );
        $stmt->execute([$gymId,$this->demoFlag(),$this->datasetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function summary(int $userId, int $gymId): array
    {
        $context = $this->memberContext($userId,$gymId);
        if (!$context) return [];
        $preferences = $this->preferences($userId,$gymId);
        $membership = $this->currentMembership($userId,$gymId);
        $pendingPayment = $this->pendingPayment($userId,$gymId);
        $timezone = new DateTimeZone((string) ($context['timezone'] ?: 'America/Montevideo'));
        $monthStart = (new DateTimeImmutable('first day of this month 00:00:00',$timezone))->format('Y-m-d H:i:s');
        $monthEnd = (new DateTimeImmutable('first day of next month 00:00:00',$timezone))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'SELECT s.id sesion_id,s.inicio_en,s.fin_en,s.zona_horaria,s.estado sesion_estado,
                    c.id clase_id,c.nombre,c.color,gs.nombre sede_nombre,
                    CONCAT_WS(" ",trainer.nombre,trainer.apellido) instructor_nombre,
                    r.id reserva_id,r.estado reserva_estado,r.posicion_espera
             FROM reservas r
             JOIN sesiones_clase s ON s.id=r.sesion_clase_id
             JOIN clases c ON c.id=s.clase_id
             JOIN usuarios trainer ON trainer.id=s.instructor_id
             LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id
             WHERE r.usuario_id=? AND s.gimnasio_id=? AND s.inicio_en>NOW()
               AND s.estado="programada" AND r.estado IN ("confirmada","lista_espera")
               AND r.is_demo=? AND (r.demo_dataset_id <=> ?)
             ORDER BY s.inicio_en ASC LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        $nextClass = $stmt->fetch() ?: null;

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM asistencias a
             JOIN reservas r ON r.id=a.reserva_id
             JOIN sesiones_clase s ON s.id=r.sesion_clase_id
             WHERE r.usuario_id=? AND s.gimnasio_id=? AND a.estado IN ("presente","tarde")
               AND s.inicio_en>=? AND s.inicio_en<?
               AND r.is_demo=? AND (r.demo_dataset_id <=> ?)'
        );
        $stmt->execute([$userId,$gymId,$monthStart,$monthEnd,$this->demoFlag(),$this->datasetId]);
        $attended = (int) $stmt->fetchColumn();
        $goal = (int) $preferences['monthly_attendance_goal'];

        return [
            'next_class' => $nextClass,
            'monthly_progress' => [
                'month' => substr($monthStart,0,7),
                'goal' => $goal,
                'attended' => $attended,
                'percentage' => min(100,(int) round(($attended / max(1,$goal)) * 100)),
            ],
            'membership' => $membership,
            'pending_payment' => $pendingPayment,
            'promotions' => $this->relevantPromotions($gymId,$membership,$pendingPayment),
        ];
    }

    public function profile(int $userId, int $gymId): ?array
    {
        $context = $this->memberContext($userId,$gymId);
        if (!$context) return null;
        return [
            'id' => $context['user_id'],
            'first_name' => $context['first_name'],
            'last_name' => $context['last_name'],
            'email' => $context['email'],
            'phone' => $context['phone'],
            'birth_date' => $context['birth_date'],
            'member_number' => $context['member_number'],
            'gym' => ['id'=>$context['gym_id'],'name'=>$context['gym_name']],
            'avatar_url' => $context['photo_file_id'] ? '/api/member/avatar' : null,
        ];
    }

    public function updateProfile(int $userId, int $gymId, array $changes): ?array
    {
        if (!$this->memberContext($userId,$gymId) || $changes === []) return null;
        $columns = [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'phone' => 'telefono',
            'birth_date' => 'fecha_nacimiento',
        ];
        $sets = [];
        $params = [];
        foreach ($columns as $key => $column) {
            if (!array_key_exists($key,$changes)) continue;
            $sets[] = $column . '=?';
            $params[] = $changes[$key];
        }
        if ($sets === []) return $this->profile($userId,$gymId);
        $params[] = $userId;
        $params[] = $this->demoFlag();
        $params[] = $this->datasetId;
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET ' . implode(',',$sets) . '
             WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
        );
        $stmt->execute($params);
        return $this->profile($userId,$gymId);
    }

    public function saveAvatar(int $userId, int $gymId, array $file): ?array
    {
        if (!$this->memberContext($userId,$gymId)) return null;
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT foto_archivo_id FROM usuarios
                 WHERE id=? AND is_demo=? AND (demo_dataset_id <=> ?) FOR UPDATE'
            );
            $stmt->execute([$userId,$this->demoFlag(),$this->datasetId]);
            $oldId = $stmt->fetchColumn();
            $stmt = $this->pdo->prepare(
                'INSERT INTO archivos
                    (gimnasio_id,usuario_id,categoria,nombre_original,storage_key,mime_type,tamano_bytes,sha256,
                     estado,is_demo,demo_dataset_id,creado_por)
                 VALUES (?, ?, "socio_avatar", ?, ?, ?, ?, ?, "activo", ?, ?, ?)'
            );
            $stmt->execute([
                $gymId,$userId,$file['original_name'],$file['storage_key'],$file['mime_type'],$file['size'],$file['sha256'],
                $this->demoFlag(),$this->datasetId,$userId,
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE usuarios SET foto_archivo_id=? WHERE id=?')->execute([$newId,$userId]);
            $oldStorageKey = null;
            if ($oldId !== false && $oldId !== null) {
                $old = $this->pdo->prepare('SELECT storage_key FROM archivos WHERE id=? AND usuario_id=? LIMIT 1');
                $old->execute([(int)$oldId,$userId]);
                $oldStorageKey = $old->fetchColumn() ?: null;
                $this->pdo->prepare('UPDATE archivos SET estado="eliminado",eliminado_en=NOW() WHERE id=? AND usuario_id=?')->execute([(int)$oldId,$userId]);
            }
            $this->pdo->commit();
            return ['id'=>$newId,'avatar_url'=>'/api/member/avatar','old_storage_key'=>$oldStorageKey];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function avatarFile(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,a.storage_key,a.mime_type,a.nombre_original,a.tamano_bytes,a.sha256
             FROM usuarios u JOIN archivos a ON a.id=u.foto_archivo_id
             WHERE u.id=? AND a.usuario_id=u.id AND a.categoria="socio_avatar" AND a.estado="activo"
               AND u.is_demo=? AND (u.demo_dataset_id <=> ?)
               AND a.is_demo=? AND (a.demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$userId,$this->demoFlag(),$this->datasetId,$this->demoFlag(),$this->datasetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function preferences(int $userId, int $gymId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT objetivo_asistencias_mes,mostrar_imc_orientativo,horarios_preferidos_json,actualizado_en
             FROM socio_preferencias
             WHERE usuario_id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        $row = $stmt->fetch();
        return [
            'monthly_attendance_goal' => (int) ($row['objetivo_asistencias_mes'] ?? 8),
            'show_orientation_bmi' => $row ? (bool)$row['mostrar_imc_orientativo'] : true,
            'preferred_schedules' => $row
                ? (json_decode((string)$row['horarios_preferidos_json'],true) ?: [])
                : [],
            'updated_at' => $row['actualizado_en'] ?? null,
        ];
    }

    public function updatePreferences(
        int $userId,
        int $gymId,
        int $goal,
        bool $showBmi,
        array $preferredSchedules
    ): array
    {
        if (!$this->memberContext($userId,$gymId)) return $this->preferences($userId,$gymId);
        $stmt = $this->pdo->prepare(
            'INSERT INTO socio_preferencias
                (usuario_id,gimnasio_id,objetivo_asistencias_mes,mostrar_imc_orientativo,
                 horarios_preferidos_json,is_demo,demo_dataset_id)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE objetivo_asistencias_mes=VALUES(objetivo_asistencias_mes),
                 mostrar_imc_orientativo=VALUES(mostrar_imc_orientativo),
                 horarios_preferidos_json=VALUES(horarios_preferidos_json),
                 is_demo=VALUES(is_demo),demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([
            $userId,$gymId,$goal,$showBmi?1:0,
            json_encode($preferredSchedules,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            $this->demoFlag(),$this->datasetId,
        ]);
        return $this->preferences($userId,$gymId);
    }

    public function favorites(int $userId, int $gymId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id,g.nombre,g.slug,g.ciudad,g.departamento,g.estado,g.imagen_path,f.creado_en favorited_at
             FROM socio_gimnasios_favoritos f JOIN gimnasios g ON g.id=f.gimnasio_id
             WHERE f.usuario_id=? AND f.is_demo=? AND (f.demo_dataset_id <=> ?)
               AND g.is_demo=? AND (g.demo_dataset_id <=> ?) AND g.archivado_en IS NULL
             ORDER BY f.creado_en DESC'
        );
        $scope = [$this->demoFlag(),$this->datasetId];
        $stmt->execute([$userId,...$scope,...$scope]);
        $gyms = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT a.id,a.nombre,a.slug,a.descripcion,f.creado_en favorited_at
             FROM socio_actividades_favoritas f JOIN actividades a ON a.id=f.actividad_id
             WHERE f.usuario_id=? AND f.gimnasio_id=? AND f.is_demo=? AND (f.demo_dataset_id <=> ?)
               AND a.gimnasio_id=? AND a.activa=1 AND a.is_demo=? AND (a.demo_dataset_id <=> ?)
             ORDER BY a.nombre'
        );
        $stmt->execute([$userId,$gymId,...$scope,$gymId,...$scope]);
        $activities = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT a.id,a.nombre,a.slug,a.descripcion,(f.id IS NOT NULL) favorite
             FROM actividades a
             LEFT JOIN socio_actividades_favoritas f ON f.actividad_id=a.id AND f.usuario_id=?
             WHERE a.gimnasio_id=? AND a.activa=1 AND a.is_demo=? AND (a.demo_dataset_id <=> ?)
             ORDER BY a.nombre'
        );
        $stmt->execute([$userId,$gymId,...$scope]);
        $available = array_map(static function(array $row): array {
            $row['id']=(int)$row['id'];$row['favorite']=(bool)$row['favorite'];return $row;
        },$stmt->fetchAll());

        return ['gyms'=>$gyms,'activities'=>$activities,'available_activities'=>$available];
    }

    public function addFavoriteGym(int $userId, int $targetGymId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM gimnasios WHERE id=? AND estado IN ("publicado","temporalmente_cerrado")
             AND archivado_en IS NULL AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$targetGymId,$this->demoFlag(),$this->datasetId]);
        if ($stmt->fetchColumn() === false) return false;
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO socio_gimnasios_favoritos (usuario_id,gimnasio_id,is_demo,demo_dataset_id) VALUES (?,?,?,?)'
        );
        $stmt->execute([$userId,$targetGymId,$this->demoFlag(),$this->datasetId]);
        return true;
    }

    public function addFavoriteActivity(int $userId, int $gymId, int $activityId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM actividades WHERE id=? AND gimnasio_id=? AND activa=1
             AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$activityId,$gymId,$this->demoFlag(),$this->datasetId]);
        if ($stmt->fetchColumn() === false) return false;
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO socio_actividades_favoritas
                (usuario_id,actividad_id,gimnasio_id,is_demo,demo_dataset_id) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$userId,$activityId,$gymId,$this->demoFlag(),$this->datasetId]);
        return true;
    }

    public function removeFavorite(int $userId, int $gymId, string $type, int $targetId): bool
    {
        if ($type === 'gym') {
            $stmt = $this->pdo->prepare(
                'DELETE FROM socio_gimnasios_favoritos
                 WHERE usuario_id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([$userId,$targetId,$this->demoFlag(),$this->datasetId]);
        } else {
            $stmt = $this->pdo->prepare(
                'DELETE FROM socio_actividades_favoritas
                 WHERE usuario_id=? AND actividad_id=? AND gimnasio_id=?
                   AND is_demo=? AND (demo_dataset_id <=> ?)'
            );
            $stmt->execute([$userId,$targetId,$gymId,$this->demoFlag(),$this->datasetId]);
        }
        return $stmt->rowCount() > 0;
    }

    public function attendanceHistory(int $userId, int $gymId, int $page, int $perPage): array
    {
        $base = ' FROM asistencias a
                  JOIN reservas r ON r.id=a.reserva_id
                  JOIN sesiones_clase s ON s.id=r.sesion_clase_id
                  JOIN clases c ON c.id=s.clase_id
                  LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id
                  JOIN usuarios trainer ON trainer.id=s.instructor_id
                  WHERE r.usuario_id=? AND s.gimnasio_id=?
                    AND r.is_demo=? AND (r.demo_dataset_id <=> ?)';
        $params = [$userId,$gymId,$this->demoFlag(),$this->datasetId];
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $base);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page-1)*$perPage;
        $stmt = $this->pdo->prepare(
            'SELECT a.id,a.estado,a.fecha_asist,a.notas,r.id reserva_id,s.id sesion_id,
                    s.inicio_en,s.fin_en,s.zona_horaria,c.nombre clase_nombre,c.color,
                    gs.nombre sede_nombre,CONCAT_WS(" ",trainer.nombre,trainer.apellido) instructor_nombre' .
            $base . ' ORDER BY s.inicio_en DESC,a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'pagination'=>$this->pagination($page,$perPage,$total)];
    }

    public function memberships(int $userId, int $gymId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id,m.numero_socio,m.plan_id,COALESCE(pm.nombre,m.plan) plan,
                    m.fecha_inicio,m.fecha_vencimiento,m.estado,m.precio_pagado,
                    COALESCE(pm.moneda,"UYU") moneda,m.creado_en
             FROM membresias m LEFT JOIN planes_membresia pm ON pm.id=m.plan_id
             WHERE m.usuario_id=? AND m.gimnasio_id=?
               AND m.is_demo=? AND (m.demo_dataset_id <=> ?)
             ORDER BY FIELD(m.estado,"activa","pendiente_pago","suspendida","vencida"),m.fecha_vencimiento DESC,m.id DESC'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        return $stmt->fetchAll();
    }

    public function measurements(int $userId, int $gymId, int $page, int $perPage): array
    {
        $params = [$userId,$gymId,$this->demoFlag(),$this->datasetId];
        $where = ' WHERE usuario_id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)';
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM socio_mediciones' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page-1)*$perPage;
        $stmt = $this->pdo->prepare(
            'SELECT id,fecha_medicion,altura_cm,peso_kg,notas,creado_en,actualizado_en
             FROM socio_mediciones' . $where . '
             ORDER BY fecha_medicion DESC,id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return [
            'items'=>array_map([$this,'normalizeMeasurement'],$stmt->fetchAll()),
            'pagination'=>$this->pagination($page,$perPage,$total),
            'medical_notice'=>self::BMI_NOTICE,
        ];
    }

    public function createMeasurement(int $userId, int $gymId, array $data, ?string $idempotencyKey): array
    {
        if ($idempotencyKey !== null) {
            $existing = $this->measurementByKey($userId,$gymId,$idempotencyKey);
            if ($existing) return ['measurement'=>$existing,'idempotent'=>true];
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO socio_mediciones
                (usuario_id,gimnasio_id,fecha_medicion,altura_cm,peso_kg,notas,idempotency_key,is_demo,demo_dataset_id)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $userId,$gymId,$data['measured_at'],$data['height_cm'],$data['weight_kg'],$data['notes'],$idempotencyKey,
            $this->demoFlag(),$this->datasetId,
        ]);
        return ['measurement'=>$this->measurement((int)$this->pdo->lastInsertId(),$userId,$gymId),'idempotent'=>false];
    }

    public function cardRecord(int $userId, int $gymId): ?array
    {
        $context = $this->memberContext($userId,$gymId);
        if (!$context) return null;
        return ['member'=>$context,'membership'=>$this->currentMembership($userId,$gymId)];
    }

    public function verifyCardMember(int $userId, int $gymId, ?int $membershipId): ?array
    {
        $context = $this->memberContext($userId,$gymId,false);
        if (!$context) return null;
        $membership = null;
        if ($membershipId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT m.id,m.estado,m.fecha_inicio,m.fecha_vencimiento,m.numero_socio,
                        COALESCE(pm.nombre,m.plan) plan
                 FROM membresias m LEFT JOIN planes_membresia pm ON pm.id=m.plan_id
                 WHERE m.id=? AND m.usuario_id=? AND m.gimnasio_id=?
                   AND m.is_demo=? AND (m.demo_dataset_id <=> ?) LIMIT 1'
            );
            $stmt->execute([$membershipId,$userId,$gymId,$this->demoFlag(),$this->datasetId]);
            $membership = $stmt->fetch() ?: null;
        }
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $valid = (bool)$context['assignment_active'] && $context['member_state']==='activo'
            && $membership !== null && $membership['estado']==='activa'
            && $membership['fecha_inicio'] <= $today && $membership['fecha_vencimiento'] >= $today;
        return [
            'valid'=>$valid,
            'member'=>[
                'id'=>$context['user_id'],
                'name'=>trim($context['first_name'].' '.$context['last_name']),
                'member_number'=>$context['member_number'] ?: ($membership['numero_socio'] ?? null),
                'state'=>$context['member_state'],
            ],
            'membership'=>$membership,
        ];
    }

    private function currentMembership(int $userId, int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id,m.numero_socio,m.plan_id,COALESCE(pm.nombre,m.plan) plan,
                    m.fecha_inicio,m.fecha_vencimiento,m.estado,m.precio_pagado,COALESCE(pm.moneda,"UYU") moneda
             FROM membresias m LEFT JOIN planes_membresia pm ON pm.id=m.plan_id
             WHERE m.usuario_id=? AND m.gimnasio_id=? AND m.is_demo=? AND (m.demo_dataset_id <=> ?)
             ORDER BY FIELD(m.estado,"activa","pendiente_pago","suspendida","vencida"),m.fecha_vencimiento DESC,m.id DESC LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        return $stmt->fetch() ?: null;
    }

    private function pendingPayment(int $userId, int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id,p.membresia_id,p.monto,p.moneda,p.estado,p.concepto,p.fecha_vencimiento,p.creado_en
             FROM pagos p JOIN membresias m ON m.id=p.membresia_id
             WHERE m.usuario_id=? AND p.gimnasio_id=? AND p.estado IN ("pendiente","vencido")
               AND p.is_demo=? AND (p.demo_dataset_id <=> ?)
             ORDER BY FIELD(p.estado,"pendiente","vencido"),p.creado_en DESC LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        return $stmt->fetch() ?: null;
    }

    private function relevantPromotions(int $gymId, ?array $membership, ?array $pendingPayment): array
    {
        $hasActiveMembership = ($membership['estado'] ?? null) === 'activa';
        $hasDebt = $pendingPayment !== null;
        $audiences = ['todos_socios'];
        if ($hasActiveMembership) $audiences[] = 'socios_activos';
        else $audiences[] = 'socios_inactivos';
        if ($hasDebt) $audiences[] = 'socios_con_deuda';
        $placeholders = implode(',',array_fill(0,count($audiences),'?'));
        $stmt = $this->pdo->prepare(
            'SELECT id,nombre,descripcion,estado,inicio_en,fin_en,zona_horaria,tipo_descuento,
                    valor_descuento,moneda,codigo_descuento,imagen_archivo_id,canales_json
             FROM promociones
             WHERE gimnasio_id=? AND estado="activa" AND eliminado_en IS NULL
               AND inicio_en<=NOW() AND fin_en>NOW() AND audiencia IN (' . $placeholders . ')
               AND is_demo=? AND (demo_dataset_id <=> ?)
             ORDER BY inicio_en DESC,id DESC LIMIT 3'
        );
        $stmt->execute([$gymId,...$audiences,$this->demoFlag(),$this->datasetId]);
        return array_map(static function(array $row): array {
            $row['id']=(int)$row['id'];
            $row['channels']=json_decode((string)$row['canales_json'],true)?:[];
            unset($row['canales_json']);
            return $row;
        },$stmt->fetchAll());
    }

    private function measurement(int $id, int $userId, int $gymId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,fecha_medicion,altura_cm,peso_kg,notas,creado_en,actualizado_en
             FROM socio_mediciones
             WHERE id=? AND usuario_id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$id,$userId,$gymId,$this->demoFlag(),$this->datasetId]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeMeasurement($row) : null;
    }

    private function measurementByKey(int $userId, int $gymId, string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM socio_mediciones
             WHERE usuario_id=? AND gimnasio_id=? AND idempotency_key=?
               AND is_demo=? AND (demo_dataset_id <=> ?) LIMIT 1'
        );
        $stmt->execute([$userId,$gymId,$key,$this->demoFlag(),$this->datasetId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : $this->measurement((int)$id,$userId,$gymId);
    }

    private function normalizeMeasurement(array $row): array
    {
        $height = $row['altura_cm'] === null ? null : (float)$row['altura_cm'];
        $weight = $row['peso_kg'] === null ? null : (float)$row['peso_kg'];
        $bmi = $height !== null && $weight !== null
            ? round($weight / (($height / 100) ** 2),1)
            : null;
        return [
            'id'=>(int)$row['id'],
            'measured_at'=>$row['fecha_medicion'],
            'height_cm'=>$height,
            'weight_kg'=>$weight,
            'bmi'=>$bmi,
            'bmi_notice'=>$bmi === null ? null : self::BMI_NOTICE,
            'notes'=>$row['notas'],
            'created_at'=>$row['creado_en'],
            'updated_at'=>$row['actualizado_en'],
        ];
    }

    private function normalizeContext(array $row): array
    {
        return [
            'user_id'=>(int)$row['usuario_id'],
            'first_name'=>(string)$row['nombre'],
            'last_name'=>(string)$row['apellido'],
            'email'=>(string)$row['email'],
            'phone'=>$row['telefono'],
            'birth_date'=>$row['fecha_nacimiento'],
            'photo_file_id'=>$row['foto_archivo_id']===null?null:(int)$row['foto_archivo_id'],
            'assignment_id'=>(int)$row['asignacion_id'],
            'assignment_active'=>(bool)$row['asignacion_activa'],
            'member_state'=>(string)$row['socio_estado'],
            'member_number'=>$row['numero_socio'],
            'gym_id'=>(int)$row['gimnasio_id'],
            'gym_name'=>(string)$row['gimnasio_nombre'],
            'timezone'=>(string)$row['zona_horaria'],
        ];
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        return ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage))];
    }

    private function demoFlag(): int
    {
        return $this->datasetId === null ? 0 : 1;
    }
}
