<?php

declare(strict_types=1);

/**
 * Contrato de rutas para integrar en backend/routes/api.php:
 * GET   /api/member/summary                  -> summary
 * GET   /api/member/profile                  -> profile
 * PATCH /api/member/profile                  -> updateProfile
 * POST  /api/member/profile/photo            -> uploadPhoto (multipart: photo)
 * GET   /api/member/avatar                   -> avatar
 * GET   /api/member/preferences              -> preferences
 * PUT   /api/member/preferences              -> updatePreferences
 * GET   /api/member/favorites                -> favorites
 * POST  /api/member/favorites                -> addFavorite ({type,target_id})
 * DELETE /api/member/favorites/(gym|activity)/(id) -> removeFavorite
 * GET   /api/member/attendance               -> attendance
 * GET   /api/member/memberships              -> memberships
 * GET   /api/member/measurements             -> measurements
 * POST  /api/member/measurements             -> createMeasurement
 * GET   /api/member/card                     -> card
 * POST  /api/admin/member-card/verify        -> verifyCard ({token})
 */
final class MemberController
{
    private const ADMIN_ROLES = ['empleado','dueño','admin_general'];
    private const MAX_PHOTO_BYTES = 5_242_880;
    private const PHOTO_MIMES = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public function summary(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        ApiResponder::success($repo->summary($userId,$gymId));
    }

    public function profile(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        ApiResponder::success($repo->profile($userId,$gymId) ?? []);
    }

    public function updateProfile(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $body = $this->body();
        $fields = [];
        $errors = [];

        if (array_key_exists('first_name',$body)) {
            $fields['first_name'] = $this->boundedString($body['first_name'],'El nombre',2,100,$errors,'first_name');
        }
        if (array_key_exists('last_name',$body)) {
            $fields['last_name'] = $this->boundedString($body['last_name'],'El apellido',2,100,$errors,'last_name');
        }
        if (array_key_exists('phone',$body)) {
            $phone = trim((string)$body['phone']);
            if ($phone !== '' && !preg_match('/^[0-9+() .-]{7,20}$/',$phone)) {
                $errors['phone'] = 'Ingresá un teléfono válido de hasta 20 caracteres.';
            }
            $fields['phone'] = $phone === '' ? null : $phone;
        }
        if (array_key_exists('birth_date',$body)) {
            $fields['birth_date'] = $this->optionalDate($body['birth_date'],'birth_date','La fecha de nacimiento',$errors,false);
        }
        if ($fields === []) {
            $errors['profile'] = 'Enviá al menos un campo editable.';
        }
        if ($errors) ApiResponder::error(422,'validation_error','Revisá los campos indicados.',$errors);

        ApiResponder::success($repo->updateProfile($userId,$gymId,$fields) ?? []);
    }

    public function uploadPhoto(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $file = $_FILES['photo'] ?? $_FILES['archivo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            ApiResponder::error(422,'photo_required','Seleccioná una foto válida.',['photo'=>'No se recibió la foto.']);
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_PHOTO_BYTES) {
            ApiResponder::error(422,'photo_too_large','La foto debe pesar menos de 5 MB.',['photo'=>'Tamaño máximo: 5 MB.']);
        }
        $temporary = (string)($file['tmp_name'] ?? '');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
        if (!isset(self::PHOTO_MIMES[$mime])) {
            ApiResponder::error(422,'photo_type_not_allowed','El formato de la foto no está permitido.',['photo'=>'Usá JPG, PNG o WebP.']);
        }
        $dimensions = @getimagesize($temporary);
        if (!$dimensions || $dimensions[0] < 128 || $dimensions[1] < 128 || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
            ApiResponder::error(422,'invalid_photo','La foto no tiene dimensiones válidas.',['photo'=>'Entre 128×128 y 6000×6000 px.']);
        }

        $extension = self::PHOTO_MIMES[$mime];
        $scope = AuthMiddleware::esCuentaDemo() ? 'demo-' . (int)AuthMiddleware::obtenerDemoDatasetId() : 'real';
        $storageKey = sprintf('%s/%d/socio_avatar/%d/%s.%s',$scope,$gymId,$userId,bin2hex(random_bytes(20)),$extension);
        $storage = new LocalFileStorage();
        $storage->putUploaded($temporary,$storageKey);
        try {
            $result = $repo->saveAvatar($userId,$gymId,[
                'original_name'=>mb_substr(basename((string)($file['name'] ?? 'avatar.' . $extension)),0,255),
                'storage_key'=>$storageKey,
                'mime_type'=>$mime,
                'size'=>$size,
                'sha256'=>hash_file('sha256',$storage->path($storageKey)),
            ]);
            if (!$result) {
                $storage->delete($storageKey);
                ApiResponder::error(403,'member_scope_mismatch','Tu perfil no pertenece al gimnasio activo.');
            }
            if (!empty($result['old_storage_key'])) {
                $storage->delete((string)$result['old_storage_key']);
            }
            unset($result['old_storage_key']);
            ApiResponder::success($result,201);
        } catch (Throwable $error) {
            $storage->delete($storageKey);
            throw $error;
        }
    }

