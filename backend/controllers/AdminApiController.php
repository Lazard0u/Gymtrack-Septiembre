<?php
/**
 * Controlador HTTP AdminApiController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AdminApiController
{
    private const ROLES = ['empleado', 'dueño', 'admin_general'];

    public function context(): void
    {
        AuthMiddleware::verificarRoles(self::ROLES);
        $userId = AuthMiddleware::obtenerUsuarioId();
        $activeGymId = AuthMiddleware::obtenerGimnasioContextoId();
        $authz = new AuthorizationService();
        ApiResponder::success([
            'active_gym_id' => $activeGymId,
            'gyms' => $authz->gymAssignments($userId),
            'effective_role' => $authz->effectiveRole($userId, $activeGymId),
            'global_role' => $authz->globalRole($userId),
            'permissions' => $authz->permissions($userId, $activeGymId),
            'support_mode' => $authz->globalRole($userId) === AuthorizationService::ADMIN && $activeGymId !== null,
            'support_reason' => $authz->globalRole($userId) === AuthorizationService::ADMIN ? ($_SESSION['admin_support_reason'] ?? null) : null,
            'is_demo' => AuthMiddleware::esCuentaDemo(),
        ]);
    }

    public function selectContext(): void
    {
        AuthMiddleware::verificarRoles(self::ROLES);
        $data = $this->body();
        $gymId = (int) ($data['gym_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($gymId < 1) ApiResponder::error(422, 'validation_error', 'Seleccioná un gimnasio válido.', ['gym_id' => 'El gimnasio es obligatorio.']);
        $userId = AuthMiddleware::obtenerUsuarioId();
        $authz = new AuthorizationService();
        if (!$authz->canAccessGym($userId, $gymId)) {
            AdminAuditLogger::record('admin.context.selected', 'gimnasio', 'denied', $userId, $gymId, (string) $gymId, 'Acceso fuera de alcance.');
            ApiResponder::error(403, 'forbidden', 'No tenés acceso a ese gimnasio.');
        }
        $supportMode = $authz->globalRole($userId) === AuthorizationService::ADMIN;
        if ($supportMode && mb_strlen($reason) < 8) {
            ApiResponder::error(422, 'support_reason_required', 'Indicá el motivo de soporte antes de entrar al gimnasio.', ['reason' => 'Escribí al menos 8 caracteres.']);
        }
        $before = AuthMiddleware::obtenerGimnasioContextoId();
        $csrf = (new SessionManager())->rotateContext($gymId);
        if ($supportMode) $_SESSION['admin_support_reason'] = mb_substr($reason, 0, 255);
        else unset($_SESSION['admin_support_reason']);
        AdminAuditLogger::record('admin.context.selected', 'gimnasio', 'success', $userId, $gymId, (string) $gymId, $supportMode ? $reason : null, ['gym_id' => $before], ['gym_id' => $gymId]);
        ApiResponder::success([
            'csrf_token' => $csrf,
            'usuario' => (new UserContextService())->payload($userId),
            'support_mode' => $supportMode,
            'support_reason' => $supportMode ? $reason : null,
        ]);
    }

    public function permissions(): void
    {
        [$userId, $gymId] = $this->authorize(null);
        $authz = new AuthorizationService();
        ApiResponder::success([
            'role' => $authz->effectiveRole($userId, $gymId),
            'permissions' => $authz->permissions($userId, $gymId),
            'gym_id' => $gymId,
        ]);
    }

    public function summary(): void
    {
        [, $gymId, $repo] = $this->authorize('members.read');
        ApiResponder::success($repo->summary($gymId));
    }

    public function activity(): void
    {
        [, $gymId, $repo] = $this->authorize('gym.configure');
        $this->paged($repo->activity($gymId, AdminRepository::query($_GET)));
    }

    public function members(): void
    {
        [, $gymId, $repo] = $this->authorize('members.read');
        $this->paged($repo->members($gymId, AdminRepository::query($_GET)));
    }

    public function staff(): void
    {
        [, $gymId, $repo] = $this->authorize('staff.manage');
        $this->paged($repo->staff($gymId, AdminRepository::query($_GET)));
    }

    public function classes(): void
    {
        [, $gymId, $repo] = $this->authorize('classes.read');
        $this->paged($repo->classes($gymId, AdminRepository::query($_GET)));
    }

    public function reservations(): void
    {
        [, $gymId, $repo] = $this->authorize('reservations.read');
        $this->paged($repo->reservations($gymId, AdminRepository::query($_GET)));
    }

    public function memberships(): void
    {
        [, $gymId, $repo] = $this->authorize('memberships.read');
        $this->paged($repo->memberships($gymId, AdminRepository::query($_GET)));
    }

    public function payments(): void
    {
        [, $gymId, $repo] = $this->authorize('payments.read');
        $this->paged($repo->payments($gymId, AdminRepository::query($_GET)));
    }

    public function exports(): void
    {
        [, $gymId, $repo] = $this->authorize('reports.export');
        $this->paged($repo->exports($gymId, AdminRepository::query($_GET)));
    }

    private function authorize(?string $permission): array
    {
        AuthMiddleware::verificarRoles(self::ROLES);
        if ($permission !== null) AuthMiddleware::verificarPermiso($permission);
        $gymId = AuthMiddleware::requerirContextoGimnasio();
        $repo = new AdminRepository(AuthMiddleware::obtenerDemoDatasetId());
        if (!$repo->gym($gymId)) ApiResponder::error(403, 'gym_scope_mismatch', 'El gimnasio activo no pertenece al conjunto de datos de tu sesión.');
        return [AuthMiddleware::obtenerUsuarioId(), $gymId, $repo];
    }

    private function paged(array $result): never
    {
        ApiResponder::success(['items' => $result['items']], 200, ['pagination' => $result['pagination']]);
    }

    private function body(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($decoded)) ApiResponder::error(400, 'invalid_json', 'El cuerpo de la solicitud no es válido.');
        return $decoded;
    }
}
