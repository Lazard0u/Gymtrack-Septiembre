<?php
/**
 * Seeder DemoDatasetSeeder. Crea datos de presentación idempotentes, identificados y separables de los datos reales.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class DemoDatasetSeeder
{
    private const REQUIRED_MIGRATION = '010_gimnasios_mapa_sincronizado';
    private const VERSION = '1.6.0';

    private PDO $pdo;
    private string $datasetName;
    private array $pendingFileDeletes = [];

    public function __construct()
    {
        $this->pdo = Database::conectar();
        $this->datasetName = trim((string) (getenv('DEMO_DATASET_NAME') ?: 'gymtrack-presentation'));

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $this->datasetName)) {
            throw new RuntimeException('DEMO_DATASET_NAME debe usar 3–64 caracteres: minúsculas, números, punto, guion o guion bajo.');
        }
    }

    public function seed(bool $reset = false): void
    {
        $this->assertSchema();
        $this->assertSeedAllowed();

        $password = (string) getenv('DEMO_USER_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('Definí DEMO_USER_PASSWORD con al menos 12 caracteres. No se usa una contraseña por defecto.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($reset) {
                $existingId = $this->findDatasetId();
                if ($existingId !== null) {
                    $this->removeRows($existingId);
                }
            }

            $datasetId = $this->upsertDataset();
            $gymIds = [];
            foreach ($this->gyms() as $gym) {
                $gymIds[$gym['slug']] = $this->upsertGym($datasetId, $gym);
            }

            $userIds = [];
            foreach ($this->users() as $user) {
                $userIds[$user['email']] = $this->upsertUser($datasetId, $user, $password);
            }

            $this->upsertGymRole($datasetId, $userIds['socio.demo@gymtrack.local'], $gymIds['gymtrack-centro'], 'socio');
            $this->upsertGymRole($datasetId, $userIds['socio.demo@gymtrack.local'], $gymIds['titan-training'], 'socio');
            $this->upsertGymRole($datasetId, $userIds['empleado.demo@gymtrack.local'], $gymIds['gymtrack-centro'], 'empleado');
            $this->upsertGymRole($datasetId, $userIds['dueno.demo@gymtrack.local'], $gymIds['gymtrack-centro'], 'dueño');
            $this->upsertGymRole($datasetId, $userIds['dueno.demo@gymtrack.local'], $gymIds['arena-functional-gym'], 'dueño');

            foreach ($this->gyms() as $gym) {
                $this->upsertPrimaryLocation($datasetId, $gymIds[$gym['slug']], $gym);
            }
            $this->upsertPeopleProfiles($datasetId, $userIds, $gymIds);
            $planIds = $this->upsertPlans($datasetId, $userIds['dueno.demo@gymtrack.local'], $gymIds);

            $classIds = [];
            foreach ($this->classes() as $class) {
                $classIds[$class['demo_key']] = $this->upsertClass(
                    $datasetId,
                    $gymIds['gymtrack-centro'],
                    $userIds['empleado.demo@gymtrack.local'],
                    $class
                );
            }

            $membershipIds=[];
            $membershipIds['titan']=$this->upsertMembership(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $gymIds['titan-training'],
                $planIds['titan-training'],
                'presentation-socio-titan-membership'
            );
            $membershipIds['centro']=$this->upsertMembership(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $gymIds['gymtrack-centro'],
                $planIds['gymtrack-centro'],
                'presentation-socio-centro-membership'
            );
            $this->upsertPayments($datasetId,$gymIds['gymtrack-centro'],$membershipIds['centro'],$userIds['dueno.demo@gymtrack.local']);
            $sessionIds = [];
            foreach ($this->classes() as $class) {
                $sessionIds[$class['demo_key']] = $this->upsertClassSession(
                    $datasetId,
                    $classIds[$class['demo_key']],
                    $gymIds['gymtrack-centro'],
                    $userIds['empleado.demo@gymtrack.local'],
                    $class
                );
            }
            $this->upsertReservation(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $classIds['presentation-centro-funcional'],
                $sessionIds['presentation-centro-funcional']
            );
            $this->recalculateSessionCounters($sessionIds);
            $promotionId = $this->upsertPromotion(
                $datasetId,
                $gymIds['gymtrack-centro'],
                $userIds['dueno.demo@gymtrack.local']
            );
            $this->upsertMarketingConsent($userIds['socio.demo@gymtrack.local']);
            $this->upsertNotificationPreferences($datasetId, $userIds['socio.demo@gymtrack.local']);
            $this->upsertDemoNotifications(
                $datasetId,
                $promotionId,
                $gymIds['gymtrack-centro'],
                $userIds['socio.demo@gymtrack.local']
            );
            $this->upsertMemberExperience(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $userIds['empleado.demo@gymtrack.local'],
                $gymIds,
                $classIds
            );

            $this->pdo->commit();
            $this->purgeDemoFiles();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        echo $reset
            ? "Dataset demo regenerado correctamente.\n"
            : "Dataset demo creado o actualizado sin duplicados.\n";
        $this->status();
    }

    public function remove(): void
    {
        $this->assertSchema();
        $this->warnProduction('eliminar');

        $datasetId = $this->findDatasetId();
        if ($datasetId === null) {
            echo "El dataset '{$this->datasetName}' no existe. No se eliminó ningún registro.\n";
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->removeRows($datasetId);
            $this->pdo->commit();
            $this->purgeDemoFiles();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        echo "Se eliminaron únicamente los registros demo de '{$this->datasetName}'.\n";
    }

    public function status(): void
    {
        $this->assertSchema();
        $datasetId = $this->findDatasetId();
        if ($datasetId === null) {
            echo "Dataset demo '{$this->datasetName}': inactivo.\n";
            return;
        }

        $tables = [
            'gimnasios' => 'gimnasios',
            'usuarios' => 'usuarios',
            'usuario_gimnasio_roles' => 'asociaciones',
            'clases' => 'clases',
            'membresias' => 'membresías',
            'pagos' => 'pagos',
            'reservas' => 'reservas',
        ];
        if ($this->tableExists('audit_logs')) $tables['audit_logs'] = 'eventos de auditoría';
        if ($this->tableExists('exports')) $tables['exports'] = 'exportaciones';
        foreach (['gimnasio_sedes'=>'sedes','entrenador_perfiles'=>'entrenadores','entrenador_gimnasios'=>'asignaciones de entrenador','planes_membresia'=>'planes','invitaciones_gimnasio'=>'invitaciones','archivos'=>'archivos'] as $table=>$label) {
            if ($this->tableExists($table)) $tables[$table]=$label;
        }
        if ($this->tableExists('sesiones_clase')) $tables['sesiones_clase']='sesiones de clase';
        foreach (['promociones'=>'promociones','promocion_gimnasios'=>'alcances de promoción','notificaciones'=>'notificaciones','notificacion_preferencias'=>'preferencias de notificación','notificacion_envios'=>'entregas de notificación'] as $table=>$label) {
            if ($this->tableExists($table)) $tables[$table]=$label;
        }
        foreach (['socio_preferencias'=>'preferencias del socio','actividades'=>'actividades','socio_gimnasios_favoritos'=>'gimnasios favoritos','socio_actividades_favoritas'=>'actividades favoritas','socio_mediciones'=>'mediciones'] as $table=>$label) {
            if ($this->tableExists($table)) $tables[$table]=$label;
        }

        echo "Dataset demo '{$this->datasetName}' v" . self::VERSION . ": activo.\n";
        foreach ($tables as $table => $label) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
            echo sprintf("- %s: %d\n", $label, (int) $stmt->fetchColumn());
        }
        echo "Usuarios: socio.demo@gymtrack.local, empleado.demo@gymtrack.local, dueno.demo@gymtrack.local, admin.demo@gymtrack.local\n";
        echo "Contraseña: valor actual de DEMO_USER_PASSWORD (nunca se imprime ni se guarda en texto plano).\n";
    }

    private function assertSchema(): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
        $stmt->execute([self::REQUIRED_MIGRATION]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Falta aplicar database/migrations/010_gimnasios_mapa_sincronizado.sql.');
        }
    }

    private function assertSeedAllowed(): void
    {
        $environment = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        $seedEnabled = filter_var(getenv('SEED_DEMO_DATA') ?: 'false', FILTER_VALIDATE_BOOL);
        $allowProduction = filter_var(getenv('ALLOW_DEMO_DATA_IN_PRODUCTION') ?: 'false', FILTER_VALIDATE_BOOL);

        if ($environment === 'production') {
            $this->warnProduction('activar');
            if (!$allowProduction) {
                throw new RuntimeException('Dataset demo bloqueado en producción. ALLOW_DEMO_DATA_IN_PRODUCTION=false.');
            }
        }

        if ($environment !== 'demo' && !$seedEnabled) {
            throw new RuntimeException('El seed demo requiere APP_ENV=demo o SEED_DEMO_DATA=true.');
        }
    }

    private function warnProduction(string $action): void
    {
        if (strtolower((string) (getenv('APP_ENV') ?: 'production')) === 'production') {
            fwrite(STDERR, "ADVERTENCIA: se intentó {$action} el dataset demo con APP_ENV=production.\n");
        }
    }

    private function upsertDataset(): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demo_datasets (nombre, version, activo) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE version = VALUES(version), activo = 1, actualizado_en = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$this->datasetName, self::VERSION]);

        return $this->findDatasetId() ?? throw new RuntimeException('No se pudo crear el dataset demo.');
    }

    private function findDatasetId(): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM demo_datasets WHERE nombre = ? LIMIT 1');
        $stmt->execute([$this->datasetName]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function assertOwnedDemo(array|false $existing, int $datasetId, string $type, string $identifier): void
    {
        if (!$existing) {
            return;
        }
        if ((int) $existing['is_demo'] !== 1 || (int) $existing['demo_dataset_id'] !== $datasetId) {
            throw new RuntimeException("No se puede sobrescribir {$type} real con identificador '{$identifier}'.");
        }
    }

    private function upsertGym(int $datasetId, array $gym): int
    {
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM gimnasios WHERE slug = ? LIMIT 1');
        $stmt->execute([$gym['slug']]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'gimnasio', $gym['slug']);

        $values = [
            $gym['nombre'], $gym['nombre_legal'], $gym['descripcion'], $gym['direccion'], $gym['ciudad'], $gym['departamento'],
            $gym['latitud'], $gym['longitud'], 'America/Montevideo', $gym['telefono'], $gym['email'],
            json_encode($gym['horarios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['categorias'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['servicios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $gym['estado'], 'verificado', $gym['imagen_path'],
            json_encode($gym['scenario'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $datasetId,
        ];

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE gimnasios SET nombre = ?, nombre_legal = ?, descripcion = ?, direccion = ?, ciudad = ?, departamento = ?, pais = "Uruguay",
                 latitud = ?, longitud = ?, zona_horaria = ?, telefono = ?, email = ?, horarios_json = ?,
                 categorias_json = ?, servicios_json = ?, estado = ?, verificacion_estado = ?, imagen_path = ?, demo_scenario_json = ?,
                 is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([...$values, (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO gimnasios
             (nombre, nombre_legal, slug, descripcion, direccion, ciudad, departamento, pais, latitud, longitud, zona_horaria,
              telefono, email, horarios_json, categorias_json, servicios_json, estado, verificacion_estado, imagen_path,
              demo_scenario_json, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, "Uruguay", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $gym['nombre'], $gym['nombre_legal'], $gym['slug'], $gym['descripcion'], $gym['direccion'], $gym['ciudad'],
            $gym['departamento'], $gym['latitud'], $gym['longitud'], 'America/Montevideo', $gym['telefono'],
            $gym['email'], json_encode($gym['horarios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['categorias'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['servicios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $gym['estado'], 'verificado',
            $gym['imagen_path'], json_encode($gym['scenario'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $datasetId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upsertUser(int $datasetId, array $user, string $password): int
    {
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$user['email']]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'usuario', $user['email']);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios SET nombre = ?, apellido = ?, email_normalizado = ?, password_hash = ?, telefono = ?, rol_id = ?, activo = 1,
                 email_verificado_en = COALESCE(email_verificado_en, NOW()), debe_cambiar_password = 0,
                 is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([$user['nombre'], $user['apellido'], $user['email'], $hash, $user['telefono'], $this->roleId($user['rol']), $datasetId, (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, email_normalizado, email_verificado_en, password_hash, telefono, rol_id, activo, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 1, 1, ?)'
        );
        $stmt->execute([$user['nombre'], $user['apellido'], $user['email'], $user['email'], $hash, $user['telefono'], $this->roleId($user['rol']), $datasetId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upsertGymRole(int $datasetId, int $userId, int $gymId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario_gimnasio_roles
             (usuario_id, gimnasio_id, rol_id, activo, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, 1, 1, ?)
             ON DUPLICATE KEY UPDATE activo = 1, is_demo = 1, demo_dataset_id = VALUES(demo_dataset_id)'
        );
        $stmt->execute([$userId, $gymId, $this->roleId($role), $datasetId]);
    }

    private function upsertPrimaryLocation(int $datasetId,int $gymId,array $gym): void
    {
        $stmt=$this->pdo->prepare(
            'INSERT INTO gimnasio_sedes
             (gimnasio_id,nombre,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,horarios_json,es_principal,estado,is_demo,demo_dataset_id)
             VALUES (?,"Sede principal",?,?,?,"Uruguay",?,?,? ,?,?,?,1,"activa",1,?)
             ON DUPLICATE KEY UPDATE direccion=VALUES(direccion),ciudad=VALUES(ciudad),departamento=VALUES(departamento),pais=VALUES(pais),latitud=VALUES(latitud),
             longitud=VALUES(longitud),zona_horaria=VALUES(zona_horaria),telefono=VALUES(telefono),email=VALUES(email),horarios_json=VALUES(horarios_json),
             es_principal=1,estado="activa",is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$gymId,$gym['direccion'],$gym['ciudad'],$gym['departamento'],$gym['latitud'],$gym['longitud'],'America/Montevideo',$gym['telefono'],$gym['email'],json_encode($gym['horarios'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$datasetId]);
    }

    private function upsertPeopleProfiles(int $datasetId,array $userIds,array $gymIds): void
    {
        foreach ([['gym'=>'gymtrack-centro','user'=>'socio.demo@gymtrack.local'],['gym'=>'titan-training','user'=>'socio.demo@gymtrack.local']] as $member) {
            $assignment=$this->assignmentId($userIds[$member['user']],$gymIds[$member['gym']],'socio');
            $stmt=$this->pdo->prepare('INSERT INTO socio_perfiles (usuario_gimnasio_rol_id,numero_socio,estado,fecha_alta) VALUES (?,? ,"activo",CURRENT_DATE()) ON DUPLICATE KEY UPDATE estado="activo"');
            $stmt->execute([$assignment,$this->memberNumber($gymIds[$member['gym']],$userIds[$member['user']])]);
        }
        $employeeId=$userIds['empleado.demo@gymtrack.local'];$gymId=$gymIds['gymtrack-centro'];$assignment=$this->assignmentId($employeeId,$gymId,'empleado');
        $this->pdo->prepare('INSERT INTO empleado_perfiles (usuario_gimnasio_rol_id,cargo,estado,fecha_ingreso) VALUES (?,"Entrenador y recepción","activo",CURRENT_DATE()) ON DUPLICATE KEY UPDATE cargo=VALUES(cargo),estado="activo"')->execute([$assignment]);
        $stmt=$this->pdo->prepare('INSERT INTO entrenador_perfiles (usuario_id,biografia,especialidades_json,disponibilidad_json,estado,is_demo,demo_dataset_id) VALUES (?,? ,?,? ,"activo",1,?) ON DUPLICATE KEY UPDATE biografia=VALUES(biografia),especialidades_json=VALUES(especialidades_json),disponibilidad_json=VALUES(disponibilidad_json),estado="activo",is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)');
        $stmt->execute([$employeeId,'Entrenador de demostración para validar especialidades y asignación técnica.',json_encode(['Funcional','Movilidad'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode(['Lunes 18:00','Miércoles 08:00'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$datasetId]);
        $stmt=$this->pdo->prepare('SELECT id FROM entrenador_perfiles WHERE usuario_id=?');$stmt->execute([$employeeId]);$profileId=(int)$stmt->fetchColumn();
        $this->pdo->prepare('INSERT INTO entrenador_gimnasios (entrenador_perfil_id,gimnasio_id,activo,is_demo,demo_dataset_id) VALUES (?,?,1,1,?) ON DUPLICATE KEY UPDATE activo=1,is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)')->execute([$profileId,$gymId,$datasetId]);
    }

    private function upsertPlans(int $datasetId,int $actorId,array $gymIds): array
    {
        $plans=[
            'gymtrack-centro'=>[
                ['nombre'=>'Pase Semanal','slug'=>'pase-semanal-centro','descripcion'=>'Acceso corto para probar la sede principal sin permanencia.','duracion'=>7,'precio'=>'490.00','beneficios'=>['Acceso a sala','Sin permanencia']],
                ['nombre'=>'Plan Centro Base','slug'=>'plan-centro-base','descripcion'=>'Acceso mensual demostrativo a la sede principal.','duracion'=>30,'precio'=>'1290.00','beneficios'=>['Acceso a sala','Evaluación inicial']],
                ['nombre'=>'Plan Centro Plus','slug'=>'plan-centro-plus','descripcion'=>'Acceso ampliado con clases grupales y seguimiento mensual.','duracion'=>30,'precio'=>'1890.00','beneficios'=>['Acceso a sala','Clases grupales ilimitadas','Evaluación mensual']],
            ],
            'titan-training'=>[
                ['nombre'=>'Pase Semanal','slug'=>'pase-semanal-titan','descripcion'=>'Acceso corto al área de fuerza sin permanencia.','duracion'=>7,'precio'=>'520.00','beneficios'=>['Área de fuerza','Sin permanencia']],
                ['nombre'=>'Fuerza Mensual','slug'=>'fuerza-mensual','descripcion'=>'Plan mensual demostrativo orientado a fuerza.','duracion'=>30,'precio'=>'1490.00','beneficios'=>['Área de fuerza','Zona de movilidad']],
                ['nombre'=>'Fuerza Premium','slug'=>'fuerza-premium','descripcion'=>'Plan completo con acompañamiento técnico y movilidad.','duracion'=>30,'precio'=>'2190.00','beneficios'=>['Área de fuerza','Zona de movilidad','Seguimiento técnico mensual']],
            ],
            'arena-functional-gym'=>[
                ['nombre'=>'Pase Semanal','slug'=>'pase-semanal-arena','descripcion'=>'Acceso corto a clases funcionales sin permanencia.','duracion'=>7,'precio'=>'470.00','beneficios'=>['Clases grupales','Sin permanencia']],
                ['nombre'=>'Funcional Flexible','slug'=>'funcional-flexible','descripcion'=>'Plan demostrativo para entrenamiento funcional.','duracion'=>30,'precio'=>'1390.00','beneficios'=>['Clases grupales','Zona exterior']],
                ['nombre'=>'Funcional Plus','slug'=>'funcional-plus','descripcion'=>'Plan ampliado con más clases grupales y zona exterior prioritaria.','duracion'=>30,'precio'=>'1790.00','beneficios'=>['Clases grupales ilimitadas','Zona exterior prioritaria','Evaluación inicial']],
            ],
        ];
        $ids=[];
        foreach($plans as $gymSlug=>$gymPlans){
            $gymId=$gymIds[$gymSlug];
            $planIds=[];
            foreach($gymPlans as $plan){
                $stmt=$this->pdo->prepare('INSERT INTO planes_membresia (gimnasio_id,nombre,slug,descripcion,duracion_dias,precio,moneda,beneficios_json,version,estado,is_demo,demo_dataset_id,creado_por) VALUES (?,?,?,?,?,?,"UYU",?,1,"activo",1,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),descripcion=VALUES(descripcion),duracion_dias=VALUES(duracion_dias),precio=VALUES(precio),beneficios_json=VALUES(beneficios_json),estado="activo",is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)');
                $stmt->execute([$gymId,$plan['nombre'],$plan['slug'],$plan['descripcion'],$plan['duracion'],$plan['precio'],json_encode($plan['beneficios'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$datasetId,$actorId]);
                $stmt=$this->pdo->prepare('SELECT id FROM planes_membresia WHERE gimnasio_id=? AND slug=? AND version=1');
                $stmt->execute([$gymId,$plan['slug']]);
                $planIds[$plan['slug']]=(int)$stmt->fetchColumn();
            }
            // el plan mensual base es el segundo de la lista y es el que queda asociado a la membresía demo del socio
            $ids[$gymSlug]=$planIds[$gymPlans[1]['slug']];
        }
        return $ids;
    }

    private function assignmentId(int $userId,int $gymId,string $role): int
    {
        $stmt=$this->pdo->prepare('SELECT ugr.id FROM usuario_gimnasio_roles ugr JOIN roles r ON r.id=ugr.rol_id WHERE ugr.usuario_id=? AND ugr.gimnasio_id=? AND r.nombre=? LIMIT 1');$stmt->execute([$userId,$gymId,$role]);$id=$stmt->fetchColumn();if($id===false)throw new RuntimeException('No se encontró la asociación demo esperada.');return (int)$id;
    }

    private function memberNumber(int $gymId,int $userId): string{return sprintf('GT-%04d-%06d',$gymId,$userId);}
    private function upsertMembershipHistory(int $membershipId): void{$this->pdo->prepare('INSERT INTO membresia_historial (membresia_id,estado_anterior,estado_nuevo,motivo) SELECT ?,NULL,"activa","Dataset de presentación" WHERE NOT EXISTS (SELECT 1 FROM membresia_historial WHERE membresia_id=?)')->execute([$membershipId,$membershipId]);}

    private function upsertClass(int $datasetId, int $gymId, int $instructorId, array $class): int
    {
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM clases WHERE demo_key = ? LIMIT 1');
        $stmt->execute([$class['demo_key']]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'clase', $class['demo_key']);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE clases SET nombre = ?, instructor_id = ?, gimnasio_id = ?, dia_semana = ?, hora_inicio = ?,
                 hora_fin = ?, cupo_maximo = ?, cupos_disponibles = ?, activa = 1, is_demo = 1,
                 demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([
                $class['nombre'], $instructorId, $gymId, $class['dia'], $class['inicio'], $class['fin'],
                $class['cupo'], $class['disponibles'], $datasetId, (int) $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO clases
             (nombre, instructor_id, gimnasio_id, dia_semana, hora_inicio, hora_fin, cupo_maximo,
              cupos_disponibles, activa, demo_key, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?)'
        );
        $stmt->execute([
            $class['nombre'], $instructorId, $gymId, $class['dia'], $class['inicio'], $class['fin'],
            $class['cupo'], $class['disponibles'], $class['demo_key'], $datasetId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upsertMembership(int $datasetId, int $userId, int $gymId, int $planId, string $demoKey): int
    {
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM membresias WHERE demo_key = ? LIMIT 1');
        $stmt->execute([$demoKey]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'membresía', $demoKey);
        $start = (new DateTimeImmutable('today'))->format('Y-m-d');
        $end = (new DateTimeImmutable('+30 days'))->format('Y-m-d');

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE membresias SET usuario_id = ?, numero_socio = ?, gimnasio_id = ?, plan_id = ?, plan = ?, fecha_inicio = ?,
                 fecha_vencimiento = ?, estado = ?, precio_pagado = ?, is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([$userId, $this->memberNumber($gymId,$userId), $gymId, $planId, 'mensual', $start, $end, 'activa', 1490, $datasetId, (int) $existing['id']]);
            $this->upsertMembershipHistory((int)$existing['id']);
            return (int)$existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO membresias
             (usuario_id, numero_socio, gimnasio_id, plan_id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado,
              demo_key, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$userId, $this->memberNumber($gymId,$userId), $gymId, $planId, 'mensual', $start, $end, 'activa', 1490, $demoKey, $datasetId]);
        $id=(int)$this->pdo->lastInsertId();$this->upsertMembershipHistory($id);return $id;
    }

    private function upsertPayments(int $datasetId,int $gymId,int $membershipId,int $actorId): void
    {
        $payments=[];
        for($months=5;$months>=0;$months--){$date=(new DateTimeImmutable('first day of this month'))->modify('-'.$months.' months')->modify('+'.(5+$months).' days')->format('Y-m-d 12:00:00');$payments[]=['reference'=>'GT-DEMO-INCOME-'.$months,'amount'=>(string)(1190+$months*75),'method'=>['efectivo','transferencia','tarjeta'][$months%3],'status'=>'aprobado','date'=>$date];}
        $payments[]=['reference'=>'GT-DEMO-PENDING','amount'=>'1290.00','method'=>'mercado_pago','status'=>'pendiente','date'=>null];
        $payments[]=['reference'=>'GT-DEMO-REJECTED','amount'=>'1290.00','method'=>'tarjeta','status'=>'rechazado','date'=>null];
        $payments[]=['reference'=>'GT-DEMO-EXPIRED','amount'=>'1290.00','method'=>'mercado_pago','status'=>'vencido','date'=>null];
        $payments[]=['reference'=>'GT-DEMO-REFUNDED','amount'=>'1290.00','method'=>'transferencia','status'=>'reembolsado','date'=>(new DateTimeImmutable('-2 months'))->format('Y-m-d 12:00:00')];
        $sql='INSERT INTO pagos (membresia_id,gimnasio_id,monto,moneda,metodo,estado,proveedor,referencia_externa,modo,concepto,fecha_pago,fecha_vencimiento,aprobado_en,rechazado_en,reembolsado_en,is_demo,demo_dataset_id,creado_por,creado_en) VALUES (?,?,?,"UYU",?,?,?, ?,"prueba","Membresía demo",?, ?,?,?,?,1,?,?,?) ON DUPLICATE KEY UPDATE monto=VALUES(monto),metodo=VALUES(metodo),estado=VALUES(estado),fecha_pago=VALUES(fecha_pago),fecha_vencimiento=VALUES(fecha_vencimiento),aprobado_en=VALUES(aprobado_en),rechazado_en=VALUES(rechazado_en),reembolsado_en=VALUES(reembolsado_en),is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)';
        $stmt=$this->pdo->prepare($sql);
        foreach($payments as $payment){$provider=$payment['method']==='mercado_pago'?'mercado_pago':'manual';$created=$payment['date']??date('Y-m-d H:i:s');$expires=$payment['status']==='pendiente'?(new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s'):($payment['status']==='vencido'?(new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'):null);$approved=in_array($payment['status'],['aprobado','reembolsado'],true)?$payment['date']:null;$rejected=$payment['status']==='rechazado'?$created:null;$refunded=$payment['status']==='reembolsado'?date('Y-m-d H:i:s'):null;$stmt->execute([$membershipId,$gymId,$payment['amount'],$payment['method'],$payment['status'],$provider,$payment['reference'],$payment['date'],$expires,$approved,$rejected,$refunded,$datasetId,$actorId,$created]);$idStmt=$this->pdo->prepare('SELECT id FROM pagos WHERE referencia_externa=?');$idStmt->execute([$payment['reference']]);$paymentId=(int)$idStmt->fetchColumn();$event=$this->pdo->prepare('INSERT IGNORE INTO pago_eventos (pago_id,proveedor,evento_externo_id,tipo,estado_anterior,estado_nuevo,payload_hash,payload_json,request_id) VALUES (?,?,?,"demo.seed",NULL,?,?,?,?)');$payload=json_encode(['dataset'=>$this->datasetName,'reference'=>$payment['reference']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$event->execute([$paymentId,$provider,'demo-'.$payment['reference'],$payment['status'],hash('sha256',$payload),$payload,'00000000-0000-4000-8000-000000000007']);}
    }

    private function upsertPromotion(int $datasetId, int $gymId, int $actorId): int
    {
        $code = 'CENTRO15DEMO';
        $stmt = $this->pdo->prepare(
            'SELECT id,is_demo,demo_dataset_id FROM promociones
             WHERE gimnasio_id=? AND codigo_descuento=? AND eliminado_en IS NULL LIMIT 1'
        );
        $stmt->execute([$gymId, $code]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'promoción', $code);

        $start = (new DateTimeImmutable('today -7 days'))->format('Y-m-d 00:00:00');
        $end = (new DateTimeImmutable('today +21 days'))->format('Y-m-d 23:59:59');
        $channels = json_encode(['internal', 'email'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE promociones SET nombre=?,descripcion=?,estado="activa",audiencia="socios_activos",
                 inicio_en=?,fin_en=?,zona_horaria="America/Montevideo",tipo_descuento="porcentaje",
                 valor_descuento=15.00,moneda="UYU",canales_json=?,creado_por=?,is_demo=1,demo_dataset_id=?,eliminado_en=NULL
                 WHERE id=?'
            );
            $stmt->execute([
                '15% en tu próxima renovación',
                'Promoción demostrativa activa para socios de GymTrack Centro.',
                $start,
                $end,
                $channels,
                $actorId,
                $datasetId,
                (int) $existing['id'],
            ]);
            $promotionId = (int) $existing['id'];
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO promociones
                 (gimnasio_id,nombre,descripcion,estado,audiencia,inicio_en,fin_en,zona_horaria,tipo_descuento,
                  valor_descuento,moneda,codigo_descuento,canales_json,version,creado_por,is_demo,demo_dataset_id)
                 VALUES (?,"15% en tu próxima renovación","Promoción demostrativa activa para socios de GymTrack Centro.",
                  "activa","socios_activos",?,? ,"America/Montevideo","porcentaje",15.00,"UYU",?,?,1,?,1,?)'
            );
            $stmt->execute([$gymId, $start, $end, $code, $channels, $actorId, $datasetId]);
            $promotionId = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO promocion_gimnasios (promocion_id,gimnasio_id,is_demo,demo_dataset_id)
             VALUES (?,?,1,?)
             ON DUPLICATE KEY UPDATE is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$promotionId, $gymId, $datasetId]);
        return $promotionId;
    }

    private function upsertNotificationPreferences(int $datasetId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT is_demo,demo_dataset_id FROM notificacion_preferencias WHERE usuario_id=? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $this->assertOwnedDemo($stmt->fetch(), $datasetId, 'preferencias de notificación', (string) $userId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO notificacion_preferencias
             (usuario_id,internal_transactional,internal_marketing,email_transactional,email_marketing,
              whatsapp_marketing,marketing_unsubscribed_at,is_demo,demo_dataset_id)
             VALUES (?,1,1,1,0,0,NULL,1,?)
             ON DUPLICATE KEY UPDATE internal_transactional=1,internal_marketing=1,email_transactional=1,
              email_marketing=0,whatsapp_marketing=0,marketing_unsubscribed_at=NULL,is_demo=1,
              demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$userId, $datasetId]);
    }

    private function upsertMarketingConsent(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_consents
             WHERE usuario_id=? AND tipo="marketing" AND revocado_en IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() === false) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_consents
                 (usuario_id,tipo,version_documento,aceptado_en,revocado_en,ip_hash)
                 VALUES (? ,"marketing","demo-presentation-v1",NOW(),NULL,NULL)'
            );
            $stmt->execute([$userId]);
        }
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
             SET marketing_consentido_en=COALESCE(marketing_consentido_en,NOW())
             WHERE id=? AND activo=1 AND is_demo=1'
        );
        $stmt->execute([$userId]);
    }

    private function upsertDemoNotifications(int $datasetId, int $promotionId, int $gymId, int $userId): void
    {
        $notificationKey = 'demo:phase9:promotion:centro15:internal';
        $message = 'Tenés 15% de descuento en tu próxima renovación con el código CENTRO15DEMO.';
        $stmt = $this->pdo->prepare(
            'INSERT INTO notificaciones
             (usuario_id,gimnasio_id,mensaje,titulo,cuerpo,categoria,link_url,leida,leida_en,idempotency_key,is_demo,demo_dataset_id)
             VALUES (?, ?, ?,"15% en tu próxima renovación",?,"marketing","/dashboard",0,NULL,?,1,?)
             ON DUPLICATE KEY UPDATE gimnasio_id=VALUES(gimnasio_id),mensaje=VALUES(mensaje),titulo=VALUES(titulo),
              cuerpo=VALUES(cuerpo),categoria="marketing",link_url=VALUES(link_url),leida=0,leida_en=NULL,
              is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$userId, $gymId, $message, $message, $notificationKey, $datasetId]);
        $stmt = $this->pdo->prepare('SELECT id FROM notificaciones WHERE idempotency_key=? LIMIT 1');
        $stmt->execute([$notificationKey]);
        $notificationId = (int) $stmt->fetchColumn();

        $payload = json_encode([
            'promotion_id' => $promotionId,
            'title' => '15% en tu próxima renovación',
            'body' => $message,
            'link_url' => '/dashboard',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare(
            'INSERT INTO notificacion_envios
             (promocion_id,notificacion_id,gimnasio_id,usuario_id,canal,categoria,estado,idempotency_key,
              intentos,max_intentos,programado_en,payload_json,enviado_en,is_demo,demo_dataset_id)
             VALUES (?, ?, ?, ?,"internal","marketing","enviado",?,1,3,NOW(),?,NOW(),1,?)
             ON DUPLICATE KEY UPDATE promocion_id=VALUES(promocion_id),notificacion_id=VALUES(notificacion_id),
              gimnasio_id=VALUES(gimnasio_id),usuario_id=VALUES(usuario_id),estado="enviado",intentos=1,
              payload_json=VALUES(payload_json),enviado_en=COALESCE(enviado_en,NOW()),error_codigo=NULL,error_seguro=NULL,
              is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$promotionId, $notificationId, $gymId, $userId, $notificationKey, $payload, $datasetId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO notificacion_envios
             (promocion_id,notificacion_id,gimnasio_id,usuario_id,canal,categoria,estado,idempotency_key,
              intentos,max_intentos,programado_en,error_codigo,error_seguro,payload_json,is_demo,demo_dataset_id)
             VALUES (?,NULL,?, ?,"email","marketing","omitido",?,0,3,NOW(),"preference_disabled",
              "El socio desactivó el email de marketing.",?,1,?)
             ON DUPLICATE KEY UPDATE promocion_id=VALUES(promocion_id),gimnasio_id=VALUES(gimnasio_id),
              usuario_id=VALUES(usuario_id),estado="omitido",intentos=0,error_codigo="preference_disabled",
              error_seguro="El socio desactivó el email de marketing.",payload_json=VALUES(payload_json),
              is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$promotionId, $gymId, $userId, 'demo:phase9:promotion:centro15:email', $payload, $datasetId]);
    }

    private function upsertMemberExperience(
        int $datasetId,
        int $memberId,
        int $employeeId,
        array $gymIds,
        array $classIds
    ): void {
        $centroId = $gymIds['gymtrack-centro'];
        $preferredSchedules = json_encode(
            ['morning','evening'],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $stmt = $this->pdo->prepare(
            'INSERT INTO socio_preferencias
             (usuario_id,gimnasio_id,objetivo_asistencias_mes,mostrar_imc_orientativo,
              horarios_preferidos_json,is_demo,demo_dataset_id)
             VALUES (?, ?, 10, 1, ?, 1, ?)
             ON DUPLICATE KEY UPDATE objetivo_asistencias_mes=10,mostrar_imc_orientativo=1,
              horarios_preferidos_json=VALUES(horarios_preferidos_json),is_demo=1,
              demo_dataset_id=VALUES(demo_dataset_id)'
        );
        $stmt->execute([$memberId,$centroId,$preferredSchedules,$datasetId]);

        foreach ($this->classes() as $class) {
            $activitySlug = str_replace('presentation-centro-','',$class['demo_key']);
            $stmt = $this->pdo->prepare(
                'INSERT INTO actividades
                 (gimnasio_id,nombre,slug,descripcion,activa,is_demo,demo_dataset_id)
                 VALUES (?, ?, ?, ?, 1, 1, ?)
                 ON DUPLICATE KEY UPDATE slug=VALUES(slug),descripcion=VALUES(descripcion),activa=1,is_demo=1,
                  demo_dataset_id=VALUES(demo_dataset_id)'
            );
            $stmt->execute([
                $centroId,
                $class['nombre'],
                $activitySlug,
                'Actividad de demostración asociada a la agenda de GymTrack Centro.',
                $datasetId,
            ]);
        }

        foreach ([$centroId,$gymIds['titan-training']] as $favoriteGymId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO socio_gimnasios_favoritos
                 (usuario_id,gimnasio_id,is_demo,demo_dataset_id)
                 VALUES (?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
            );
            $stmt->execute([$memberId,$favoriteGymId,$datasetId]);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM actividades
             WHERE gimnasio_id=? AND slug="funcional" AND is_demo=1 AND demo_dataset_id=? LIMIT 1'
        );
        $stmt->execute([$centroId,$datasetId]);
        $favoriteActivityId = (int) $stmt->fetchColumn();
        if ($favoriteActivityId > 0) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO socio_actividades_favoritas
                 (usuario_id,actividad_id,gimnasio_id,is_demo,demo_dataset_id)
                 VALUES (?, ?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE gimnasio_id=VALUES(gimnasio_id),is_demo=1,
                  demo_dataset_id=VALUES(demo_dataset_id)'
            );
            $stmt->execute([$memberId,$favoriteActivityId,$centroId,$datasetId]);
        }

        $measurements = [
            ['00000000-0000-4000-8000-000000000101','-60 days','173.00','79.40','Registro inicial de demostración.'],
            ['00000000-0000-4000-8000-000000000102','-30 days','173.00','78.30','Seguimiento mensual opcional.'],
            ['00000000-0000-4000-8000-000000000103','today','173.00','77.60','Último registro de demostración.'],
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO socio_mediciones
             (usuario_id,gimnasio_id,fecha_medicion,altura_cm,peso_kg,notas,idempotency_key,
              is_demo,demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE fecha_medicion=VALUES(fecha_medicion),altura_cm=VALUES(altura_cm),
              peso_kg=VALUES(peso_kg),notas=VALUES(notas),is_demo=1,
              demo_dataset_id=VALUES(demo_dataset_id)'
        );
        foreach ($measurements as [$key,$relativeDate,$height,$weight,$notes]) {
            $measuredAt = (new DateTimeImmutable($relativeDate))->format('Y-m-d');
            $stmt->execute([$memberId,$centroId,$measuredAt,$height,$weight,$notes,$key,$datasetId]);
        }

        $this->upsertAttendanceHistory(
            $datasetId,
            $memberId,
            $employeeId,
            $centroId,
            $classIds['presentation-centro-funcional']
        );
    }

    private function upsertAttendanceHistory(
        int $datasetId,
        int $memberId,
        int $employeeId,
        int $gymId,
        int $classId
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM gimnasio_sedes WHERE gimnasio_id=? AND es_principal=1 LIMIT 1'
        );
        $stmt->execute([$gymId]);
        $locationId = (int) $stmt->fetchColumn();
        $monthStart = new DateTimeImmutable('first day of this month 07:00:00');
        $dates = [$monthStart,$monthStart->modify('+7 days'),$monthStart->modify('+14 days')];

        foreach ($dates as $index => $start) {
            if ($start >= new DateTimeImmutable()) continue;
            $end = $start->modify('+1 hour');
            $stmt = $this->pdo->prepare(
                'INSERT INTO sesiones_clase
                 (clase_id,gimnasio_id,sede_id,instructor_id,inicio_en,fin_en,zona_horaria,cupo_maximo,
                  cupos_reservados,espera_total,estado,notas,is_demo,demo_dataset_id,creado_por)
                 VALUES (?, ?, ?, ?, ?, ?, "America/Montevideo",18,1,0,"finalizada",
                  "Historial del dataset de presentación",1,?,?)
                 ON DUPLICATE KEY UPDATE sede_id=VALUES(sede_id),instructor_id=VALUES(instructor_id),
                  fin_en=VALUES(fin_en),cupos_reservados=1,estado="finalizada",notas=VALUES(notas),
                  is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
            );
            $stmt->execute([
                $classId,$gymId,$locationId,$employeeId,$start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),$datasetId,$employeeId,
            ]);
            $stmt = $this->pdo->prepare('SELECT id FROM sesiones_clase WHERE clase_id=? AND inicio_en=?');
            $stmt->execute([$classId,$start->format('Y-m-d H:i:s')]);
            $sessionId = (int) $stmt->fetchColumn();
            $demoKey = sprintf('presentation-socio-centro-attendance-%d',$index + 1);
            $reservedAt = $start->modify('-2 days')->format('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                'INSERT INTO reservas
                 (usuario_id,clase_id,sesion_clase_id,fecha_reserva,confirmada_en,estado,origen,
                  demo_key,is_demo,demo_dataset_id)
                 VALUES (?, ?, ?, ?, ?, "asistio","sistema",?,1,?)
                 ON DUPLICATE KEY UPDATE clase_id=VALUES(clase_id),sesion_clase_id=VALUES(sesion_clase_id),
                  fecha_reserva=VALUES(fecha_reserva),confirmada_en=VALUES(confirmada_en),estado="asistio",
                  origen="sistema",is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)'
            );
            $stmt->execute([$memberId,$classId,$sessionId,$reservedAt,$reservedAt,$demoKey,$datasetId]);
            $stmt = $this->pdo->prepare('SELECT id FROM reservas WHERE demo_key=? LIMIT 1');
            $stmt->execute([$demoKey]);
            $reservationId = (int) $stmt->fetchColumn();
            $stmt = $this->pdo->prepare(
                'INSERT INTO asistencias (reserva_id,estado,fecha_asist,registrada_por,notas)
                 VALUES (?,"presente",?,?,"Asistencia de demostración")
                 ON DUPLICATE KEY UPDATE estado="presente",fecha_asist=VALUES(fecha_asist),
                  registrada_por=VALUES(registrada_por),notas=VALUES(notas)'
            );
            $stmt->execute([$reservationId,$end->format('Y-m-d H:i:s'),$employeeId]);
        }
    }

    private function upsertClassSession(int $datasetId,int $classId,int $gymId,int $instructorId,array $class): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM gimnasio_sedes WHERE gimnasio_id=? AND es_principal=1 LIMIT 1');$stmt->execute([$gymId]);$locationId=(int)$stmt->fetchColumn();
        $days=['lunes'=>1,'martes'=>2,'miercoles'=>3,'jueves'=>4,'viernes'=>5,'sabado'=>6,'domingo'=>7];$today=new DateTimeImmutable('today');$offset=($days[$class['dia']]-(int)$today->format('N')+7)%7;if($offset===0)$offset=7;$date=$today->modify('+'.$offset.' days')->format('Y-m-d');$start=$date.' '.$class['inicio'];$end=$date.' '.$class['fin'];
        $stmt=$this->pdo->prepare('INSERT INTO sesiones_clase (clase_id,gimnasio_id,sede_id,instructor_id,inicio_en,fin_en,zona_horaria,cupo_maximo,cupos_reservados,espera_total,estado,is_demo,demo_dataset_id) VALUES (?,?,?,?,?,?,?,?,0,0,"programada",1,?) ON DUPLICATE KEY UPDATE sede_id=VALUES(sede_id),instructor_id=VALUES(instructor_id),fin_en=VALUES(fin_en),cupo_maximo=VALUES(cupo_maximo),estado="programada",motivo_cancelacion=NULL,is_demo=1,demo_dataset_id=VALUES(demo_dataset_id)');$stmt->execute([$classId,$gymId,$locationId,$instructorId,$start,$end,'America/Montevideo',$class['cupo'],$datasetId]);
        $stmt=$this->pdo->prepare('SELECT id FROM sesiones_clase WHERE clase_id=? AND inicio_en=?');$stmt->execute([$classId,$start]);return (int)$stmt->fetchColumn();
    }

    private function recalculateSessionCounters(array $sessionIds): void
    {
        $stmt=$this->pdo->prepare('UPDATE sesiones_clase s SET cupos_reservados=(SELECT COUNT(*) FROM reservas r WHERE r.sesion_clase_id=s.id AND r.estado IN ("confirmada","asistio","no_asistio")),espera_total=(SELECT COUNT(*) FROM reservas r WHERE r.sesion_clase_id=s.id AND r.estado="lista_espera") WHERE s.id=?');foreach($sessionIds as $id)$stmt->execute([$id]);
    }

    private function upsertReservation(int $datasetId, int $userId, int $classId, int $sessionId): void
    {
        $demoKey = 'presentation-socio-centro-reservation';
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM reservas WHERE demo_key = ? LIMIT 1');
        $stmt->execute([$demoKey]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'reserva', $demoKey);
        $reservedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE reservas SET usuario_id = ?, clase_id = ?, sesion_clase_id = ?, fecha_reserva = ?, confirmada_en = ?, estado = ?, posicion_espera=NULL,
                 is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([$userId, $classId, $sessionId, $reservedAt, $reservedAt, 'confirmada', $datasetId, (int) $existing['id']]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO reservas
             (usuario_id, clase_id, sesion_clase_id, fecha_reserva, confirmada_en, estado, demo_key, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$userId, $classId, $sessionId, $reservedAt, $reservedAt, 'confirmada', $demoKey, $datasetId]);
    }

    private function removeRows(int $datasetId): void
    {
        if ($this->tableExists('archivos')) {
            $stmt = $this->pdo->prepare('SELECT storage_key FROM archivos WHERE is_demo = 1 AND demo_dataset_id = ?');
            $stmt->execute([$datasetId]);
            $this->pendingFileDeletes = array_values(array_unique(array_merge($this->pendingFileDeletes, $stmt->fetchAll(PDO::FETCH_COLUMN))));
        }
        foreach (['notificacion_envios','notificaciones','marketing_unsubscribe_tokens','notificacion_preferencias','promocion_gimnasios','promociones'] as $tablaDatos) {
            if (!$this->tableExists($tablaDatos)) continue;
            $stmt = $this->pdo->prepare("DELETE FROM {$tablaDatos} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
        }
        foreach (['socio_actividades_favoritas','socio_gimnasios_favoritos','socio_mediciones','socio_preferencias','actividades'] as $tablaDatos) {
            if (!$this->tableExists($tablaDatos)) continue;
            $stmt = $this->pdo->prepare("DELETE FROM {$tablaDatos} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
        }
        foreach (['archivos','invitaciones_gimnasio','entrenador_gimnasios','entrenador_perfiles','audit_logs','exports'] as $tablaDatos) {
            if (!$this->tableExists($tablaDatos)) continue;
            $stmt = $this->pdo->prepare("DELETE FROM {$tablaDatos} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
        }
        foreach (['reservas','sesiones_clase','pagos','membresias','planes_membresia','clases','usuario_gimnasio_roles','usuarios','gimnasio_sedes','gimnasios'] as $table) {
            if (!$this->tableExists($table)) continue;
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
        }
        $stmt = $this->pdo->prepare('DELETE FROM demo_datasets WHERE id = ? AND nombre = ?');
        $stmt->execute([$datasetId, $this->datasetName]);
    }

    private function purgeDemoFiles(): void
    {
        if ($this->pendingFileDeletes === []) return;
        $storage = new LocalFileStorage();
        foreach ($this->pendingFileDeletes as $key) {
            try {
                $storage->delete((string) $key);
            } catch (Throwable $error) {
                error_log('[GymTrack demo cleanup] No se pudo eliminar un archivo demo: ' . $error->getMessage());
            }
        }
        $this->pendingFileDeletes = [];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private function gyms(): array
    {
        return [
            [
                'nombre' => 'GymTrack Centro', 'nombre_legal' => 'GymTrack Centro Demo SAS', 'slug' => 'gymtrack-centro',
                'descripcion' => 'Sede demostrativa urbana para probar clases, reservas y operación diaria.',
                'direccion' => 'Avenida Demostración 100', 'ciudad' => 'Montevideo', 'departamento' => 'Montevideo',
                'latitud' => -34.9045100, 'longitud' => -56.1850200, 'telefono' => '+598 000 000 01',
                'email' => 'centro@gymtrack.example', 'estado' => 'publicado',
                'imagen_path' => '/demo/gyms/gymtrack-centro.svg',
                'horarios' => ['lunes_viernes' => '06:00–22:00', 'sabado' => '08:00–18:00', 'domingo' => 'Cerrado'],
                'categorias' => ['Musculación', 'Funcional', 'Clases grupales'],
                'servicios' => ['Vestuarios', 'Lockers', 'Evaluación inicial'],
                'scenario' => ['key' => 'multiple_classes', 'label' => 'Varias clases publicadas'],
            ],
            [
                'nombre' => 'Norte Fitness Club', 'nombre_legal' => 'Norte Fitness Club Demo SAS', 'slug' => 'norte-fitness-club',
                'descripcion' => 'Sede demostrativa sin clases para validar estados vacíos y navegación.',
                'direccion' => 'Camino de Prueba Norte 250', 'ciudad' => 'Salto', 'departamento' => 'Salto',
                'latitud' => -31.3880800, 'longitud' => -57.9600800, 'telefono' => '+598 000 000 02',
                'email' => 'norte@gymtrack.example', 'estado' => 'publicado',
                'imagen_path' => '/demo/gyms/norte-fitness-club.svg',
                'horarios' => ['lunes_sabado' => '07:00–21:00', 'domingo' => '09:00–13:00'],
                'categorias' => ['Fitness', 'Cardio'], 'servicios' => ['Duchas', 'Estacionamiento de bicicletas'],
                'scenario' => ['key' => 'no_classes', 'label' => 'Sin clases publicadas'],
            ],
            [
                'nombre' => 'Titan Training', 'nombre_legal' => 'Titan Training Demo SAS', 'slug' => 'titan-training',
                'descripcion' => 'Sede demostrativa con una membresía activa para comprobar el contexto del socio.',
                'direccion' => 'Bulevar Ejemplo 45', 'ciudad' => 'Maldonado', 'departamento' => 'Maldonado',
                'latitud' => -34.9002500, 'longitud' => -54.9508700, 'telefono' => '+598 000 000 03',
                'email' => 'titan@gymtrack.example', 'estado' => 'publicado',
                'imagen_path' => '/demo/gyms/titan-training.svg',
                'horarios' => ['lunes_viernes' => '05:30–23:00', 'fin_de_semana' => '08:00–16:00'],
                'categorias' => ['Fuerza', 'Powerlifting'], 'servicios' => ['Plataformas', 'Área de movilidad'],
                'scenario' => ['key' => 'membership_available', 'label' => 'Membresía demo activa'],
            ],
            [
                'nombre' => 'Punto Activo', 'nombre_legal' => 'Punto Activo Demo SAS', 'slug' => 'punto-activo',
                'descripcion' => 'Sede demostrativa temporalmente cerrada para probar estados no operativos.',
                'direccion' => 'Calle Escenario 88', 'ciudad' => 'Canelones', 'departamento' => 'Canelones',
                'latitud' => -34.5227800, 'longitud' => -56.2777800, 'telefono' => '+598 000 000 04',
                'email' => 'puntoactivo@gymtrack.example', 'estado' => 'temporalmente_cerrado',
                'imagen_path' => '/demo/gyms/punto-activo.svg',
                'horarios' => ['estado' => 'Cerrado temporalmente'],
                'categorias' => ['Bienestar', 'Movilidad'], 'servicios' => ['Accesibilidad'],
                'scenario' => ['key' => 'temporarily_closed', 'label' => 'Cierre temporal'],
            ],
            [
                'nombre' => 'Arena Functional Gym', 'nombre_legal' => 'Arena Functional Gym Demo SAS', 'slug' => 'arena-functional-gym',
                'descripcion' => 'Sede demostrativa para presentar comunicación promocional y segmentación.',
                'direccion' => 'Rambla de Muestra 310', 'ciudad' => 'Ciudad de la Costa', 'departamento' => 'Canelones',
                'latitud' => -34.8353400, 'longitud' => -55.9821300, 'telefono' => '+598 000 000 05',
                'email' => 'arena@gymtrack.example', 'estado' => 'publicado',
                'imagen_path' => '/demo/gyms/arena-functional-gym.svg',
                'horarios' => ['lunes_viernes' => '06:30–22:30', 'sabado' => '08:00–14:00', 'domingo' => 'Cerrado'],
                'categorias' => ['Funcional', 'HIIT'], 'servicios' => ['Clases grupales', 'Zona exterior'],
                'scenario' => [
                    'key' => 'promotions_available', 'label' => 'Promociones configurables',
                    'works' => 'Las campañas respetan audiencia, preferencias y estado de entrega.',
                    'pending' => 'WhatsApp permanece deshabilitado hasta configurar un proveedor.',
                ],
            ],
        ];
    }

    private function users(): array
    {
        return [
            ['nombre' => 'Socio', 'apellido' => 'Demo', 'email' => 'socio.demo@gymtrack.local', 'telefono' => '+598 000 100 01', 'rol' => 'socio'],
            ['nombre' => 'Empleado', 'apellido' => 'Demo', 'email' => 'empleado.demo@gymtrack.local', 'telefono' => '+598 000 100 02', 'rol' => 'empleado'],
            ['nombre' => 'Dueño', 'apellido' => 'Demo', 'email' => 'dueno.demo@gymtrack.local', 'telefono' => '+598 000 100 03', 'rol' => 'dueño'],
            ['nombre' => 'Admin', 'apellido' => 'Demo', 'email' => 'admin.demo@gymtrack.local', 'telefono' => '+598 000 100 04', 'rol' => 'admin_general'],
        ];
    }

    private function roleId(string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("Rol demo no configurado: {$name}.");
        }
        return (int) $id;
    }

    private function classes(): array
    {
        return [
            ['demo_key' => 'presentation-centro-funcional', 'nombre' => 'Entrenamiento funcional', 'dia' => 'lunes', 'inicio' => '18:00:00', 'fin' => '19:00:00', 'cupo' => 18, 'disponibles' => 7],
            ['demo_key' => 'presentation-centro-movilidad', 'nombre' => 'Movilidad y core', 'dia' => 'miercoles', 'inicio' => '08:00:00', 'fin' => '09:00:00', 'cupo' => 14, 'disponibles' => 14],
            ['demo_key' => 'presentation-centro-fuerza', 'nombre' => 'Fuerza inicial', 'dia' => 'viernes', 'inicio' => '19:30:00', 'fin' => '20:30:00', 'cupo' => 12, 'disponibles' => 3],
        ];
    }
}
