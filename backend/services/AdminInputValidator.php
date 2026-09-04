<?php
/**
 * Servicio AdminInputValidator. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class AdminInputValidator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function requiredString(string $key, string $label, int $max, int $min = 1): string
    {
        $value = trim((string) ($this->data[$key] ?? ''));
        $length = mb_strlen($value);
        if ($length < $min) $this->errors[$key] = "{$label} es obligatorio.";
        elseif ($length > $max) $this->errors[$key] = "{$label} no puede superar {$max} caracteres.";
        return mb_substr($value, 0, $max);
    }

    public function optionalString(string $key, string $label, int $max): ?string
    {
        $value = trim((string) ($this->data[$key] ?? ''));
        if ($value === '') return null;
        if (mb_strlen($value) > $max) $this->errors[$key] = "{$label} no puede superar {$max} caracteres.";
        return mb_substr($value, 0, $max);
    }

    public function email(string $key, string $label = 'El correo'): string
    {
        $value = mb_strtolower(trim((string) ($this->data[$key] ?? '')));
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 150) $this->errors[$key] = "{$label} no es válido.";
        return mb_substr($value, 0, 150);
    }

    public function enum(string $key, array $allowed, string $fallback): string
    {
        $value = (string) ($this->data[$key] ?? $fallback);
        if (!in_array($value, $allowed, true)) {
            $this->errors[$key] = 'Seleccioná una opción válida.';
            return $fallback;
        }
        return $value;
    }

    public function integer(string $key, string $label, int $min, int $max): int
    {
        $value = filter_var($this->data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $this->errors[$key] = "{$label} debe estar entre {$min} y {$max}.";
            return $min;
        }
        return (int) $value;
    }

    public function decimal(string $key, string $label, float $min, float $max): string
    {
        $raw = str_replace(',', '.', trim((string) ($this->data[$key] ?? '')));
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $raw) || (float) $raw < $min || (float) $raw > $max) {
            $this->errors[$key] = "{$label} no es válido.";
            return number_format($min, 2, '.', '');
        }
        return number_format((float) $raw, 2, '.', '');
    }

    public function latitude(string $key): string
    {
        return $this->coordinate($key, 'La latitud', -90, 90);
    }

    public function longitude(string $key): string
    {
        return $this->coordinate($key, 'La longitud', -180, 180);
    }

    public function date(string $key, string $label, bool $required = true): ?string
    {
        $value = trim((string) ($this->data[$key] ?? ''));
        if ($value === '' && !$required) return null;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) $this->errors[$key] = "{$label} no es válida.";
        return $value === '' ? null : $value;
    }

    public function stringList(string $key, int $maxItems = 20, int $maxLength = 80): array
    {
        $raw = $this->data[$key] ?? [];
        if (is_string($raw)) $raw = preg_split('/[,\n]/u', $raw) ?: [];
        if (!is_array($raw)) {
            $this->errors[$key] = 'Usá una lista válida.';
            return [];
        }
        $values = [];
        foreach (array_slice($raw, 0, $maxItems + 1) as $item) {
            $value = trim((string) $item);
            if ($value === '') continue;
            if (mb_strlen($value) > $maxLength) {
                $this->errors[$key] = "Cada elemento puede tener hasta {$maxLength} caracteres.";
                continue;
            }
            $values[mb_strtolower($value)] = $value;
        }
        if (count($values) > $maxItems) $this->errors[$key] = "La lista admite hasta {$maxItems} elementos.";
        return array_values(array_slice($values, 0, $maxItems));
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->data[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function failIfInvalid(): void
    {
        if ($this->errors) ApiResponder::error(422, 'validation_error', 'Revisá los campos indicados.', $this->errors);
    }

    private function coordinate(string $key, string $label, float $min, float $max): string
    {
        $raw = str_replace(',', '.', trim((string) ($this->data[$key] ?? '')));
        if (!is_numeric($raw) || (float) $raw < $min || (float) $raw > $max) {
            $this->errors[$key] = "{$label} no es válida.";
            return '0.0000000';
        }
        return number_format((float) $raw, 7, '.', '');
    }
}
