<?php
/**
 * Controlador HTTP NotificacionController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class NotificacionController
{
    public function index(): void
    {
        AuthMiddleware::verificarEmail();
        $result = $this->repository()->inbox(
            AuthMiddleware::obtenerUsuarioId(),
            AuthMiddleware::obtenerGimnasioContextoId(),
            $_GET
        );
        ApiResponder::success(
            ['items' => $result['items'], 'unread_count' => $result['unread_count']],
            200,
            ['pagination' => $result['pagination']]
        );
    }

    public function read(string $id): void
    {
        AuthMiddleware::verificarEmail();
        $notificationId = (int) $id;
        if ($notificationId < 1) {
            ApiResponder::error(404, 'notification_not_found', 'La notificación no existe.');
        }
        $notification = $this->repository()->markRead(
            AuthMiddleware::obtenerUsuarioId(),
            AuthMiddleware::obtenerGimnasioContextoId(),
            $notificationId
        );
        if (!$notification) {
            ApiResponder::error(404, 'notification_not_found', 'La notificación no te pertenece.');
        }
        ApiResponder::success($notification);
    }

    public function readAll(): void
    {
        AuthMiddleware::verificarEmail();
        $count = $this->repository()->markAllRead(
            AuthMiddleware::obtenerUsuarioId(),
            AuthMiddleware::obtenerGimnasioContextoId()
        );
        ApiResponder::success(['updated' => $count]);
    }

    public function preferences(): void
    {
        AuthMiddleware::verificarEmail();
        ApiResponder::success($this->repository()->preferences(AuthMiddleware::obtenerUsuarioId()));
    }

    public function updatePreferences(): void
    {
        AuthMiddleware::verificarEmail();
        $data = $this->preferenceData($this->body());
        if ($data['whatsapp_marketing']) {
            ApiResponder::error(
                409,
                'whatsapp_beta_unavailable',
                'WhatsApp sigue en beta y no tiene un adaptador de envío configurado.'
            );
        }
        $userId = AuthMiddleware::obtenerUsuarioId();
        $result = $this->repository()->updatePreferences($userId, $data);
        SecurityLogger::record(
            'notification_preferences.updated',
            'success',
            $userId,
            ['marketing_consent' => $result['marketing_consent']]
        );
        ApiResponder::success($result);
    }

    public function unsubscribe(): void
    {
        $body = $this->body();
        $token = trim((string) ($body['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            ApiResponder::error(
                422,
                'validation_error',
                'El enlace para dejar de recibir publicidad no es válido.',
                ['token' => 'Token inválido.']
            );
        }
        $this->repository()->unsubscribe($token);
        SecurityLogger::record('marketing.unsubscribed', 'success');
        ApiResponder::success([
            'unsubscribed' => true,
            'message' => 'Dejaste de recibir comunicaciones promocionales. Los avisos transaccionales necesarios siguen activos.',
        ]);
    }

    private function preferenceData(array $body): array
    {
        $keys = [
            'internal_transactional',
            'internal_marketing',
            'email_transactional',
            'email_marketing',
            'whatsapp_marketing',
            'marketing_consent',
        ];
        $data = [];
        $errors = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $body) || !is_bool($body[$key])) {
                $errors[$key] = 'Indicá verdadero o falso.';
                continue;
            }
            $data[$key] = $body[$key];
        }
        if ($errors !== []) {
            ApiResponder::error(422, 'validation_error', 'Revisá las preferencias indicadas.', $errors);
        }
        return $data;
    }

    private function repository(): NotificacionRepository
    {
        return new NotificacionRepository(AuthMiddleware::obtenerDemoDatasetId());
    }

    private function body(): array
    {
        try {
            $decoded = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            ApiResponder::error(400, 'invalid_json', 'El cuerpo de la solicitud no es válido.');
        }
        if (!is_array($decoded)) {
            ApiResponder::error(400, 'invalid_json', 'El cuerpo de la solicitud no es válido.');
        }
        return $decoded;
    }
}
