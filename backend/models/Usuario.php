<?php

declare(strict_types=1);

final class Usuario
{
    private PDO $pdo;
    public function __construct(){ $this->pdo=Database::conectar(); }

    public function buscarPorEmail(string $email): array|false
    {
        $stmt=$this->pdo->prepare('SELECT u.*,r.nombre rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.email_normalizado=? LIMIT 1');
        $stmt->execute([Security::normalizeEmail($email)]); return $stmt->fetch();
    }

    public function emailExiste(string $email): bool { return (bool)$this->buscarPorEmail($email); }

    public function crear(string $nombre,string $email,string $passwordHash,?string $telefono=null,string $apellido='',bool $terms=false,bool $privacy=false,?DateTimeImmutable $marketing=null): int
    {
        $role=$this->roleId(AuthorizationService::SOCIO); $now=date('Y-m-d H:i:s');
        $stmt=$this->pdo->prepare('INSERT INTO usuarios(nombre,apellido,email,email_normalizado,password_hash,telefono,rol_id,activo,terminos_aceptados_en,privacidad_aceptada_en,marketing_consentido_en) VALUES(?,?,?,?,?,?,?,1,?,?,?)');
        $stmt->execute([$nombre,$apellido,$email,Security::normalizeEmail($email),$passwordHash,$telefono,$role,$terms?$now:null,$privacy?$now:null,$marketing?->format('Y-m-d H:i:s')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt=$this->pdo->prepare('SELECT u.id,u.nombre,u.apellido,u.email,u.telefono,u.fecha_nacimiento,u.activo,u.is_demo,u.demo_dataset_id,u.email_verificado_en,u.debe_cambiar_password,u.creado_en,r.nombre rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? LIMIT 1');
        $stmt->execute([$id]); return $stmt->fetch();
    }

    public function contextosGimnasio(int $userId): array { return (new AuthorizationService())->gymAssignments($userId); }

    public function verifyEmail(int $userId): void { $this->pdo->prepare('UPDATE usuarios SET email_verificado_en=COALESCE(email_verificado_en,NOW()) WHERE id=?')->execute([$userId]); }
    public function updatePassword(int $userId,string $hash): void { $this->pdo->prepare('UPDATE usuarios SET password_hash=?,debe_cambiar_password=0 WHERE id=?')->execute([$hash,$userId]); }
    public function rehashPassword(int $userId,string $hash): void { $this->pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([$hash,$userId]); }

    public function listarSocios(?int $demoDatasetId=null, ?int $gimnasioId=null): array
    {
        $sql='SELECT u.id,u.nombre,u.apellido,u.email,u.telefono,u.activo,u.creado_en,r.nombre rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre=? AND u.is_demo=?';
        $params=[AuthorizationService::SOCIO,$demoDatasetId===null?0:1]; if($demoDatasetId!==null){$sql.=' AND u.demo_dataset_id=?';$params[]=$demoDatasetId;} if($gimnasioId!==null){$sql.=' AND EXISTS(SELECT 1 FROM usuario_gimnasio_roles ugr WHERE ugr.usuario_id=u.id AND ugr.gimnasio_id=? AND ugr.activo=1)';$params[]=$gimnasioId;} $sql.=' ORDER BY u.creado_en DESC';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function buscarPorIdEnDataset(int $id,?int $demoDatasetId,?int $gimnasioId=null): array|false
    {
        $sql='SELECT u.id,u.nombre,u.apellido,u.email,u.telefono,u.activo,u.is_demo,u.demo_dataset_id,u.creado_en,r.nombre rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.is_demo=?';
        $params=[$id,$demoDatasetId===null?0:1];if($demoDatasetId!==null){$sql.=' AND u.demo_dataset_id=?';$params[]=$demoDatasetId;}if($gimnasioId!==null){$sql.=' AND EXISTS(SELECT 1 FROM usuario_gimnasio_roles ugr WHERE ugr.usuario_id=u.id AND ugr.gimnasio_id=? AND ugr.activo=1)';$params[]=$gimnasioId;}$sql.=' LIMIT 1';$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetch();
    }

    public function cambiarEstado(int $id,int $active,int $gimnasioId,?int $demoDatasetId): bool
    {
        $stmt=$this->pdo->prepare(
            'UPDATE usuarios u SET u.activo=?
             WHERE u.id=? AND u.is_demo=? AND (u.demo_dataset_id <=> ?)
               AND EXISTS(
                   SELECT 1 FROM usuario_gimnasio_roles ugr
                   WHERE ugr.usuario_id=u.id AND ugr.gimnasio_id=? AND ugr.activo=1
               )'
        );
        $stmt->execute([$active,$id,$demoDatasetId===null?0:1,$demoDatasetId,$gimnasioId]);
        return $stmt->rowCount()===1;
    }
    public function contarSociosActivos(?int $demoDatasetId=null,?int $gimnasioId=null): int { $sql='SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre=? AND u.activo=1 AND u.is_demo=?';$params=[AuthorizationService::SOCIO,$demoDatasetId===null?0:1];if($demoDatasetId!==null){$sql.=' AND u.demo_dataset_id=?';$params[]=$demoDatasetId;}if($gimnasioId!==null){$sql.=' AND EXISTS(SELECT 1 FROM usuario_gimnasio_roles ugr WHERE ugr.usuario_id=u.id AND ugr.gimnasio_id=? AND ugr.activo=1)';$params[]=$gimnasioId;}$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return(int)$stmt->fetchColumn(); }
    public function actualizarPerfil(int $id,string $nombre,?string $telefono): bool { $stmt=$this->pdo->prepare('UPDATE usuarios SET nombre=?,telefono=? WHERE id=?');return$stmt->execute([$nombre,$telefono,$id]); }

    private function roleId(string $role): int { $stmt=$this->pdo->prepare('SELECT id FROM roles WHERE nombre=? LIMIT 1');$stmt->execute([$role]);$id=$stmt->fetchColumn();if(!$id)throw new RuntimeException('Rol requerido no configurado: '.$role);return(int)$id; }
}
