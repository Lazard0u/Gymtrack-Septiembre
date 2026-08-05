<?php
/**
 * GymTrack · Usuario.php (Model)
 * ---------------------------------------------------------------
 * Modelo que representa la tabla `usuarios` de la base de datos.
 *
 * ¿Qué hace un Model en el patrón MVC?
 *   Es el único lugar que habla directamente con la base de datos.
 *   Los controladores no escriben SQL — le piden al modelo que lo haga.
 *   Esto separa la lógica de negocio del acceso a datos.
 *
 * Tabla correspondiente: usuarios
 *   id, nombre, email, password_hash, telefono,
 *   fecha_nacimiento, rol_id, activo, creado_en
 */

class Usuario
{
    private PDO $pdo;

    public function __construct()
    {
        // El modelo obtiene la conexión del singleton Database
        $this->pdo = Database::conectar();
    }

    // ─────────────────────────────────────────────────────────
    // BUSCAR POR EMAIL
    // ─────────────────────────────────────────────────────────

    /**
     * Busca un usuario por su dirección de correo.
     *
     * Se usa en el login para verificar si el email existe
     * y luego comparar la contraseña con password_verify().
     *
     * @param  string $email  El correo ingresado por el usuario
     * @return array|false    Los datos del usuario o false si no existe
     */
    public function buscarPorEmail(string $email): array|false
    {
        // NUNCA concatenamos $email directamente en la query.
        // Usamos "?" como marcador y PDO lo reemplaza de forma segura.
        // Esto previene ataques de SQL Injection.
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, email, password_hash, rol_id, activo
             FROM usuarios
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);

        return $stmt->fetch(); // Devuelve array asociativo o false
    }

    // ─────────────────────────────────────────────────────────
    // VERIFICAR EMAIL ÚNICO
    // ─────────────────────────────────────────────────────────

    /**
     * Verifica si un email ya está registrado en la base de datos.
     * Se llama durante el registro para cumplir el requerimiento RF-02.
     *
     * @param  string $email
     * @return bool   true si ya existe, false si está disponible
     */
    public function emailExiste(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE email = ?'
        );
        $stmt->execute([$email]);

        // fetchColumn() devuelve el valor de la primera columna
        // de la primera fila — en este caso el COUNT(*)
        return (int) $stmt->fetchColumn() > 0;
    }

    // ─────────────────────────────────────────────────────────
    // CREAR USUARIO
    // ─────────────────────────────────────────────────────────

    /**
     * Inserta un nuevo usuario en la base de datos.
     *
     * La contraseña ya llega cifrada desde el controlador.
     * El modelo no sabe nada del cifrado — esa responsabilidad
     * es del AuthController.
     *
     * @param  string $nombre
     * @param  string $email
     * @param  string $passwordHash  Hash generado con BCRYPT
     * @param  string|null $telefono
     * @return int    ID del usuario recién creado
     */
    public function crear(
        string $nombre,
        string $email,
        string $passwordHash,
        ?string $telefono = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol_id, activo)
             VALUES (?, ?, ?, ?, 1, 1)'
            // rol_id = 1 → socio (definido en la tabla roles)
            // activo  = 1 → cuenta habilitada desde el inicio
        );

        $stmt->execute([$nombre, $email, $passwordHash, $telefono]);

        // lastInsertId() devuelve el ID autogenerado por AUTO_INCREMENT
        return (int) $this->pdo->lastInsertId();
    }

    // ─────────────────────────────────────────────────────────
    // BUSCAR POR ID
    // ─────────────────────────────────────────────────────────

    /**
     * Busca un usuario por su ID.
     * Se usa para devolver los datos del perfil sin el hash.
     *
     * @param  int $id
     * @return array|false
     */
    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, email, telefono, rol_id, activo, creado_en
             FROM usuarios
             WHERE id = ?
             LIMIT 1'
            // Nota: NO incluimos password_hash en el SELECT.
            // Nunca enviamos el hash al frontend, aunque esté cifrado.
        );
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function listarSocios(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.nombre, u.email, u.telefono, u.rol_id, u.activo, u.creado_en,
                    r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON u.rol_id = r.id
             WHERE u.rol_id = 1
             ORDER BY u.creado_en DESC'
        );

        return $stmt->fetchAll();
    }

    public function cambiarEstado(int $id, int $activo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET activo = ? WHERE id = ?'
        );

        return $stmt->execute([$activo, $id]);
    }

    public function contarSociosActivos(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND activo = 1'
        );

        return (int) $stmt->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────
    // ACTUALIZAR PERFIL
    // ─────────────────────────────────────────────────────────

    /**
     * Actualiza nombre y teléfono del usuario (RF-05).
     *
     * @param  int    $id
     * @param  string $nombre
     * @param  string|null $telefono
     * @return bool
     */
    public function actualizarPerfil(int $id, string $nombre, ?string $telefono): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?'
        );

        return $stmt->execute([$nombre, $telefono, $id]);
    }
}
