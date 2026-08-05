<?php
/**
 * GymTrack · seed_user.php
 * ---------------------------------------------------------------
 * Crea un usuario común de ejemplo para pruebas.
 * Ejecutar desde backend/: php seed_user.php
 */

require_once __DIR__ . '/config/Database.php';

try {
    if ((getenv('APP_ENV') ?: 'production') === 'production') {
        fwrite(STDERR, "El seed de usuario demo no se ejecuta en producción.\n");
        exit(1);
    }

    $pdo = Database::conectar();

    $email = strtolower(trim(getenv('TEST_USER_EMAIL') ?: ''));
    $password = getenv('TEST_USER_PASSWORD') ?: '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        fwrite(STDERR, "Definí TEST_USER_EMAIL y TEST_USER_PASSWORD (mínimo 12 caracteres).\n");
        exit(1);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);

    if ((int) $stmt->fetchColumn() > 0) {
        echo "La cuenta de usuario ya existe.\n";
        exit(0);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, telefono, rol_id, activo)
         VALUES (?, ?, ?, NULL, 1, 1)'
    );
    $stmt->execute(['Usuario Demo', $email, $passwordHash]);

    echo "Cuenta de prueba creada correctamente.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "No se pudo crear la cuenta de prueba.\n");
    exit(1);
}
