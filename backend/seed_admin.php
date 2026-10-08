<?php
/**
 * GymTrack · seed_admin.php
 * ---------------------------------------------------------------
 * Script útil para crear la cuenta admin si la base de datos ya está
 * inicializada pero no tiene la fila de administrador.
 *
 * Ejecutar desde backend/: php seed_admin.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/config/Database.php';

try {
    $pdo = Database::conectar();

    $email = strtolower(trim(getenv('ADMIN_EMAIL') ?: ''));
    $password = getenv('ADMIN_INITIAL_PASSWORD') ?: '';
    $nombre = trim(getenv('ADMIN_NAME') ?: 'Administrador GymTrack');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        fwrite(STDERR, "Definí ADMIN_EMAIL y ADMIN_INITIAL_PASSWORD (mínimo 12 caracteres).\n");
        exit(1);
    }

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email_normalizado = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuarioId = $stmt->fetchColumn();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
    $roleStmt->execute(['admin_general']);
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException('El rol admin_general no está configurado.');
    }
    if ($usuarioId) {
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET nombre = ?, email_normalizado = ?, email_verificado_en = COALESCE(email_verificado_en, NOW()),
             password_hash = ?, debe_cambiar_password = 0, rol_id = ?, activo = 1 WHERE id = ?'
        );
        $stmt->execute([$nombre, $email, $passwordHash, (int) $roleId, (int) $usuarioId]);
        $pdo->prepare('UPDATE user_sessions SET revocada_en=COALESCE(revocada_en,NOW()), motivo_revocacion="credential_rotation" WHERE usuario_id=?')->execute([(int)$usuarioId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, email_normalizado, email_verificado_en, password_hash, telefono, rol_id, activo)
             VALUES (?, "", ?, ?, NOW(), ?, NULL, ?, 1)'
        );
        $stmt->execute([$nombre, $email, $email, $passwordHash, (int) $roleId]);
    }

    echo "Cuenta administradora creada o actualizada correctamente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear la cuenta administradora.\n");
    exit(1);
}
