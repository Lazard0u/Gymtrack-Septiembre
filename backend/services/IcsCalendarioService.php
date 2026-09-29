<?php
/**
 * Servicio IcsCalendarioService. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class IcsCalendarioService
{
    public function payload(array $booking): array
    {
        $zone = $this->timezone((string) ($booking['zona_horaria'] ?? ''));
        $start = new DateTimeImmutable((string) $booking['inicio_en'], $zone);
        $end = new DateTimeImmutable((string) $booking['fin_en'], $zone);
        $location = implode(', ', array_values(array_filter([
            (string) ($booking['sede_nombre'] ?? ''),
            (string) ($booking['direccion'] ?? ''),
            (string) ($booking['ciudad'] ?? ''),
            (string) ($booking['departamento'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== '')));
        $description = trim(implode("\n", array_filter([
            (string) ($booking['descripcion'] ?? ''),
            !empty($booking['instructor_nombre']) ? 'Entrenador: ' . $booking['instructor_nombre'] : null,
            !empty($booking['gimnasio_nombre']) ? 'Gimnasio: ' . $booking['gimnasio_nombre'] : null,
        ])));
        $uid = sprintf('gymtrack-booking-%d-session-%d@gymtrack.local', (int) $booking['booking_id'], (int) $booking['sesion_clase_id']);
        $google = http_build_query([
            'action' => 'TEMPLATE',
            'text' => (string) $booking['nombre'],
            'dates' => $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . '/' . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'details' => $description,
            'location' => $location,
            'ctz' => $zone->getName(),
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'booking_id' => (int) $booking['booking_id'],
            'session_id' => (int) $booking['sesion_clase_id'],
            'uid' => $uid,
            'title' => (string) $booking['nombre'],
            'description' => $description,
            'location' => $location,
            'trainer' => (string) ($booking['instructor_nombre'] ?? ''),
            'start_at' => $start->format(DATE_ATOM),
            'end_at' => $end->format(DATE_ATOM),
            'timezone' => $zone->getName(),
            'google_calendar_url' => 'https://calendar.google.com/calendar/render?' . $google,
            'ics_url' => '/api/bookings/' . (int) $booking['booking_id'] . '/calendar.ics',
            'automatic_sync' => [
                'enabled' => false,
                'status' => $this->flag('FEATURE_GOOGLE_CALENDAR_SYNC') ? 'beta_unavailable' : 'disabled',
                'message' => 'La exportación manual funciona. OAuth y la sincronización automática todavía no están disponibles.',
            ],
        ];
    }

    public function render(array $booking): string
    {
        $payload = $this->payload($booking);
        $zone = new DateTimeZone($payload['timezone']);
        $start = new DateTimeImmutable((string) $booking['inicio_en'], $zone);
        $end = new DateTimeImmutable((string) $booking['fin_en'], $zone);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GymTrack//Calendar 1.0//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:GymTrack',
            'X-WR-TIMEZONE:' . $this->escape($payload['timezone']),
            'BEGIN:VEVENT',
            'UID:' . $this->escape($payload['uid']),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART;TZID=' . $this->escape($payload['timezone']) . ':' . $start->format('Ymd\THis'),
            'DTEND;TZID=' . $this->escape($payload['timezone']) . ':' . $end->format('Ymd\THis'),
            'SEQUENCE:' . max(0, (int) ($booking['version'] ?? 1)),
            'SUMMARY:' . $this->escape($payload['title']),
            'DESCRIPTION:' . $this->escape($payload['description']),
            'LOCATION:' . $this->escape($payload['location']),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    private function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name !== '' ? $name : 'America/Montevideo');
        } catch (Throwable) {
            return new DateTimeZone('America/Montevideo');
        }
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\r", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $value);
    }

    private function fold(string $line): string
    {
        $result = [];
        $current = '';
        $limit = 75;
        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            $characters = str_split($line);
        }
        foreach ($characters as $character) {
            if ($current !== '' && strlen($current . $character) > $limit) {
                $result[] = $current;
                $current = $character;
                $limit = 74;
                continue;
            }
            $current .= $character;
        }
        $result[] = $current;
        return implode("\r\n ", $result);
    }

    private function flag(string $name): bool
    {
        return filter_var(getenv($name) ?: 'false', FILTER_VALIDATE_BOOL);
    }
}
