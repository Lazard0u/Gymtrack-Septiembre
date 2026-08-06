<?php
/**
 * GymTrack · AuthController.php (Controller)
 * ---------------------------------------------------------------
 * Controlador que maneja todo lo relacionado con la autenticación:
 * registro, login y logout.
 *
 * ¿Qué hace un Controller en el patrón MVC?
 *   Recibe la petición HTTP, valida los datos, llama al modelo
 *   para interactuar con la base de datos y devuelve la respuesta JSON.
 *   Es el "director de orquesta" entre la petición y los datos.
 *
 * Endpoints que maneja este controlador:
 *   POST /api/auth/registro  → registrar()
 *   POST /api/auth/login     → login()
 *   POST /api/auth/logout    → logout()
 */

class AuthController
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        // Instanciamos el modelo — él se encarga de hablar con la DB
        $this->usuarioModel = new Usuario();
    }

    // ─────────────────────────────────────────────────────────
    // REGISTRO  →  POST /api/auth/registro
    // Caso de uso CU-01 del documento de requerimientos
    // ─────────────────────────────────────────────────────────

    /**
     * Registra un nuevo socio en el sistema.
     *
     * Flujo:
     *  1. Leer y validar los datos del body JSON
     *  2. Verificar que el email no esté registrado (RF-02)
     *  3. Cifrar la contraseña con BCRYPT
     *  4. Guardar el usuario en la base de datos
     *  5. Devolver respuesta JSON con los datos del nuevo usuario
     */
    public function registrar(): void
    {
        // ── Leer el body JSON ─────────────────────────────────
        // Vue envía los datos como JSON en el body de la petición POST.
        // php://input es el stream de lectura del cuerpo HTTP.
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El cuerpo de la petición no es válido.']);
            return;
        }

        // ── Validación básica ─────────────────────────────────
        // Verificamos que los campos obligatorios estén presentes
        $camposRequeridos = ['nombre', 'email', 'password'];
        foreach ($camposRequeridos as $campo) {
            if (empty($datos[$campo])) {
                $this->responder(400, [
                    'error'   => true,
                    'mensaje' => "El campo '{$campo}' es obligatorio."
                ]);
                return;
            }
        }

        if (!$this->verificarTurnstile($datos['turnstileToken'] ?? '')) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'No se pudo validar la verificación anti robot.'
            ]);
            return;
        }

        // Validamos que el email tenga formato correcto
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'El formato del correo electrónico no es válido.'
            ]);
            return;
        }

        // La contraseña debe tener al menos 8 caracteres (Manual de usuario)
        if (strlen($datos['password']) < 8) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'La contraseña debe tener al menos 8 caracteres.'
            ]);
            return;
        }

        // ── Verificar email único (RF-02) ─────────────────────
        if ($this->usuarioModel->emailExiste($datos['email'])) {
            // 409 Conflict: el recurso ya existe
            $this->responder(409, [
                'error'   => true,
                'mensaje' => 'Ese correo ya está registrado.'
            ]);
            return;
        }

        // ── Cifrar contraseña con BCRYPT ──────────────────────
        // password_hash() genera un hash unidireccional.
        // El factor 'cost' => 12 define cuánto trabajo computacional
        // requiere generar el hash — más alto = más seguro pero más lento.
        // 12 es el estándar recomendado en 2026.
        $passwordHash = password_hash(
            $datos['password'],
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );

        // ── Normalizar y validar el nombre antes de guardar ──────────
        // El escape HTML corresponde a la capa de presentación, no a la DB.
        $nombreSanitizado = trim($datos['nombre']);
        if (mb_strlen($nombreSanitizado) < 2 || mb_strlen($nombreSanitizado) > 100) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El nombre debe tener entre 2 y 100 caracteres.']);
            return;
        }

        // ── Guardar en la base de datos ───────────────────────
        $telefonoOpcional = isset($datos['telefono']) ? trim($datos['telefono']) : null;

        $nuevoId = $this->usuarioModel->crear(
            $nombreSanitizado,
            strtolower(trim($datos['email'])), // emails siempre en minúsculas
            $passwordHash,
            $telefonoOpcional
        );

        // ── Devolver respuesta exitosa ────────────────────────
        // 201 Created: se creó un nuevo recurso en el servidor
        $this->responder(201, [
            'error'   => false,
            'mensaje' => 'Cuenta creada exitosamente.',
            'usuario' => [
                'id'     => $nuevoId,
                'nombre' => $nombreSanitizado,
                'email'  => strtolower(trim($datos['email'])),
                'rol_id' => 1 // socio
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // LOGIN  →  POST /api/auth/login
    // Caso de uso CU-02 del documento de requerimientos
    // ─────────────────────────────────────────────────────────

    /**
     * Inicia sesión de un usuario registrado.
     *
     * Flujo:
     *  1. Leer email y contraseña del body JSON
     *  2. Buscar el usuario por email en la DB
     *  3. Verificar la contraseña con password_verify()
     *  4. Regenerar el ID de sesión (prevenir session fixation)
     *  5. Guardar los datos en $_SESSION
     *  6. Devolver los datos del usuario al frontend
     */
    public function login(): void
    {
        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos)) {
            $this->responder(400, ['error' => true, 'mensaje' => 'El cuerpo de la petición no es válido.']);
            return;
        }

        // ── Validación ────────────────────────────────────────
        if (empty($datos['email']) || empty($datos['password'])) {
            $this->responder(400, [
                'error'   => true,
                'mensaje' => 'Email y contraseña son obligatorios.'
            ]);
            return;
        }

        // ── Buscar usuario por email ──────────────────────────
        $usuario = $this->usuarioModel->buscarPorEmail(
            strtolower(trim($datos['email']))
        );

        // ── Verificar credenciales ────────────────────────────
        // IMPORTANTE: devolvemos el MISMO mensaje de error si el email
        // no existe O si la contraseña es incorrecta. Si dijéramos
        // "email no encontrado", un atacante sabría qué emails están
        // registrados. Esto se llama "enumeración de usuarios".
        if (!$usuario || !$this->verificarPassword($datos['password'], $usuario['password_hash'])) {
            $this->responder(401, [
                'error'   => true,
                'mensaje' => 'Credenciales incorrectas.'
            ]);
            return;
        }

        // Verificamos que la cuenta esté activa
        if (!$usuario['activo']) {
            $this->responder(403, [
                'error'   => true,
                'mensaje' => 'Tu cuenta está deshabilitada. Contactá al administrador.'
            ]);
            return;
        }

        // ── Iniciar sesión segura ─────────────────────────────
        // session_start() activa el sistema de sesiones de PHP
        $this->configurarCookieSesion();
        session_start();

        // session_regenerate_id(true) crea un nuevo ID de sesión
        // y destruye el anterior. Esto previene el ataque "Session Fixation":
        // un atacante no puede pre-setear un ID de sesión y esperar
        // que la víctima lo use para autenticarse.
        session_regenerate_id(true);

        // Guardamos los datos del usuario en la sesión del servidor
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['rol_id']     = $usuario['rol_id'];
        $_SESSION['rol_nombre'] = $usuario['rol_nombre'];
        $_SESSION['is_demo'] = (bool) $usuario['is_demo'];
        $_SESSION['demo_dataset_id'] = $usuario['demo_dataset_id'] === null
            ? null
            : (int) $usuario['demo_dataset_id'];

        // ── Respuesta al frontend ─────────────────────────────
        // NUNCA enviamos el password_hash al frontend
        $this->responder(200, [
            'error'   => false,
            'mensaje' => 'Sesión iniciada correctamente.',
            'usuario' => [
                'id'     => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email'  => $usuario['email'],
                'rol_id' => $usuario['rol_id'],
                'rol_nombre' => $usuario['rol_nombre'],
                'is_demo' => (bool) $usuario['is_demo'],
                'gimnasios' => $this->usuarioModel->contextosGimnasio((int) $usuario['id']),
            ],
            // El session_id se usa como token en las peticiones autenticadas
            'token' => session_id()
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // LOGOUT  →  POST /api/auth/logout
    // ─────────────────────────────────────────────────────────

    /**
     * Cierra la sesión del usuario (RF-04).
     *
     * Destruir la sesión en el servidor es fundamental —
     * no alcanza con que el frontend borre el token local.
     */
    public function logout(): void
    {
        $this->configurarCookieSesion();
        AuthMiddleware::destruirSesionActual();

        $this->responder(200, [
            'error'   => false,
            'mensaje' => 'Sesión cerrada correctamente.'
        ]);
    }

    private function verificarPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    private function verificarTurnstile(string $token): bool
    {
        $habilitado = filter_var(getenv('TURNSTILE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL);
        if (!$habilitado) {
            return true;
        }

        $secret = getenv('TURNSTILE_SECRET_KEY') ?: '';
        if ($secret === '' || $token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $respuesta = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);

        if ($respuesta === false) {
            return false;
        }

        $resultado = json_decode($respuesta, true);

        return (bool)($resultado['success'] ?? false);
    }

    private function configurarCookieSesion(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_name(getenv('SESSION_NAME') ?: 'gymtrack_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOL),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    // ─────────────────────────────────────────────────────────
    // HELPER: responder con JSON
    // ─────────────────────────────────────────────────────────

    /**
     * Método auxiliar que envía la respuesta JSON con el código HTTP correcto.
     *
     * Lo usamos en todos los controladores para no repetir código.
     *
     * @param int   $codigo  Código HTTP (200, 201, 400, 401, 409, 500...)
     * @param array $datos   Los datos a serializar como JSON
     */
    private function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
