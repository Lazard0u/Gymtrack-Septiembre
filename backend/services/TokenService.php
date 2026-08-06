<?php

declare(strict_types=1);

final class TokenService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function issueEmailVerification(int $userId): string
    {
        $this->pdo->prepare('UPDATE email_verification_tokens SET usado_en=COALESCE(usado_en,NOW()) WHERE usuario_id=? AND usado_en IS NULL')->execute([$userId]);
        return $this->issue('email_verification_tokens',$userId,24*3600);
    }

    public function issuePasswordReset(int $userId): string
    {
        $this->pdo->prepare('UPDATE password_reset_tokens SET usado_en=COALESCE(usado_en,NOW()) WHERE usuario_id=? AND usado_en IS NULL')->execute([$userId]);
        return $this->issue('password_reset_tokens',$userId,3600);
    }

    public function consume(string $table, string $plainToken): ?int
    {
        if (!in_array($table,['email_verification_tokens','password_reset_tokens'],true) || strlen($plainToken)<32 || strlen($plainToken)>160) return null;
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare("SELECT id,usuario_id FROM {$table} WHERE token_hash=? AND usado_en IS NULL AND expira_en>NOW() FOR UPDATE");
            $stmt->execute([hash('sha256',$plainToken)]); $row=$stmt->fetch();
            if(!$row){$this->pdo->rollBack();return null;}
            $this->pdo->prepare("UPDATE {$table} SET usado_en=NOW() WHERE id=? AND usado_en IS NULL")->execute([(int)$row['id']]);
            $this->pdo->commit(); return (int)$row['usuario_id'];
        } catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function cleanup(): int
    {
        $total=0;
        foreach(['email_verification_tokens','password_reset_tokens'] as $table){$stmt=$this->pdo->prepare("DELETE FROM {$table} WHERE expira_en<DATE_SUB(NOW(),INTERVAL 1 DAY) OR usado_en<DATE_SUB(NOW(),INTERVAL 7 DAY)");$stmt->execute();$total+=$stmt->rowCount();}
        return $total;
    }

    private function issue(string $table,int $userId,int $ttl): string
    {
        $plain=Security::randomToken(48); $hash=hash('sha256',$plain);
        $stmt=$this->pdo->prepare("INSERT INTO {$table}(usuario_id,token_hash,creado_en,expira_en) VALUES(?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND))");
        $stmt->execute([$userId,$hash,$ttl]); return $plain;
    }
}
