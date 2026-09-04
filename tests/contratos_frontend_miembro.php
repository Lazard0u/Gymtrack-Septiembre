<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

/** @param list<string> $needles */
function requireStrings(string $source, array $needles, string $subject, array &$failures): void
{
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $failures[] = "{$subject} no conserva el contrato: {$needle}";
        }
    }
}

$memberStore = file_get_contents($root . '/frontend/src/stores/member.js');
requireStrings($memberStore, [
    "api.get('/member/summary')",
    "api.get('/member/preferences')",
    "api.put('/member/preferences', payload)",
    "api.get('/member/favorites')",
    "api.post('/member/favorites', { type, target_id: Number(targetId) })",
    "api.delete(`/member/favorites/\${type}/\${targetId}`)",
    "api.post('/member/measurements', payload, { headers: { 'Idempotency-Key': crypto.randomUUID() } })",
    "api.get('/member/card')",
], 'El store de socio', $failures);

$router = file_get_contents($root . '/frontend/src/router/index.js');
$memberRoutes = [
    '/perfil' => 'member-profile',
    '/preferencias' => 'member-preferences',
    '/progreso' => 'member-activity',
    '/carne' => 'member-card',
];
foreach ($memberRoutes as $path => $name) {
    $routeStart = strpos($router, "path: '{$path}'");
    $routeEnd = $routeStart === false ? false : strpos($router, "\n  },", $routeStart);
    if ($routeStart === false || $routeEnd === false) {
        $failures[] = "No existe la ruta de socio {$path}.";
        continue;
    }
    $route = substr($router, $routeStart, $routeEnd - $routeStart);
    if (!str_contains($route, "name: '{$name}'")) {
        $failures[] = "La ruta {$path} no conserva el nombre {$name}.";
    }
    foreach (['requiresAuth: true', 'requiresVerifiedEmail: true', "allowedRoles: ['socio']"] as $guard) {
        if (!str_contains($route, $guard)) {
            $failures[] = "La ruta {$path} no exige {$guard}.";
        }
    }
}
requireStrings($router, [
    "if (to.meta.requiresAuth && !authStore.estaAutenticado) return { name: 'login'",
    "if (to.meta.requiresVerifiedEmail && !authStore.correoVerificado) return { name: 'verify-email'",
    "if (to.meta.allowedRoles && !to.meta.allowedRoles.includes(authStore.user?.role)) return { name: 'forbidden'",
    "path: 'verificar-carne', name: 'admin-member-card-verify', component: AdminMemberCardVerifyView, meta: { adminLabel: 'Verificar carné', requiredPermission: 'members.read'",
], 'El router', $failures);

$verifier = file_get_contents($root . '/frontend/src/views/admin/AdminMemberCardVerifyView.vue');
requireStrings($verifier, [
    "api.post('/admin/member-card/verify', { token: value })",
    'v-if="!admin.hasContext"',
    'watch(() => admin.version, reset)',
    "status.value = 'error'",
    "status.value = 'ready'",
    'result?.valid',
    'datos obtenidos y verificados desde MySQL',
], 'El verificador administrativo', $failures);

$explorer = file_get_contents($root . '/frontend/src/components/public/GymExplorer.vue');
requireStrings($explorer, [
    "import { useMemberStore } from '../../stores/member'",
    "auth.user?.role === 'socio' && Boolean(auth.user?.active_gym_id)",
    'await member.loadFavorites()',
    "await member.setFavorite('gym', gym.id, !wasFavorite)",
    ':aria-pressed="favoriteGymIds.has(Number(gym.id))"',
], 'El favorito del mapa', $failures);

$favoriteFunctionStart = strpos($explorer, 'async function toggleFavorite(gym)');
$favoriteFunctionEnd = $favoriteFunctionStart === false ? false : strpos($explorer, "\n}\n", $favoriteFunctionStart);
if ($favoriteFunctionStart === false || $favoriteFunctionEnd === false) {
    $failures[] = 'El mapa no expone toggleFavorite().';
} else {
    $favoriteFunction = substr($explorer, $favoriteFunctionStart, $favoriteFunctionEnd - $favoriteFunctionStart);
    if (str_contains($favoriteFunction, 'localStorage')) {
        $failures[] = 'El favorito del mapa se guarda localmente en vez de usar la persistencia del socio.';
    }
}

$apiRoutes = file_get_contents($root . '/backend/routes/api.php');
$repository = file_get_contents($root . '/backend/models/MemberRepository.php');
$migration = file_get_contents($root . '/database/migrations/009_phase10_member_experience.sql');
requireStrings($apiRoutes, [
    "'GET /api/member/favorites'",
    "'POST /api/member/favorites'",
    "'DELETE /api/member/favorites/(gym|activity)/(\\d+)'",
    "'POST /api/admin/member-card/verify'",
], 'Las rutas API del área del socio', $failures);
requireStrings($repository, [
    'INSERT IGNORE INTO socio_gimnasios_favoritos',
    'DELETE FROM socio_gimnasios_favoritos',
    'WHERE usuario_id=? AND gimnasio_id=? AND is_demo=? AND (demo_dataset_id <=> ?)',
], 'MemberRepository', $failures);
requireStrings($migration, [
    'CREATE TABLE IF NOT EXISTS socio_gimnasios_favoritos',
    'UNIQUE KEY uq_socio_gimnasio_favorito (usuario_id,gimnasio_id)',
    'FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE',
    'FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE',
], 'La persistencia MySQL de favoritos', $failures);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contratos frontend de experiencia del socio correctos.\n";
