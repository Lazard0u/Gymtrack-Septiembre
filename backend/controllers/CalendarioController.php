<?php
/**
 * Controlador HTTP CalendarioController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class CalendarioController
{
    public function show(string $id): void
    {
        $booking = $this->booking((int) $id);
        ApiResponder::success((new IcsCalendarioService())->payload($booking));
    }

    public function download(string $id): void
    {
        $booking = $this->booking((int) $id);
        $contents = (new IcsCalendarioService())->render($booking);
        $filename = 'gymtrack-reserva-' . (int) $booking['booking_id'] . '.ics';
        header('Content-Type: text/calendar; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($contents));
        header('Cache-Control: private, no-store');
        echo $contents;
        exit;
    }

    private function booking(int $bookingId): array
    {
        AuthMiddleware::verificarRol('socio');
        AuthMiddleware::verificarPermiso('reservations.read');
        if ($bookingId < 1) ApiResponder::error(404, 'booking_not_found', 'La reserva no existe.');
        $userId = AuthMiddleware::obtenerUsuarioId();
        $gymId = AuthMiddleware::requerirContextoGimnasio();
        $datasetId = AuthMiddleware::obtenerDemoDatasetId();
        $stmt = Database::conectar()->prepare(
            'SELECT r.id booking_id,r.estado booking_estado,s.id sesion_clase_id,s.inicio_en,s.fin_en,s.zona_horaria,s.estado sesion_estado,s.version,
                    c.nombre,c.descripcion,CONCAT_WS(" ",u.nombre,u.apellido) instructor_nombre,g.nombre gimnasio_nombre,
                    gs.nombre sede_nombre,COALESCE(gs.direccion,g.direccion) direccion,COALESCE(gs.ciudad,g.ciudad) ciudad,
                    COALESCE(gs.departamento,g.departamento) departamento
             FROM reservas r
             JOIN sesiones_clase s ON s.id=r.sesion_clase_id
             JOIN clases c ON c.id=s.clase_id
             JOIN usuarios u ON u.id=s.instructor_id
             JOIN gimnasios g ON g.id=s.gimnasio_id
             LEFT JOIN gimnasio_sedes gs ON gs.id=s.sede_id
             WHERE r.id=? AND r.usuario_id=? AND s.gimnasio_id=?
               AND r.is_demo=? AND (r.demo_dataset_id <=> ?)
               AND s.is_demo=? AND (s.demo_dataset_id <=> ?)
               AND g.is_demo=? AND (g.demo_dataset_id <=> ?)
             LIMIT 1'
        );
        $demoFlag = $datasetId === null ? 0 : 1;
        $stmt->execute([
            $bookingId,
            $userId,
            $gymId,
            $demoFlag,
            $datasetId,
            $demoFlag,
            $datasetId,
            $demoFlag,
            $datasetId,
        ]);
        $booking = $stmt->fetch();
        if (!$booking) ApiResponder::error(404, 'booking_not_found', 'La reserva no te pertenece o no existe en el gimnasio activo.');
        if ($booking['booking_estado'] !== 'confirmada' || $booking['sesion_estado'] !== 'programada') {
            ApiResponder::error(409, 'calendar_booking_unavailable', 'Sólo una reserva confirmada de una sesión programada se puede agregar al calendario.');
        }
        return $booking;
    }
}