    public function avatar(): void
    {
        [$userId,,$repo] = $this->memberAccess();
        $file = $repo->avatarFile($userId);
        if (!$file) ApiResponder::error(404,'avatar_not_found','Todavía no cargaste una foto de perfil.');
        $path = (new LocalFileStorage())->path((string)$file['storage_key']);
        if (!is_file($path)) ApiResponder::error(404,'avatar_unavailable','La foto de perfil no está disponible.');
        $etag = '"' . (string)$file['sha256'] . '"';
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="avatar.' . self::PHOTO_MIMES[$file['mime_type']] . '"');
        header('Cache-Control: private, max-age=300');
        header('ETag: ' . $etag);
        readfile($path);
        exit;
    }

    public function preferences(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        ApiResponder::success($repo->preferences($userId,$gymId));
    }

    public function updatePreferences(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $body = $this->body();
        $current = $repo->preferences($userId,$gymId);
        $goal = filter_var($body['monthly_attendance_goal'] ?? $current['monthly_attendance_goal'],FILTER_VALIDATE_INT);
        if ($goal === false || $goal < 1 || $goal > 31) {
            ApiResponder::error(422,'validation_error','Revisá los campos indicados.',['monthly_attendance_goal'=>'Elegí un objetivo entre 1 y 31 asistencias.']);
        }
        $showBmi = array_key_exists('show_orientation_bmi',$body)
            ? filter_var($body['show_orientation_bmi'],FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE)
            : (bool)$current['show_orientation_bmi'];
        if ($showBmi === null) {
            ApiResponder::error(422,'validation_error','Revisá los campos indicados.',['show_orientation_bmi'=>'Usá un valor booleano.']);
        }
        $preferredSchedules = $body['preferred_schedules'] ?? $current['preferred_schedules'];
        if (!is_array($preferredSchedules) || !array_is_list($preferredSchedules)) {
            ApiResponder::error(422,'validation_error','Revisá los campos indicados.',['preferred_schedules'=>'Enviá una lista de horarios preferidos.']);
        }
        $allowedSchedules = ['morning','afternoon','evening'];
        $preferredSchedules = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $preferredSchedules
        )));
        foreach ($preferredSchedules as $schedule) {
            if (!in_array($schedule,$allowedSchedules,true)) {
                ApiResponder::error(422,'validation_error','Revisá los campos indicados.',[
                    'preferred_schedules'=>'Usá únicamente morning, afternoon o evening.',
                ]);
            }
        }
        ApiResponder::success($repo->updatePreferences(
            $userId,$gymId,(int)$goal,(bool)$showBmi,$preferredSchedules
        ));
    }

    public function favorites(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        ApiResponder::success($repo->favorites($userId,$gymId));
    }

    public function addFavorite(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $body = $this->body();
        $type = (string)($body['type'] ?? '');
        $targetId = filter_var($body['target_id'] ?? null,FILTER_VALIDATE_INT);
        if (!in_array($type,['gym','activity'],true) || $targetId === false || $targetId < 1) {
            ApiResponder::error(422,'validation_error','Revisá el favorito.',['type'=>'Usá gym o activity.','target_id'=>'Seleccioná un identificador válido.']);
        }
        $added = $type === 'gym'
            ? $repo->addFavoriteGym($userId,(int)$targetId)
            : $repo->addFavoriteActivity($userId,$gymId,(int)$targetId);
        if (!$added) ApiResponder::error(404,'favorite_target_not_found','El favorito no está disponible dentro de tu scope.');
        ApiResponder::success(['type'=>$type,'target_id'=>(int)$targetId,'favorite'=>true],201);
    }

    public function removeFavorite(string $type, string $targetId): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $id = filter_var($targetId,FILTER_VALIDATE_INT);
        if (!in_array($type,['gym','activity'],true) || $id === false || $id < 1) {
            ApiResponder::error(422,'validation_error','Revisá el favorito.');
        }
        $removed = $repo->removeFavorite($userId,$gymId,$type,(int)$id);
        ApiResponder::success(['type'=>$type,'target_id'=>(int)$id,'favorite'=>false,'removed'=>$removed]);
    }

    public function attendance(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        [$page,$perPage] = $this->page();
        $result = $repo->attendanceHistory($userId,$gymId,$page,$perPage);
        ApiResponder::success(['items'=>$result['items']],200,['pagination'=>$result['pagination']]);
    }

    public function memberships(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        ApiResponder::success(['items'=>$repo->memberships($userId,$gymId)]);
    }

    public function measurements(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        [$page,$perPage] = $this->page();
        $result = $repo->measurements($userId,$gymId,$page,$perPage);
        ApiResponder::success(
            ['items'=>$result['items'],'medical_notice'=>$result['medical_notice']],
            200,
            ['pagination'=>$result['pagination']]
        );
    }

    public function createMeasurement(): void
    {
        [$userId,$gymId,$repo] = $this->memberAccess();
        $body = $this->body();
        $errors = [];
        $measuredAt = $this->optionalDate($body['measured_at'] ?? date('Y-m-d'),'measured_at','La fecha de medición',$errors,true);
        $height = $this->optionalDecimal($body,'height_cm','La altura',80,250,$errors);
        $weight = $this->optionalDecimal($body,'weight_kg','El peso',20,500,$errors);
        if ($height === null && $weight === null) {
            $errors['measurement'] = 'Ingresá al menos altura o peso.';
        }
        $notes = trim((string)($body['notes'] ?? ''));
        if (mb_strlen($notes) > 500) $errors['notes'] = 'Las notas admiten hasta 500 caracteres.';
        if ($errors) ApiResponder::error(422,'validation_error','Revisá los campos indicados.',$errors);
        $idempotencyKey = $this->idempotencyKey();
        $result = $repo->createMeasurement($userId,$gymId,[
            'measured_at'=>$measuredAt,
            'height_cm'=>$height,
            'weight_kg'=>$weight,
            'notes'=>$notes === '' ? null : $notes,
        ],$idempotencyKey);
        ApiResponder::success($result,201,['idempotent'=>$result['idempotent']]);
    }

    public function card(): void
    {
        [$userId,$gymId,$repo,$context] = $this->memberAccess();
        $record = $repo->cardRecord($userId,$gymId);
        if (!$record) ApiResponder::error(404,'member_not_found','No encontramos tu perfil de socio en el gimnasio activo.');
        $membershipId = isset($record['membership']['id']) ? (int)$record['membership']['id'] : null;
        $issued = (new MemberCardService())->issue(
            $userId,$gymId,$membershipId,AuthMiddleware::esCuentaDemo(),AuthMiddleware::obtenerDemoDatasetId()
        );
        ApiResponder::success([
            ...$issued,
            'member'=>[
                'member_number'=>$context['member_number'] ?: ($record['membership']['numero_socio'] ?? null),
                'gym'=>['id'=>$context['gym_id'],'name'=>$context['gym_name']],
            ],
            'membership'=>$record['membership'],
        ]);
    }

    public function verifyCard(): void
    {
        AuthMiddleware::verificarRoles(self::ADMIN_ROLES);
        AuthMiddleware::verificarPermiso('members.read');
        $actor = AuthMiddleware::obtenerUsuarioId();
        $gymId = AuthMiddleware::requerirContextoGimnasio();
        $repo = new MemberRepository(AuthMiddleware::obtenerDemoDatasetId());
        if (!$repo->gymInScope($gymId)) {
            ApiResponder::error(403,'gym_scope_mismatch','El gimnasio activo no pertenece al scope de tu sesión.');
        }
        $body = $this->body();
        $token = trim((string)($body['token'] ?? ''));
        if ($token === '' || strlen($token) > 4096) {
            ApiResponder::error(422,'validation_error','Escaneá un carné válido.',['token'=>'El token es obligatorio.']);
        }
        try {
            $claims = (new MemberCardService())->verify($token);
        } catch (InvalidArgumentException $error) {
            ApiResponder::error(422,'invalid_member_card',$error->getMessage());
        }
        if ($claims['gym_id'] !== $gymId
            || $claims['is_demo'] !== AuthMiddleware::esCuentaDemo()
            || $claims['dataset_id'] !== AuthMiddleware::obtenerDemoDatasetId()) {
            ApiResponder::error(403,'member_card_scope_mismatch','El carné no pertenece al gimnasio y dataset activos.');
        }
        $result = $repo->verifyCardMember($claims['user_id'],$gymId,$claims['membership_id']);
        if (!$result) ApiResponder::error(404,'member_not_found','El carné no corresponde a un socio de este gimnasio.');
        AdminAuditLogger::record(
            'member.card.verified','socio',$result['valid']?'success':'denied',$actor,$gymId,(string)$claims['user_id'],
            $result['valid']?'Carné vigente':'Carné sin membresía vigente'
        );
        ApiResponder::success([...$result,'verified_at'=>gmdate('c')]);
    }

    private function memberAccess(): array
    {
        AuthMiddleware::verificarRol(AuthorizationService::SOCIO);
        $userId = AuthMiddleware::obtenerUsuarioId();
        $gymId = AuthMiddleware::requerirContextoGimnasio();
        $repo = new MemberRepository(AuthMiddleware::obtenerDemoDatasetId());
        $context = $repo->memberContext($userId,$gymId);
        if (!$context) ApiResponder::error(403,'member_scope_mismatch','Tu perfil de socio no pertenece al gimnasio y dataset activos.');
        return [$userId,$gymId,$repo,$context];
    }

    private function body(): array
    {
        $decoded = json_decode((string)file_get_contents('php://input'),true);
        if (!is_array($decoded)) ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');
        return $decoded;
    }

    private function page(): array
    {
        $page = filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1;
        $perPage = filter_var($_GET['per_page'] ?? 20,FILTER_VALIDATE_INT) ?: 20;
        return [max(1,(int)$page),max(1,min(50,(int)$perPage))];
    }

    private function boundedString(mixed $raw, string $label, int $min, int $max, array &$errors, string $key): string
    {
        $value = trim((string)$raw);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) $errors[$key] = "{$label} debe tener entre {$min} y {$max} caracteres.";
        return mb_substr($value,0,$max);
    }

    private function optionalDate(mixed $raw, string $key, string $label, array &$errors, bool $allowToday): ?string
    {
        $value = trim((string)$raw);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$key] = "{$label} no es válida.";
            return $value;
        }
        $today = new DateTimeImmutable('today');
        if ($date > $today || (!$allowToday && $date < new DateTimeImmutable('1900-01-01'))) {
            $errors[$key] = $allowToday ? "{$label} no puede estar en el futuro." : "{$label} debe estar entre 1900 y hoy.";
        }
        return $value;
    }

    private function optionalDecimal(array $body, string $key, string $label, float $min, float $max, array &$errors): ?string
    {
        if (!array_key_exists($key,$body) || $body[$key] === null || trim((string)$body[$key]) === '') return null;
        $raw = str_replace(',','.',trim((string)$body[$key]));
        if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/',$raw) || (float)$raw < $min || (float)$raw > $max) {
            $errors[$key] = "{$label} debe estar entre {$min} y {$max}.";
            return null;
        }
        return number_format((float)$raw,2,'.','');
    }

    private function idempotencyKey(): ?string
    {
        $key = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if ($key === '') return null;
        if (!preg_match('/^[a-f0-9-]{36}$/i',$key)) {
            ApiResponder::error(422,'invalid_idempotency_key','Idempotency-Key debe ser un UUID válido.');
        }
        return strtolower($key);
    }
}
