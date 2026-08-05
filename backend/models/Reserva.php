<?php
/**
 * GymTrack · Reserva.php
 * ---------------------------------------------------------------
 * Modelo para la tabla reservas.
 */

class Reserva
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function listarPorRango(string $desde, string $hasta): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    r.clase_id, c.nombre AS clase_nombre, c.dia_semana, c.hora_inicio, c.hora_fin,
                    r.fecha_reserva, r.estado
             FROM reservas r
             JOIN usuarios u ON r.usuario_id = u.id
             JOIN clases c ON r.clase_id = c.id
             WHERE r.fecha_reserva BETWEEN ? AND ?
             ORDER BY r.fecha_reserva DESC'
        );

        $stmt->execute([$desde, $hasta]);

        return $stmt->fetchAll();
    }

    public function reservar(int $usuarioId, int $claseId): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmtClase = $this->pdo->prepare(
                'SELECT id, activa, cupos_disponibles
                 FROM clases
                 WHERE id = ?
                 FOR UPDATE'
            );
            $stmtClase->execute([$claseId]);
            $clase = $stmtClase->fetch();

            if (!$clase || !(int) $clase['activa']) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La clase no existe o no está activa.'];
            }

            $stmtExistente = $this->pdo->prepare(
                'SELECT id, estado
                 FROM reservas
                 WHERE usuario_id = ? AND clase_id = ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmtExistente->execute([$usuarioId, $claseId]);
            $existente = $stmtExistente->fetch();

            if ($existente && $existente['estado'] !== 'cancelada') {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Ya tenés una reserva para esta clase.'];
            }

            if ((int) $clase['cupos_disponibles'] <= 0) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La clase ya no tiene cupos disponibles.'];
            }

            if ($existente) {
                $stmtReserva = $this->pdo->prepare(
                    'UPDATE reservas
                     SET estado = "confirmada", fecha_reserva = NOW()
                     WHERE id = ?'
                );
                $stmtReserva->execute([(int) $existente['id']]);
                $reservaId = (int) $existente['id'];
            } else {
                $stmtReserva = $this->pdo->prepare(
                    'INSERT INTO reservas (usuario_id, clase_id, fecha_reserva, estado)
                     VALUES (?, ?, NOW(), "confirmada")'
                );
                $stmtReserva->execute([$usuarioId, $claseId]);
                $reservaId = (int) $this->pdo->lastInsertId();
            }

            $stmtCupo = $this->pdo->prepare(
                'UPDATE clases
                 SET cupos_disponibles = cupos_disponibles - 1
                 WHERE id = ? AND cupos_disponibles > 0'
            );
            $stmtCupo->execute([$claseId]);

            if ($stmtCupo->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La clase ya no tiene cupos disponibles.'];
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'mensaje' => 'Reserva confirmada correctamente.',
                'reserva_id' => $reservaId,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                return ['ok' => false, 'mensaje' => 'Ya tenés una reserva para esta clase.'];
            }

            throw $e;
        }
    }

    public function cancelar(int $id, ?int $usuarioId = null): array
    {
        try {
            $this->pdo->beginTransaction();

            $sql = 'SELECT id, usuario_id, clase_id, estado FROM reservas WHERE id = ?';
            $parametros = [$id];
            if ($usuarioId !== null) {
                $sql .= ' AND usuario_id = ?';
                $parametros[] = $usuarioId;
            }
            $sql .= ' FOR UPDATE';

            $stmtReserva = $this->pdo->prepare($sql);
            $stmtReserva->execute($parametros);
            $reserva = $stmtReserva->fetch();

            if (!$reserva) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La reserva no existe o no te pertenece.'];
            }

            if ($reserva['estado'] === 'cancelada') {
                $this->pdo->commit();
                return ['ok' => true, 'mensaje' => 'La reserva ya estaba cancelada.'];
            }

            $stmtActualizar = $this->pdo->prepare(
                'UPDATE reservas SET estado = "cancelada" WHERE id = ?'
            );
            $stmtActualizar->execute([$id]);

            $stmtCupo = $this->pdo->prepare(
                'UPDATE clases
                 SET cupos_disponibles = LEAST(cupo_maximo, cupos_disponibles + 1)
                 WHERE id = ?'
            );
            $stmtCupo->execute([(int) $reserva['clase_id']]);

            $this->pdo->commit();

            return ['ok' => true, 'mensaje' => 'Reserva cancelada correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.clase_id, c.nombre AS clase_nombre, c.dia_semana,
                    c.hora_inicio, c.hora_fin, r.fecha_reserva, r.estado
             FROM reservas r
             JOIN clases c ON c.id = r.clase_id
             WHERE r.usuario_id = ?
             ORDER BY r.fecha_reserva DESC'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function listarTodas(?int $usuarioId = null): array
    {
        $sql = 'SELECT r.id, r.usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                       r.clase_id, c.nombre AS clase_nombre, c.dia_semana, c.hora_inicio, c.hora_fin,
                       r.fecha_reserva, r.estado
                FROM reservas r
                JOIN usuarios u ON u.id = r.usuario_id
                JOIN clases c ON c.id = r.clase_id';

        $parametros = [];
        if ($usuarioId !== null) {
            $sql .= ' WHERE r.usuario_id = ?';
            $parametros[] = $usuarioId;
        }
        $sql .= ' ORDER BY r.fecha_reserva DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }
}
