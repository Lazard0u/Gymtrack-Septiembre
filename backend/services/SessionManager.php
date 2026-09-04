<?php
/**
 * Servicio SessionManager. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class SessionManager
{
    private PDO $pdo;
    private int $idleSeconds;
    private int $absoluteSeconds;

    public function __construct()
    {
        $this->pdo = Database::conectar();
        $this->idleSeconds = max(300, (int) (getenv('SESSION_IDLE_SECONDS') ?: 1800));
        $this->absoluteSeconds = max($this->idleSeconds, (int) (getenv('SESSION_ABSOLUTE_SECONDS') ?: 28800));
    }

    public static function configureCookie(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $production = (getenv('APP_ENV') ?: 'production') === 'production';
        $secure = $production || filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOL);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        session_name((string) (getenv('SESSION_NAME') ?: 'gymtrack_session'));
        session_set_cookie_params([
            'lifetime' => max(1800, (int) (getenv('SESSION_ABSOLUTE_SECONDS') ?: 28800)),
            'path' => (string) (getenv('SESSION_PATH') ?: '/'),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function create(array $user, ?int $gymId = null): array
    {
        self::configureCookie();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->destroy(false, 'reemplazada');
        }
        session_start();
        session_regenerate_id(true);
        $csrf = Security::randomToken();
        $_SESSION = [
            'usuario_id' => (int) $user['id'],
            'is_demo' => (bool) ($user['is_demo'] ?? false),
            'demo_dataset_id' => empty($user['demo_dataset_id']) ? null : (int) $user['demo_dataset_id'],
            'csrf_token' => $csrf,
            'gimnasio_contexto_id' => $gymId,
        ];
        $now = time();
        $publicId = Security::uuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions
             (public_id,session_hash,usuario_id,gimnasio_contexto_id,csrf_hash,creado_en,ultima_actividad_en,expira_inactividad_en,expira_absoluta_en,ip_hash,user_agent,dispositivo)
             VALUES (?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?)'
        );
        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconocido'), 0, 255);
        $stmt->execute([
            $publicId,
            hash('sha256', session_id()),
            (int) $user['id'],
            $gymId,
            hash('sha256', $csrf),
            $now,
            $now,
            $now + $this->idleSeconds,
            $now + $this->absoluteSeconds,
            Security::ipHash(),
            $userAgent,
            $this->deviceLabel($userAgent),
        ]);
        $_SESSION['session_public_id'] = $publicId;
        session_write_close();
        return ['csrf_token' => $csrf, 'session_public_id' => $publicId];
    }

    public function start(bool $required = true): bool
    {
        self::configureCookie();
        $cookieName = session_name();
        if (empty($_COOKIE[$cookieName])) {
            if ($required) $this->deny(401, 'No autenticado. Iniciá sesión para continuar.');
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
            if ($required) $this->deny(401, 'No se pudo validar la sesión.');
            return false;
        }
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($userId < 1) {
            $this->destroy(true, 'invalida');
            if ($required) $this->deny(401, 'Sesión expirada. Iniciá sesión nuevamente.');
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT public_id,usuario_id,gimnasio_contexto_id,csrf_hash,UNIX_TIMESTAMP(expira_inactividad_en) idle_at,
                    UNIX_TIMESTAMP(expira_absoluta_en) absolute_at,revocada_en
             FROM user_sessions WHERE session_hash=? LIMIT 1'
        );
        $stmt->execute([hash('sha256', session_id())]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['usuario_id'] !== $userId || $row['revocada_en'] !== null
            || (int) $row['idle_at'] <= time() || (int) $row['absolute_at'] <= time()) {
            $this->destroy(true, 'expirada');
            if ($required) $this->deny(401, 'Sesión expirada. Iniciá sesión nuevamente.');
            return false;
        }
        $_SESSION['session_public_id'] = $row['public_id'];
        $_SESSION['gimnasio_contexto_id'] = $row['gimnasio_contexto_id'] === null ? null : (int) $row['gimnasio_contexto_id'];
        $this->pdo->prepare('UPDATE user_sessions SET ultima_actividad_en=NOW(), expira_inactividad_en=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE public_id=?')
            ->execute([$this->idleSeconds, $row['public_id']]);
        return true;
    }

    public function verifyCsrf(): void
    {
        $provided = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $expected = (string) ($_SESSION['csrf_token'] ?? '');
        if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
            $this->deny(419, 'La sesión de seguridad venció. Recargá la página e intentá nuevamente.');
        }
    }

    public function rotateContext(?int $gymId): string
    {
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($gymId !== null && !(new AuthorizationService())->canAccessGym($userId, $gymId)) {
            $this->deny(403, 'No tenés acceso a ese gimnasio.');
        }
        $oldHash = hash('sha256', session_id());
        $stmt=$this->pdo->prepare('SELECT UNIX_TIMESTAMP(expira_absoluta_en) FROM user_sessions WHERE session_hash=? AND revocada_en IS NULL LIMIT 1');
        $stmt->execute([$oldHash]);
        $absoluteAt=(int)$stmt->fetchColumn();
        if($absoluteAt<=time()) $this->deny(401,'Sesión expirada. Iniciá sesión nuevamente.');
        $this->pdo->prepare('UPDATE user_sessions SET revocada_en=NOW(),motivo_revocacion="contexto_rotado" WHERE session_hash=?')->execute([$oldHash]);
        session_regenerate_id(true);
        $csrf = Security::randomToken();
        $_SESSION['csrf_token'] = $csrf;
        $_SESSION['gimnasio_contexto_id'] = $gymId;
        $publicId = Security::uuid();
        $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconocido'), 0, 255);
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions (public_id,session_hash,usuario_id,gimnasio_contexto_id,csrf_hash,creado_en,ultima_actividad_en,expira_inactividad_en,expira_absoluta_en,ip_hash,user_agent,dispositivo)
             VALUES (?,?,?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND),FROM_UNIXTIME(?),?,?,?)'
        );
        $stmt->execute([$publicId,hash('sha256',session_id()),$userId,$gymId,hash('sha256',$csrf),$this->idleSeconds,$absoluteAt,Security::ipHash(),$agent,$this->deviceLabel($agent)]);
        $_SESSION['session_public_id'] = $publicId;
        return $csrf;
    }

    public function destroy(bool $clearCookie = true, string $reason = 'logout'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->pdo->prepare('UPDATE user_sessions SET revocada_en=COALESCE(revocada_en,NOW()),motivo_revocacion=? WHERE session_hash=?')
                ->execute([$reason, hash('sha256', session_id())]);
            $_SESSION = [];
            session_destroy();
        }
        if ($clearCookie) {
            self::configureCookie();
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public function revokeAll(int $userId, ?string $exceptPublicId = null, string $reason = 'logout_all'): int
    {
        $sql = 'UPDATE user_sessions SET revocada_en=COALESCE(revocada_en,NOW()),motivo_revocacion=? WHERE usuario_id=? AND revocada_en IS NULL';
        $params = [$reason,$userId];
        if ($exceptPublicId !== null) { $sql .= ' AND public_id<>?'; $params[]=$exceptPublicId; }
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params); return $stmt->rowCount();
    }

    public function listForUser(int $userId, string $currentPublicId): array
    {
        $stmt=$this->pdo->prepare('SELECT public_id,dispositivo,user_agent,creado_en,ultima_actividad_en,expira_absoluta_en FROM user_sessions WHERE usuario_id=? AND revocada_en IS NULL AND expira_absoluta_en>NOW() ORDER BY ultima_actividad_en DESC');
        $stmt->execute([$userId]);
        return array_map(static fn(array $row): array => [
            'id'=>$row['public_id'],'device'=>$row['dispositivo'],'user_agent'=>$row['user_agent'],
            'created_at'=>$row['creado_en'],'last_activity_at'=>$row['ultima_actividad_en'],'expires_at'=>$row['expira_absoluta_en'],
            'current'=>hash_equals($currentPublicId,(string)$row['public_id']),
        ],$stmt->fetchAll());
    }

    public function revokeOne(int $userId, string $publicId, string $currentPublicId): bool
    {
        if (hash_equals($currentPublicId,$publicId)) return false;
        $stmt=$this->pdo->prepare('UPDATE user_sessions SET revocada_en=NOW(),motivo_revocacion="revocada_usuario" WHERE usuario_id=? AND public_id=? AND revocada_en IS NULL');
        $stmt->execute([$userId,$publicId]); return $stmt->rowCount()===1;
    }

    private function deviceLabel(string $agent): string
    {
        $browser = str_contains($agent,'Firefox') ? 'Firefox' : (str_contains($agent,'Edg/') ? 'Edge' : (str_contains($agent,'Chrome') ? 'Chrome' : (str_contains($agent,'Safari') ? 'Safari' : 'Navegador')));
        $os = str_contains($agent,'Android') ? 'Android' : (str_contains($agent,'iPhone') || str_contains($agent,'iPad') ? 'iOS' : (str_contains($agent,'Windows') ? 'Windows' : (str_contains($agent,'Macintosh') ? 'macOS' : (str_contains($agent,'Linux') ? 'Linux' : 'dispositivo desconocido'))));
        return $browser . ' en ' . $os;
    }

    private function deny(int $status, string $message): never
    {
        if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/admin')) {
            ApiResponder::error($status, $status === 419 ? 'csrf_expired' : 'authentication_required', $message);
        }
        if ($status === 419) {
            header('HTTP/1.1 419 Page Expired');
        } else {
            http_response_code($status);
        }
        echo json_encode(['error'=>true,'mensaje'=>$message],JSON_UNESCAPED_UNICODE);
        exit;
    }
}
