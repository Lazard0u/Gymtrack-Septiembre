<?php
/**
 * GymTrack · seed_admin.php
 * ---------------------------------------------------------------
 * Script útil para crear la cuenta admin si la base de datos ya está
 * inicializada pero no tiene la fila de administrador.
 *
 * Ejecutar desde backend/: php seed_admin.php
 */

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

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuarioId = $stmt->fetchColumn();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    if ($usuarioId) {
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET nombre = ?, password_hash = ?, rol_id = 2, activo = 1 WHERE id = ?'
        );
        $stmt->execute([$nombre, $passwordHash, (int) $usuarioId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol_id, activo)
             VALUES (?, ?, ?, NULL, 2, 1)'
        );
        $stmt->execute([$nombre, $email, $passwordHash]);
    }

    echo "Cuenta administradora creada o actualizada correctamente.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "No se pudo crear la cuenta administradora.\n");
    exit(1);
}
