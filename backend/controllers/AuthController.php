<?php

declare(strict_types=1);

/**
 * Orquesta registro, ingreso, verificación y contraseñas.
 *
 * El controlador valida HTTP y coordina servicios. Usuario ejecuta consultas,
 * TokenService protege secretos, MailService entrega mensajes y
 * SessionManager crea cookies seguras. Ninguna decisión de seguridad depende
 * exclusivamente de Vue: todas vuelven a comprobarse en PHP.
 */
final class AuthController
{
    private Usuario $users;
    private PDO $pdo;

    public function __construct()
    {
        $this->users = new Usuario();
        $this->pdo = Database::conectar();
    }

    /** Registra una cuenta de socio común. */
    public function registrar(): void
    {
        $this->register(false);
    }

    /** Registra un socio y además crea una solicitud de acceso como dueño. */
    public function registrarDueno(): void
    {
        $this->register(true);
    }

    /** Valida credenciales y crea una sesión respaldada por una cookie HttpOnly. */
    public function login(): void
    {
        $data = $this->body();
        $email = Security::normalizeEmail((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->respond(400, ['error' => true, 'mensaje' => 'Correo electrónico y contraseña son obligatorios.']);
            return;
        }

        $limit = new RateLimiter();
        $limit->ensureAllowed('login', $email);
        // esto además limita por cuenta: sin esto, alcanza con cambiar de
        // IP para seguir probando contraseñas sin que nada te frene
        $limit->ensureAllowed('login', $email, true);
        $user = $this->users->buscarPorEmail($email);

        // Se usa la misma respuesta para usuario inexistente y clave incorrecta.
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $limit->fail('login', $email);
            $limit->fail('login', $email, true);
            SecurityLogger::record('auth.login', 'failed', is_array($user) ? (int) $user['id'] : null);
            usleep(random_int(80000, 160000));
            $this->respond(401, ['error' => true, 'mensaje' => 'Credenciales incorrectas.']);
            return;
        }
        if (!(bool) $user['activo']) {
            $limit->fail('login', $email);
            $limit->fail('login', $email, true);
            SecurityLogger::record('auth.login', 'disabled', (int) $user['id']);
            $this->respond(401, ['error' => true, 'mensaje' => 'Credenciales incorrectas.']);
            return;
        }

        $limit->clear('login', $email);
        $limit->clear('login', $email, true);
        // El hash se actualiza de forma transparente si cambia el costo recomendado.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->users->rehashPassword((int) $user['id'], $hash);
        }

        $assignments = (new AuthorizationService())->gymAssignments((int) $user['id']);
        $gymId = $user['rol_nombre'] === AuthorizationService::ADMIN
            ? null
            : ($assignments[0]['gimnasio_id'] ?? null);
        $session = (new SessionManager())->create($user, $gymId);
        SessionManager::configureCookie();
        session_start();
        $payload = (new UserContextService())->payload((int) $user['id']);
        session_write_close();

