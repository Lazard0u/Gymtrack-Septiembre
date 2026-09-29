<?php
/**
 * Controlador HTTP GeocodificacionController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class GeocodificacionController
{
    public function search(): void
    {
        AuthMiddleware::verificarRoles(['dueño', 'admin_general']);
        AuthMiddleware::verificarPermiso('gym.configure');

        $validator = new AdminInputValidator($_GET);
        $address = $validator->requiredString('direccion', 'La dirección', 180, 5);
        $city = $validator->requiredString('ciudad', 'La ciudad o localidad', 100, 2);
        $country = $validator->requiredString('pais', 'El país', 100, 2);
        $validator->failIfInvalid();

        (new RateLimiter())->ensureAllowed('admin_geocoding', 'user:' . AuthMiddleware::obtenerUsuarioId());

        try {
            $results = (new GeocodificacionService())->search($address, $city, $country);
        } catch (RuntimeException $error) {
            error_log('[GymTrack geocodificación] ' . $error->getMessage());
            ApiResponder::error(502, 'geocoding_unavailable', 'No pudimos ubicar la dirección. Ingresá las coordenadas o elegí el punto manualmente.');
        }

        ApiResponder::success(['items' => $results], 200, [
            'attribution' => 'Datos © colaboradores de OpenStreetMap',
            'policy' => 'Consulta manual con caché del servidor.',
        ]);
    }
}
