<?php

declare(strict_types=1);

final class DemoDatasetSeeder
{
    private const MIGRATION = '003_phase3_identity_security';
    private const VERSION = '1.1.0';

    private PDO $pdo;
    private string $datasetName;

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

            $classIds = [];
            foreach ($this->classes() as $class) {
                $classIds[$class['demo_key']] = $this->upsertClass(
                    $datasetId,
                    $gymIds['gymtrack-centro'],
                    $userIds['empleado.demo@gymtrack.local'],
                    $class
                );
            }

            $this->upsertMembership(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $gymIds['titan-training']
            );
            $this->upsertReservation(
                $datasetId,
                $userIds['socio.demo@gymtrack.local'],
                $classIds['presentation-centro-funcional']
            );

            $this->pdo->commit();
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
            'reservas' => 'reservas',
        ];

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
        $stmt->execute([self::MIGRATION]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Falta aplicar database/migrations/003_phase3_identity_security.sql.');
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
            $gym['nombre'], $gym['descripcion'], $gym['direccion'], $gym['ciudad'], $gym['departamento'],
            $gym['latitud'], $gym['longitud'], 'America/Montevideo', $gym['telefono'], $gym['email'],
            json_encode($gym['horarios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['categorias'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['servicios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $gym['estado'], $gym['imagen_path'],
            json_encode($gym['scenario'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $datasetId,
        ];

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE gimnasios SET nombre = ?, descripcion = ?, direccion = ?, ciudad = ?, departamento = ?,
                 latitud = ?, longitud = ?, zona_horaria = ?, telefono = ?, email = ?, horarios_json = ?,
                 categorias_json = ?, servicios_json = ?, estado = ?, imagen_path = ?, demo_scenario_json = ?,
                 is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([...$values, (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO gimnasios
             (nombre, slug, descripcion, direccion, ciudad, departamento, latitud, longitud, zona_horaria,
              telefono, email, horarios_json, categorias_json, servicios_json, estado, imagen_path,
              demo_scenario_json, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $gym['nombre'], $gym['slug'], $gym['descripcion'], $gym['direccion'], $gym['ciudad'],
            $gym['departamento'], $gym['latitud'], $gym['longitud'], 'America/Montevideo', $gym['telefono'],
            $gym['email'], json_encode($gym['horarios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['categorias'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($gym['servicios'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $gym['estado'],
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

    private function upsertMembership(int $datasetId, int $userId, int $gymId): void
    {
        $demoKey = 'presentation-socio-titan-membership';
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM membresias WHERE demo_key = ? LIMIT 1');
        $stmt->execute([$demoKey]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'membresía', $demoKey);
        $start = (new DateTimeImmutable('today'))->format('Y-m-d');
        $end = (new DateTimeImmutable('+30 days'))->format('Y-m-d');

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE membresias SET usuario_id = ?, gimnasio_id = ?, plan = ?, fecha_inicio = ?,
                 fecha_vencimiento = ?, estado = ?, precio_pagado = ?, is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([$userId, $gymId, 'mensual', $start, $end, 'activa', 1490, $datasetId, (int) $existing['id']]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO membresias
             (usuario_id, gimnasio_id, plan, fecha_inicio, fecha_vencimiento, estado, precio_pagado,
              demo_key, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$userId, $gymId, 'mensual', $start, $end, 'activa', 1490, $demoKey, $datasetId]);
    }

    private function upsertReservation(int $datasetId, int $userId, int $classId): void
    {
        $demoKey = 'presentation-socio-centro-reservation';
        $stmt = $this->pdo->prepare('SELECT id, is_demo, demo_dataset_id FROM reservas WHERE demo_key = ? LIMIT 1');
        $stmt->execute([$demoKey]);
        $existing = $stmt->fetch();
        $this->assertOwnedDemo($existing, $datasetId, 'reserva', $demoKey);
        $reservedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE reservas SET usuario_id = ?, clase_id = ?, fecha_reserva = ?, estado = ?,
                 is_demo = 1, demo_dataset_id = ? WHERE id = ?'
            );
            $stmt->execute([$userId, $classId, $reservedAt, 'confirmada', $datasetId, (int) $existing['id']]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO reservas
             (usuario_id, clase_id, fecha_reserva, estado, demo_key, is_demo, demo_dataset_id)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$userId, $classId, $reservedAt, 'confirmada', $demoKey, $datasetId]);
    }

    private function removeRows(int $datasetId): void
    {
        foreach (['reservas', 'membresias', 'clases', 'usuario_gimnasio_roles', 'usuarios', 'gimnasios'] as $table) {
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE is_demo = 1 AND demo_dataset_id = ?");
            $stmt->execute([$datasetId]);
        }
        $stmt = $this->pdo->prepare('DELETE FROM demo_datasets WHERE id = ? AND nombre = ?');
        $stmt->execute([$datasetId, $this->datasetName]);
    }

    private function gyms(): array
    {
        return [
            [
                'nombre' => 'GymTrack Centro', 'slug' => 'gymtrack-centro',
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
                'nombre' => 'Norte Fitness Club', 'slug' => 'norte-fitness-club',
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
                'nombre' => 'Titan Training', 'slug' => 'titan-training',
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
                'nombre' => 'Punto Activo', 'slug' => 'punto-activo',
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
                'nombre' => 'Arena Functional Gym', 'slug' => 'arena-functional-gym',
                'descripcion' => 'Sede demostrativa para presentar comunicación promocional en estado beta.',
                'direccion' => 'Rambla de Muestra 310', 'ciudad' => 'Ciudad de la Costa', 'departamento' => 'Canelones',
                'latitud' => -34.8353400, 'longitud' => -55.9821300, 'telefono' => '+598 000 000 05',
                'email' => 'arena@gymtrack.example', 'estado' => 'publicado',
                'imagen_path' => '/demo/gyms/arena-functional-gym.svg',
                'horarios' => ['lunes_viernes' => '06:30–22:30', 'sabado' => '08:00–14:00', 'domingo' => 'Cerrado'],
                'categorias' => ['Funcional', 'HIIT'], 'servicios' => ['Clases grupales', 'Zona exterior'],
                'scenario' => [
                    'key' => 'promotions_beta', 'label' => 'Promociones — Beta',
                    'works' => 'La promoción puede mostrarse como contenido informativo.',
                    'pending' => 'Todavía no envía campañas ni aplica descuentos automáticamente.',
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
