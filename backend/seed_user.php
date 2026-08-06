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
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email_normalizado = ?');
    $stmt->execute([$email]);
    $existingId = $stmt->fetchColumn();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE nombre = "socio" LIMIT 1');
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if ($existingId) {
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash=?,debe_cambiar_password=0,email_verificado_en=COALESCE(email_verificado_en,NOW()),rol_id=?,activo=1 WHERE id=?');
        $stmt->execute([$passwordHash,(int)$roleId,(int)$existingId]);
        $pdo->prepare('UPDATE user_sessions SET revocada_en=COALESCE(revocada_en,NOW()),motivo_revocacion="credential_rotation" WHERE usuario_id=?')->execute([(int)$existingId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, email_normalizado, email_verificado_en, password_hash, telefono, rol_id, activo)
             VALUES (?, ?, ?, ?, NOW(), ?, NULL, ?, 1)'
        );
        $stmt->execute(['Usuario', 'Prueba', $email, $email, $passwordHash, (int)$roleId]);
    }

    echo "Cuenta de prueba creada o actualizada correctamente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear la cuenta de prueba.\n");
    exit(1);
}
