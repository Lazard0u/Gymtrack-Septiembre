<?php
/**
 * Servicio GeocodificacionService. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class GeocodificacionService
{
    private PDO $pdo;
    private string $providerUrl;
    private string $userAgent;
    private int $cacheDays;

    public function __construct()
    {
        $this->pdo = Database::conectar();
        $this->providerUrl = trim((string) (getenv('GEOCODING_PROVIDER_URL') ?: 'https://nominatim.openstreetmap.org/search'));
        $this->userAgent = trim((string) (getenv('GEOCODING_USER_AGENT') ?: 'GymTrack/1.0 (contacto@gymtrack.local)'));
        $this->cacheDays = max(1, min(365, (int) (getenv('GEOCODING_CACHE_DAYS') ?: 30)));
    }

    public function search(string $address, string $city, string $country): array
    {
        if (!filter_var(getenv('GEOCODING_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL)) {
            ApiResponder::error(503, 'geocoding_disabled', 'La geocodificación no está disponible. Ingresá las coordenadas o elegí el punto manualmente.');
        }

        $query = implode(', ', array_filter([$address, $city, $country]));
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($query)) ?? trim($query));
        $hash = hash('sha256', $normalized);
        $cached = $this->cached($hash);
        if ($cached !== null) return $cached;

        $provider = parse_url($this->providerUrl);
        if (($provider['scheme'] ?? '') !== 'https' || empty($provider['host'])) {
            throw new RuntimeException('El proveedor de geocodificación debe usar una URL HTTPS válida.');
        }

        $separator = str_contains($this->providerUrl, '?') ? '&' : '?';
        $url = $this->providerUrl . $separator . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 5,
            'addressdetails' => 1,
        ], '', '&', PHP_QUERY_RFC3986);

        $payload = $this->requestProvider($url);
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) throw new RuntimeException('El proveedor de geocodificación devolvió una respuesta inválida.');

        $results = [];
        foreach (array_slice($decoded, 0, 5) as $item) {
            $latitude = filter_var($item['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($item['lon'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) continue;
            $results[] = [
                'latitud' => number_format((float) $latitude, 7, '.', ''),
                'longitud' => number_format((float) $longitude, 7, '.', ''),
                'nombre' => mb_substr(trim((string) ($item['display_name'] ?? $query)), 0, 500),
                'tipo' => mb_substr(trim((string) ($item['type'] ?? 'ubicacion')), 0, 80),
            ];
        }

        $this->store($hash, $normalized, $results);
        return $results;
    }

    private function cached(string $hash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT resultados_json FROM geocodificacion_cache WHERE consulta_hash=? AND expira_en>NOW() LIMIT 1');
        $stmt->execute([$hash]);
        $value = $stmt->fetchColumn();
        if ($value === false) return null;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function store(string $hash, string $query, array $results): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO geocodificacion_cache (consulta_hash,consulta,resultados_json,creado_en,expira_en)
             VALUES (?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? DAY))
             ON DUPLICATE KEY UPDATE consulta=VALUES(consulta),resultados_json=VALUES(resultados_json),creado_en=NOW(),expira_en=VALUES(expira_en)'
        );
        $stmt->execute([$hash, $query, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $this->cacheDays]);
    }

    private function requestProvider(string $url): string
    {
        $lockPath = sys_get_temp_dir() . '/gymtrack-geocodificacion.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('No se pudo coordinar la consulta de ubicación.');

        try {
            rewind($lock);
            $lastRequest = (float) trim((string) stream_get_contents($lock));
            $remaining = 1.05 - (microtime(true) - $lastRequest);
            if ($remaining > 0) usleep((int) ceil($remaining * 1_000_000));

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "User-Agent: {$this->userAgent}\r\nAccept: application/json\r\nAccept-Language: es\r\n",
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $payload = @file_get_contents($url, false, $context);
            ftruncate($lock, 0);
            rewind($lock);
            fwrite($lock, (string) microtime(true));
            fflush($lock);

            $statusLine = $http_response_header[0] ?? '';
            if ($payload === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
                throw new RuntimeException('El servicio de geocodificación no respondió.');
            }
            return $payload;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
