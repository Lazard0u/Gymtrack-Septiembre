<!--
  Vista administrativa AdminTrainersView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { IconShieldCheck, IconUserPlus } from '@tabler/icons-vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminFilterBar from '../../components/admin/AdminFilterBar.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppDrawer from '../../components/ui/AppDrawer.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import AppTextarea from '../../components/ui/AppTextarea.vue'
import AppToast from '../../components/ui/AppToast.vue'
import { useAdminStore } from '../../stores/admin'
import { useAdminManagementStore } from '../../stores/adminManagement'

const admin = useAdminStore(); const management = useAdminManagementStore()
const query = reactive({ q: '', status: '', page: 1, per_page: 20 })
const inviteOpen = ref(false); const editOpen = ref(false); const saving = ref(false); const error = ref(''); const fields = ref({})
const invitation = reactive({ email: '', cargo: 'Entrenador', biografia: '', especialidades: '' })
const edit = reactive({ id: null, nombre: '', email: '', biografia: '', especialidades: '', disponibilidad: '', estado: 'activo' })
const toast = reactive({ open: false, title: '', message: '', tone: 'success' })
const invitations = computed(() => management.invitations.filter((item) => item.tipo === 'entrenador'))
const columns = [{ key: 'nombre', label: 'Nombre' }, { key: 'apellido', label: 'Apellido' }, { key: 'email', label: 'Correo' }, { key: 'especialidades', label: 'Especialidades' }, { key: 'estado', label: 'Estado', format: 'status' }, { key: 'creado_en', label: 'Alta', format: 'datetime' }]
const filters = [{ key: 'status', label: 'Estado', options: [{ value: '', label: 'Todos' }, { value: 'activo', label: 'Activos' }, { value: 'inactivo', label: 'Inactivos' }, { value: 'archivado', label: 'Archivados' }] }]
const status = computed(() => management.status === 'loading' ? 'loading' : management.error ? 'error' : management.trainers.length ? 'ready' : 'empty')
function split(value) { return String(value || '').split(',').map((item) => item.trim()).filter(Boolean) }
function notify(title, message = '', tone = 'success') { Object.assign(toast, { open: true, title, message, tone }) }
async function load() { management.status = 'loading'; management.error = ''; await Promise.all([management.loadTrainers(query), management.loadInvitations()]); if (management.status === 'loading') management.status = 'ready' }
async function invite() { saving.value = true; error.value = ''; fields.value = {}; const result = await management.invite({ email: invitation.email, tipo: 'entrenador', cargo: invitation.cargo, biografia: invitation.biografia, especialidades: split(invitation.especialidades), permissions: ['classes.read'] }); saving.value = false; if (!result.ok) { error.value = result.message; fields.value = result.fields; return } inviteOpen.value = false; await load(); notify(result.data.status === 'accepted_existing' ? 'Entrenador asociado' : 'Invitación enviada') }
function openEdit(item) { Object.assign(edit, { id: item.id, nombre: `${item.nombre} ${item.apellido}`.trim(), email: item.email, biografia: item.biografia || '', especialidades: (item.especialidades || []).join(', '), disponibilidad: (item.disponibilidad || []).join(', '), estado: item.estado }); editOpen.value = true; error.value = '' }
async function save() { saving.value = true; error.value = ''; const result = await management.updateTrainer(edit.id, { biografia: edit.biografia, especialidades: split(edit.especialidades), disponibilidad: split(edit.disponibilidad), estado: edit.estado }); saving.value = false; if (!result.ok) { error.value = result.message; fields.value = result.fields; return } editOpen.value = false; await load(); notify('Perfil de entrenador actualizado') }
async function revoke(id) { const result = await management.revokeInvitation(id); result.ok ? notify('Invitación revocada') : notify('No se pudo revocar', result.message, 'danger') }
watch(() => admin.version, load, { immediate: true })
</script>