        SecurityLogger::record('auth.login', 'success', (int) $user['id']);
        $this->respond(200, [
            'error' => false,
            'mensaje' => 'Sesión iniciada correctamente.',
            'usuario' => $payload,
            'csrf_token' => $session['csrf_token'],
        ]);
    }

    /** Revoca la sesión actual después de comprobar CSRF. */
    public function logout(): void
    {
        $manager = new SessionManager();
        if ($manager->start(false)) {
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            $manager->verifyCsrf();
            $manager->destroy(true, 'logout');
            SecurityLogger::record('auth.logout', 'success', $userId);
        }
        $this->respond(200, ['error' => false, 'mensaje' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Reenvía la verificación sin revelar si el correo existe o ya fue verificado.
     */
    public function resendEmail(): void
    {
        $data = $this->body();
        $email = Security::normalizeEmail((string) ($data['email'] ?? ''));
        $message = 'Si la cuenta existe y aún necesita verificación, recibirás un enlace.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(202, ['error' => false, 'mensaje' => $message]);
            return;
        }

        $limit = new RateLimiter();
        $limit->ensureAllowed('email_resend', $email);
        // Cada solicitud cuenta, aunque la dirección no exista, para evitar enumeración.
        $limit->fail('email_resend', $email);
        $user = $this->users->buscarPorEmail($email);
        if ($user && $user['email_verificado_en'] === null) {
            $sent = $this->deliverVerificationEmail(
                (int) $user['id'],
                (string) $user['email'],
                (string) $user['nombre']
            );
            SecurityLogger::record('auth.email_resend', $sent ? 'accepted' : 'delivery_failed', (int) $user['id']);
        }

        // La respuesta deliberadamente no cambia según el estado de la cuenta.
        $this->respond(202, ['error' => false, 'mensaje' => $message]);
    }

    /** Consume el enlace y devuelve un estado preciso para la pantalla pública. */
    public function verifyEmail(): void
    {
        $data = $this->body();
        $result = (new TokenService())->verifyEmail((string) ($data['token'] ?? ''));
        $userId = $result['user_id'];

        if ($result['status'] === 'verified') {
            SecurityLogger::record('auth.email_verified', 'success', $userId);
            $this->respond(200, [
                'error' => false,
                'codigo' => 'email_verified',
                'mensaje' => 'Correo verificado correctamente. Ya podés continuar.',
            ]);
            return;
        }
        if ($result['status'] === 'already_verified') {
            SecurityLogger::record('auth.email_verified', 'already_verified', $userId);
            $this->respond(200, [
                'error' => false,
                'codigo' => 'email_already_verified',
                'mensaje' => 'Tu correo ya estaba verificado. Podés continuar con normalidad.',
            ]);
            return;
        }
        if ($result['status'] === 'expired') {
            $this->respond(410, [
                'error' => true,
                'codigo' => 'email_verification_expired',
                'mensaje' => 'El enlace venció. Solicitá una verificación nueva.',
            ]);
            return;
        }
        if ($result['status'] === 'used') {
            $this->respond(409, [
                'error' => true,
                'codigo' => 'email_verification_used',
                'mensaje' => 'Este enlace ya fue utilizado. Si todavía no podés entrar, solicitá uno nuevo.',
            ]);
            return;
        }

        $this->respond(400, [
            'error' => true,
            'codigo' => 'email_verification_invalid',
            'mensaje' => 'El enlace de verificación no es válido. Revisá que esté completo o solicitá uno nuevo.',
        ]);
    }

    /** Solicita recuperación con una respuesta neutral para cualquier dirección. */
    public function forgotPassword(): void
    {
        $data = $this->body();
        $email = Security::normalizeEmail((string) ($data['email'] ?? ''));
        $message = 'Si la cuenta existe, recibirás instrucciones para restablecer la contraseña.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(202, ['error' => false, 'mensaje' => $message]);
            return;
        }

        $limit = new RateLimiter();
        $limit->ensureAllowed('password_forgot', $email);
        $limit->fail('password_forgot', $email);
        $user = $this->users->buscarPorEmail($email);
        if ($user && (bool) $user['activo']) {
            $token = (new TokenService())->issuePasswordReset((int) $user['id']);
            $this->sendPlainLink(
                $email,
                'Restablecé tu contraseña de GymTrack',
                '/restablecer/' . rawurlencode($token),
                'El enlace vence en una hora.'
            );
            SecurityLogger::record('auth.password_forgot', 'accepted', (int) $user['id']);
        }
        $this->respond(202, ['error' => false, 'mensaje' => $message]);
    }

    /** Valida el token, aplica la nueva clave y cierra sesiones anteriores. */
    public function resetPassword(): void
    {
        $data = $this->body();
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        $subject = hash('sha256', $token);
        $limit = new RateLimiter();
        $limit->ensureAllowed('password_reset', $subject);

        if ($password !== $confirmation) {
            $limit->fail('password_reset', $subject);
            $this->respond(422, ['error' => true, 'mensaje' => 'Las contraseñas no coinciden.']);
            return;
        }
        if ($error = Security::passwordError($password)) {
            $limit->fail('password_reset', $subject);
            $this->respond(422, ['error' => true, 'mensaje' => $error]);
            return;
        }

        $userId = (new TokenService())->consume('password_reset_tokens', $token);
        if ($userId === null) {
            $limit->fail('password_reset', $subject);
            $this->respond(400, ['error' => true, 'mensaje' => 'El enlace no es válido o venció. Solicitá uno nuevo.']);
            return;
        }

        $this->users->updatePassword($userId, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        (new SessionManager())->revokeAll($userId, null, 'password_reset');
        $limit->clear('password_reset', $subject);
        SecurityLogger::record('auth.password_reset', 'success', $userId);
        $this->respond(200, ['error' => false, 'mensaje' => 'Contraseña actualizada. Iniciá sesión nuevamente.']);
    }

    /** Cambia la clave desde una sesión autenticada y puede revocar otros equipos. */
    public function changePassword(): void
    {
        AuthMiddleware::verificarSesion();
        $data = $this->body();
        $userId = AuthMiddleware::obtenerUsuarioId();
        $summary = $this->users->buscarPorId($userId);
        $user = $summary ? $this->users->buscarPorEmail((string) $summary['email']) : false;
        if (!$user) {
            $this->respond(401, ['error' => true, 'mensaje' => 'La sesión ya no corresponde a una cuenta activa.']);
            return;
        }

        $current = (string) ($data['current_password'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        $limit = new RateLimiter();
        $subject = (string) $user['email_normalizado'];
        $limit->ensureAllowed('password_change', $subject);

        if (!password_verify($current, (string) $user['password_hash'])) {
            $limit->fail('password_change', $subject);
            $this->respond(422, ['error' => true, 'mensaje' => 'La contraseña actual no es correcta.']);
            return;
        }
        if ($password !== $confirmation) {
            $this->respond(422, ['error' => true, 'mensaje' => 'Las contraseñas nuevas no coinciden.']);
            return;
        }
        if ($error = Security::passwordError($password, (string) $user['email'])) {
            $this->respond(422, ['error' => true, 'mensaje' => $error]);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->users->updatePassword($userId, $hash);
        $limit->clear('password_change', $subject);
        if ((bool) ($data['logout_other_sessions'] ?? true)) {
            (new SessionManager())->revokeAll(
                $userId,
                (string) ($_SESSION['session_public_id'] ?? ''),
                'password_changed'
            );
        }
        SecurityLogger::record('auth.password_changed', 'success', $userId);
        $this->respond(200, ['error' => false, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * Flujo compartido por socio y solicitud de dueño.
     *
     * Los consentimientos y la cuenta se insertan juntos. El correo se envía
     * después del commit porque un proveedor externo no debe quedar dentro de
     * una transacción MySQL larga.
     */
    private function register(bool $owner): void
    {
        $data = $this->body();
        $email = Security::normalizeEmail((string) ($data['email'] ?? ''));
        $rateAction = $owner ? 'owner_register' : 'register';
        $limit = new RateLimiter();
        $limit->ensureAllowed($rateAction, $email ?: 'invalid');

        $first = trim((string) ($data['nombre'] ?? ''));
        $last = trim((string) ($data['apellido'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        if (!(new TurnstileService())->verify((string) ($data['turnstileToken'] ?? ''))) {
            $limit->fail($rateAction, $email ?: 'invalid');
            $this->respond(400, ['error' => true, 'mensaje' => 'No se pudo validar la verificación anti robot.']);
            return;
        }

        $errors = [];
        if (mb_strlen($first) < 2 || mb_strlen($first) > 100) {
            $errors['nombre'] = 'Ingresá un nombre de 2 a 100 caracteres.';
        }
        if (mb_strlen($last) < 2 || mb_strlen($last) > 100) {
            $errors['apellido'] = 'Ingresá un apellido de 2 a 100 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ingresá un correo electrónico válido.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Las contraseñas no coinciden.';
        }
        if ($error = Security::passwordError($password, $email)) {
            $errors['password'] = $error;
        }
        if (($data['terms_accepted'] ?? false) !== true) {
            $errors['terms_accepted'] = 'Debés aceptar los términos.';
        }
        if (($data['privacy_accepted'] ?? false) !== true) {
            $errors['privacy_accepted'] = 'Debés aceptar la política de privacidad.';
        }
        if ($owner && mb_strlen(trim((string) ($data['gym_name'] ?? ''))) < 2) {
            $errors['gym_name'] = 'Ingresá el nombre del gimnasio.';
        }
        if ($errors !== []) {
            $limit->fail($rateAction, $email ?: 'invalid');
            $this->respond(422, [
                'error' => true,
                'mensaje' => 'Revisá los campos marcados.',
                'fields' => $errors,
            ]);
            return;
        }
        if ($this->users->emailExiste($email)) {
            $limit->fail($rateAction, $email);
            $this->respond(409, ['error' => true, 'mensaje' => 'No se pudo crear la cuenta con esos datos.']);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $userId = $this->users->crear(
                $first,
                $email,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                null,
                $last,
                true,
                true,
                ($data['marketing_accepted'] ?? false) === true ? new DateTimeImmutable() : null
            );

            foreach (['terminos', 'privacidad'] as $type) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO user_consents(usuario_id,tipo,version_documento,aceptado_en,ip_hash)
                     VALUES(?,?,?,NOW(),?)'
                );
                $stmt->execute([$userId, $type, '2026-08-06', Security::ipHash()]);
            }
            if (($data['marketing_accepted'] ?? false) === true) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO user_consents(usuario_id,tipo,version_documento,aceptado_en,ip_hash)
                     VALUES(?,"marketing",?,NOW(),?)'
                );
                $stmt->execute([$userId, '2026-08-06', Security::ipHash()]);
            }
            if ($owner) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO owner_registration_requests(usuario_id,gimnasio_nombre,mensaje)
                     VALUES(?,?,?)'
                );
                $stmt->execute([
                    $userId,
                    trim((string) $data['gym_name']),
                    mb_substr(trim((string) ($data['message'] ?? '')), 0, 500),
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        $mailSent = $this->deliverVerificationEmail($userId, $email, $first);
        $limit->clear($rateAction, $email);
        SecurityLogger::record($owner ? 'auth.owner_requested' : 'auth.register', 'success', $userId);
        if (!$mailSent) {
            $this->respond(503, [
                'error' => true,
                'codigo' => 'verification_email_failed',
                'mensaje' => 'La cuenta quedó creada, pero no pudimos enviar el correo. Intentá reenviarlo en unos minutos.',
                'email' => $email,
            ]);
            return;
        }

        $this->respond(201, [
            'error' => false,
            'mensaje' => $owner
                ? 'Solicitud recibida. Verificá tu correo; no se asignaron permisos de dueño.'
                : 'Cuenta creada. Revisá tu correo para verificarla.',
            'email' => $email,
        ]);
    }

    /**
     * Genera, entrega y finalmente activa un enlace de verificación.
     * Si el proveedor falla, descarta sólo el token nuevo y conserva el anterior.
     */
    private function deliverVerificationEmail(int $userId, string $email, string $firstName): bool
    {
        $tokens = new TokenService();
        $token = '';
        try {
            $token = $tokens->issueEmailVerification($userId);
            // El fragmento #token nunca se envía en access logs ni cabeceras Referer.
            $url = EmailTemplate::frontendUrl() . '/verificar-email#token=' . rawurlencode($token);
            $message = EmailTemplate::emailVerification(
                $firstName,
                $url,
                $tokens->emailVerificationTtlSeconds()
            );
            (new MailService())->send($email, $message['subject'], $message['text'], $message['html']);
            $tokens->confirmEmailDelivery($userId, $token);
            return true;
        } catch (Throwable $error) {
            if ($token !== '') {
                try {
                    $tokens->discardEmailVerification($token);
                } catch (Throwable) {
                    // La excepción original contiene la causa útil para operaciones.
                }
            }
            // Nunca se escribe el correo, la URL ni el token en el log operativo.
            error_log('[GymTrack mail] Falló la entrega de verificación: ' . $error->getMessage());
            return false;
        }
    }

    /** Envía enlaces transaccionales simples, como recuperación de contraseña. */
    private function sendPlainLink(string $email, string $subject, string $path, string $expiry): bool
    {
        try {
            $url = EmailTemplate::frontendUrl() . $path;
            $body = "Abrí este enlace para continuar:\n\n{$url}\n\n{$expiry}\n\nSi no solicitaste esta acción, ignorá el mensaje.";
            (new MailService())->send($email, $subject, $body);
            return true;
        } catch (Throwable $error) {
            error_log('[GymTrack mail] Falló una entrega transaccional: ' . $error->getMessage());
            return false;
        }
    }

    /** Decodifica JSON; una carga inválida se trata como un objeto vacío. */
    private function body(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    /** Mantiene el contrato histórico de respuestas usado por el frontend. */
    private function respond(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