<template>
  <section>
    <AdminPageHeading title="Entrenadores" description="Perfiles técnicos, especialidades y disponibilidad dentro del gimnasio activo."><template #actions><AppButton @click="inviteOpen = true"><template #icon><IconUserPlus :size="18" /></template>Invitar entrenador</AppButton></template></AdminPageHeading>
    <AppCard :padded="false" class="resource-card"><AdminFilterBar :model-value="query" :filters="filters" :loading="management.status === 'loading'" @apply="Object.assign(query, $event); load()" @clear="Object.assign(query, { q: '', status: '', page: 1 }); load()" /><AdminDataTable :columns="columns" :items="management.trainers" :pagination="management.trainersPagination" :status="status" :error="management.error" empty-title="No hay entrenadores" empty-description="Invitá a una persona o asociá un empleado existente como entrenador." row-action-label="Gestionar" @page="query.page = $event; load()" @retry="load" @row="openEdit" /></AppCard>
    <section class="pending"><h2>Invitaciones de entrenador</h2><p v-if="!invitations.length">No hay invitaciones pendientes.</p><ul v-else><li v-for="item in invitations" :key="item.id"><span>{{ item.email }} · {{ item.estado }}</span><AppButton v-if="item.estado === 'pendiente'" variant="ghost" size="sm" @click="revoke(item.id)">Revocar</AppButton></li></ul></section>

    <AppDrawer :open="inviteOpen" title="Invitar entrenador" @close="inviteOpen = false"><form id="trainer-invite-form" class="form" @submit.prevent="invite"><AppAlert v-if="error" tone="danger" title="No pudimos invitar"><p>{{ error }}</p></AppAlert><div class="form-note"><IconShieldCheck :size="22" /><p>El perfil técnico queda separado del cargo operativo y puede desactivarse sin eliminar historial.</p></div><AppInput v-model="invitation.email" label="Correo electrónico" name="trainer-email" type="email" required :error="fields.email" /><AppInput v-model="invitation.cargo" label="Cargo" name="trainer-role" required /><AppTextarea v-model="invitation.biografia" label="Biografía" name="trainer-bio" :rows="4" /><AppInput v-model="invitation.especialidades" label="Especialidades" name="trainer-specialties" hint="Separadas por comas." /></form><template #footer><div class="actions"><AppButton variant="secondary" @click="inviteOpen = false">Cancelar</AppButton><AppButton type="submit" form="trainer-invite-form" :loading="saving">Enviar invitación</AppButton></div></template></AppDrawer>
    <AppDrawer :open="editOpen" title="Perfil del entrenador" @close="editOpen = false"><form id="trainer-edit-form" class="form" @submit.prevent="save"><AppAlert v-if="error" tone="danger" title="No pudimos guardar"><p>{{ error }}</p></AppAlert><div class="identity"><strong>{{ edit.nombre }}</strong><span>{{ edit.email }}</span></div><AppSelect v-model="edit.estado" label="Estado" name="trainer-status" :options="[{ value: 'activo', label: 'Activo' }, { value: 'inactivo', label: 'Inactivo' }, { value: 'archivado', label: 'Archivado' }]" /><AppTextarea v-model="edit.biografia" label="Biografía" name="trainer-edit-bio" :rows="4" /><AppInput v-model="edit.especialidades" label="Especialidades" name="trainer-edit-specialties" hint="Separadas por comas." /><AppInput v-model="edit.disponibilidad" label="Disponibilidad" name="trainer-availability" hint="Bloques descriptivos separados por comas." /></form><template #footer><div class="actions"><AppButton variant="secondary" @click="editOpen = false">Cancelar</AppButton><AppButton type="submit" form="trainer-edit-form" :loading="saving">Guardar cambios</AppButton></div></template></AppDrawer>
    <AppToast v-bind="toast" @close="toast.open = false" />
  </section>
</template>

<style scoped>
.resource-card { overflow: clip; }.pending { margin-top: var(--space-10); padding-top: var(--space-8); border-top: 1px solid var(--border-subtle); }.pending h2 { margin: 0 0 var(--space-4); font-size: 1.2rem; }.pending > p { color: var(--text-tertiary); font-size: .82rem; }.pending ul { margin: 0; padding: 0; list-style: none; }.pending li { display: flex; min-height: 3.5rem; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); color: var(--text-secondary); font-size: .8rem; }.form { display: grid; gap: var(--space-5); }.form-note { display: grid; grid-template-columns: 1.5rem 1fr; gap: var(--space-3); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-5); }.form-note svg { color: var(--info); }.form-note p { margin: 0; color: var(--text-secondary); font-size: .8rem; line-height: 1.5; }.identity { display: grid; gap: var(--space-1); }.identity span { color: var(--text-tertiary); font-size: .75rem; }.actions { display: flex; justify-content: flex-end; gap: var(--space-3); }
</style>
