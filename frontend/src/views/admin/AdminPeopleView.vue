<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconMailPlus, IconUserCog, IconUserPlus } from '@tabler/icons-vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminFilterBar from '../../components/admin/AdminFilterBar.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppCheckbox from '../../components/ui/AppCheckbox.vue'
import AppDrawer from '../../components/ui/AppDrawer.vue'
import AppEmptyState from '../../components/ui/AppEmptyState.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import AppTextarea from '../../components/ui/AppTextarea.vue'
import AppToast from '../../components/ui/AppToast.vue'
import { useAdminManagementStore } from '../../stores/adminManagement'
import { useAdminMembersStore, useAdminStaffStore } from '../../stores/adminResources'
import { useAdminStore } from '../../stores/admin'

const props = defineProps({ kind: { type: String, required: true } })
const route = useRoute(); const router = useRouter(); const admin = useAdminStore(); const management = useAdminManagementStore()
const resource = props.kind === 'members' ? useAdminMembersStore() : useAdminStaffStore()
const inviteOpen = ref(false); const editOpen = ref(false); const loadingDetail = ref(false); const saving = ref(false); const fields = ref({}); const formError = ref(''); const toast = reactive({ open: false, title: '', message: '', tone: 'success' })
const invitation = reactive({ email: '', cargo: 'Operación', permissions: [] })
const edit = reactive({ id: null, nombre: '', email: '', estado: 'activo', notas_operativas: '', cargo: 'Operación', permissions: [] })
const isMembers = computed(() => props.kind === 'members')
const canWrite = computed(() => isMembers.value ? admin.hasPermission('members.write') : admin.hasPermission('staff.manage'))
const title = computed(() => isMembers.value ? 'Socios' : 'Empleados')
const description = computed(() => isMembers.value
  ? (canWrite.value ? 'Altas, invitaciones y estado operativo de las personas asociadas al gimnasio activo.' : 'Consulta de personas asociadas al gimnasio activo.')
  : 'Personal, cargos y permisos específicos dentro del gimnasio activo.')
const query = computed(() => ({ q: String(route.query.q || ''), status: String(route.query.status || ''), sort: String(route.query.sort || ''), direction: route.query.direction === 'asc' ? 'asc' : 'desc', page: Math.max(1, Number(route.query.page || 1)), per_page: 20 }))
const columns = computed(() => isMembers.value ? [
  { key: 'numero_socio', label: 'N.º socio' }, { key: 'nombre', label: 'Nombre', sortable: true }, { key: 'apellido', label: 'Apellido' }, { key: 'email', label: 'Correo', sortable: true }, { key: 'telefono', label: 'Teléfono' }, { key: 'estado_socio', label: 'Estado', format: 'status' }, { key: 'fecha_alta', label: 'Alta', format: 'date' },
] : [
  { key: 'nombre', label: 'Nombre', sortable: true }, { key: 'apellido', label: 'Apellido' }, { key: 'email', label: 'Correo', sortable: true }, { key: 'cargo', label: 'Cargo' }, { key: 'estado_empleado', label: 'Estado', format: 'status' }, { key: 'fecha_ingreso', label: 'Ingreso', format: 'date' },
])
const filters = [{ key: 'status', label: 'Estado', options: [{ value: '', label: 'Todos' }, { value: 'activo', label: 'Activos' }, { value: 'inactivo', label: 'Inactivos' }, { value: 'archivado', label: 'Archivados' }] }]
const permissionOptions = [
  ['members.read', 'Consultar socios'], ['members.write', 'Gestionar socios'], ['classes.read', 'Consultar clases'], ['classes.write', 'Gestionar clases'], ['reservations.read', 'Consultar reservas'], ['reservations.write', 'Gestionar reservas'], ['attendance.write', 'Registrar asistencia'], ['memberships.read', 'Consultar membresías'], ['memberships.write', 'Gestionar membresías'], ['payments.read', 'Consultar pagos'], ['payments.manual', 'Registrar pagos manuales'],
  ['finance.read', 'Consultar finanzas'], ['reports.export', 'Exportar reportes'],
]
const visibleInvitations = computed(() => management.invitations.filter((item) => item.tipo === (isMembers.value ? 'socio' : 'empleado')))

function update(next) { router.replace({ query: Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)) }) }
function clear() { router.replace({ query: {} }) }
function sort(key) { update({ ...query.value, sort: key, direction: query.value.sort === key && query.value.direction === 'asc' ? 'desc' : 'asc', page: 1 }) }
function page(value) { update({ ...query.value, page: value }) }
function togglePermission(permission, value, target = invitation) { target.permissions = value ? [...new Set([...target.permissions, permission])] : target.permissions.filter((item) => item !== permission) }
function notify(titleValue, message = '', tone = 'success') { Object.assign(toast, { open: true, title: titleValue, message, tone }) }
function resetErrors() { fields.value = {}; formError.value = '' }

async function submitInvitation() {
  saving.value = true; resetErrors()
  const result = await management.invite({ email: invitation.email, tipo: isMembers.value ? 'socio' : 'empleado', cargo: invitation.cargo, permissions: invitation.permissions })
  saving.value = false
  if (!result.ok) { fields.value = result.fields; formError.value = result.message; return }
  inviteOpen.value = false; invitation.email = ''; invitation.permissions = []; await resource.load(query.value)
  if (route.query.action === 'invite') await router.replace({ query: { ...route.query, action: undefined } })
  notify(result.data.status === 'accepted_existing' ? 'Cuenta asociada' : 'Invitación enviada', result.data.status === 'accepted_existing' ? 'La persona ya tenía cuenta y quedó asociada al gimnasio.' : 'El enlace vence en 72 horas.')
}

async function openEdit(item) {
  editOpen.value = true; loadingDetail.value = true; resetErrors()
  const result = isMembers.value ? await management.member(item.id) : await management.employee(item.id)
  loadingDetail.value = false
  if (!result.ok) { formError.value = result.message; return }
  const data = result.data
  Object.assign(edit, { id: data.id, nombre: `${data.nombre} ${data.apellido}`.trim(), email: data.email, estado: data.estado || (data.activo ? 'activo' : 'inactivo'), notas_operativas: data.notas_operativas || '', cargo: data.cargo || 'Operación', permissions: data.permissions || [] })
}

async function saveEdit() {
  saving.value = true; resetErrors()
  const payload = isMembers.value ? { estado: edit.estado, notas_operativas: edit.notas_operativas, motivo: 'Actualización desde ficha administrativa' } : { estado: edit.estado, cargo: edit.cargo, notas_operativas: edit.notas_operativas, permissions: edit.permissions }
  const result = isMembers.value ? await management.updateMember(edit.id, payload) : await management.updateEmployee(edit.id, payload)
  saving.value = false
  if (!result.ok) { fields.value = result.fields; formError.value = result.message; return }
  editOpen.value = false; await resource.load(query.value); notify('Cambios guardados', `La ficha de ${edit.nombre} quedó actualizada.`)
}

async function revoke(id) { const result = await management.revokeInvitation(id); if (result.ok) notify('Invitación revocada'); else notify('No se pudo revocar', result.message, 'danger') }

watch([() => route.query, () => admin.version], () => {
  resource.load(query.value)
  if (canWrite.value) management.loadInvitations()
  if (canWrite.value && route.query.action === 'invite') inviteOpen.value = true
}, { immediate: true, deep: true })
onBeforeUnmount(() => resource.cancel())
</script>

<template>
  <section>
    <AdminPageHeading :title="title" :description="description"><template v-if="canWrite" #actions><AppButton @click="inviteOpen = true"><template #icon><IconUserPlus :size="18" /></template>{{ isMembers ? 'Invitar socio' : 'Invitar empleado' }}</AppButton></template></AdminPageHeading>
    <AppCard :padded="false" class="resource-card">
      <AdminFilterBar :model-value="query" :filters="filters" :loading="resource.status === 'loading'" @apply="update" @clear="clear" />
      <AdminDataTable :columns="columns" :items="resource.items" :pagination="resource.pagination" :status="resource.status" :error="resource.error" :sort="query.sort" :direction="query.direction" :empty-title="isMembers ? 'No hay socios asociados' : 'No hay empleados asociados'" :empty-description="canWrite ? 'Invitá una persona o ajustá los filtros para continuar.' : 'Ajustá los filtros para continuar.'" :request-id="resource.requestId" :row-action-label="canWrite ? 'Gestionar' : ''" @sort="sort" @page="page" @retry="resource.load(query)" @row="openEdit" />
    </AppCard>

    <section v-if="canWrite" class="invitations" aria-labelledby="invitations-title"><div><h2 id="invitations-title">Invitaciones recientes</h2><p>Los enlaces pendientes vencen automáticamente después de 72 horas.</p></div><AppEmptyState v-if="!visibleInvitations.length" title="No hay invitaciones" description="Las invitaciones nuevas aparecerán aquí con su estado real." /><ul v-else><li v-for="item in visibleInvitations" :key="item.id"><span><strong>{{ item.email }}</strong><small>{{ item.estado }} · vence {{ new Date(item.expira_en.replace(' ', 'T')).toLocaleString('es-UY') }}</small></span><AppButton v-if="item.estado === 'pendiente'" variant="ghost" size="sm" @click="revoke(item.id)">Revocar</AppButton></li></ul></section>

    <AppDrawer :open="inviteOpen" :title="isMembers ? 'Invitar socio' : 'Invitar empleado'" @close="inviteOpen = false">
      <form id="invite-person-form" class="drawer-form" @submit.prevent="submitInvitation"><AppAlert v-if="formError" tone="danger" title="No pudimos completar la invitación"><p>{{ formError }}</p></AppAlert><div class="form-intro"><IconMailPlus :size="22" /><p>Si el correo ya tiene cuenta, la asociación se completa ahora. Si no, recibirá un enlace seguro.</p></div><AppInput v-model="invitation.email" label="Correo electrónico" name="invite-email" type="email" autocomplete="email" required :error="fields.email" /> <template v-if="!isMembers"><AppInput v-model="invitation.cargo" label="Cargo" name="invite-role" required :error="fields.cargo" /><fieldset><legend>Permisos por gimnasio</legend><AppCheckbox v-for="option in permissionOptions" :key="option[0]" :model-value="invitation.permissions.includes(option[0])" :label="option[1]" @update:model-value="togglePermission(option[0], $event)" /></fieldset></template></form>
      <template #footer><div class="drawer-actions"><AppButton variant="secondary" @click="inviteOpen = false">Cancelar</AppButton><AppButton type="submit" form="invite-person-form" :loading="saving">Enviar invitación</AppButton></div></template>
    </AppDrawer>

    <AppDrawer :open="editOpen" :title="`Gestionar ${isMembers ? 'socio' : 'empleado'}`" @close="editOpen = false">
      <div v-if="loadingDetail" class="detail-loading">Cargando ficha…</div><form v-else id="edit-person-form" class="drawer-form" @submit.prevent="saveEdit"><AppAlert v-if="formError" tone="danger" title="No pudimos guardar"><p>{{ formError }}</p></AppAlert><div class="person-summary"><IconUserCog :size="22" /><span><strong>{{ edit.nombre }}</strong><small>{{ edit.email }}</small></span></div><AppSelect v-model="edit.estado" label="Estado" name="person-status" :options="[{ value: 'activo', label: 'Activo' }, { value: 'inactivo', label: 'Inactivo' }, { value: 'archivado', label: 'Archivado' }]" /> <AppInput v-if="!isMembers" v-model="edit.cargo" label="Cargo" name="employee-role" required :error="fields.cargo" /><AppTextarea v-model="edit.notas_operativas" label="Notas operativas" name="person-notes" :rows="4" hint="Sólo visibles para personal autorizado." :error="fields.notas_operativas" /><fieldset v-if="!isMembers"><legend>Permisos por gimnasio</legend><AppCheckbox v-for="option in permissionOptions" :key="option[0]" :model-value="edit.permissions.includes(option[0])" :label="option[1]" @update:model-value="togglePermission(option[0], $event, edit)" /></fieldset></form>
      <template #footer><div class="drawer-actions"><AppButton variant="secondary" @click="editOpen = false">Cancelar</AppButton><AppButton type="submit" form="edit-person-form" :loading="saving">Guardar cambios</AppButton></div></template>
    </AppDrawer>
    <AppToast v-bind="toast" @close="toast.open = false" />
  </section>
</template>

<style scoped>
.resource-card { overflow: clip; }.invitations { margin-top: var(--space-10); padding-top: var(--space-8); border-top: 1px solid var(--border-subtle); }.invitations > div { margin-bottom: var(--space-5); }.invitations h2 { margin: 0 0 var(--space-2); font-size: 1.25rem; }.invitations p { margin: 0; color: var(--text-secondary); font-size: .82rem; }.invitations ul { margin: 0; padding: 0; border-top: 1px solid var(--border-subtle); list-style: none; }.invitations li { display: flex; min-height: 4rem; align-items: center; justify-content: space-between; gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-3) 0; }.invitations li span,.person-summary span { display: grid; gap: var(--space-1); }.invitations li strong,.person-summary strong { font-size: .82rem; }.invitations li small,.person-summary small { color: var(--text-tertiary); font-size: .72rem; overflow-wrap: anywhere; }.drawer-form { display: grid; gap: var(--space-5); }.form-intro,.person-summary { display: grid; grid-template-columns: 1.5rem 1fr; align-items: flex-start; gap: var(--space-3); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-5); }.form-intro svg,.person-summary > svg { color: var(--info); }.form-intro p { margin: 0; color: var(--text-secondary); font-size: .8rem; line-height: 1.55; }.drawer-form fieldset { display: grid; gap: var(--space-3); border: 0; margin: 0; padding: var(--space-2) 0 0; }.drawer-form legend { margin-bottom: var(--space-3); font-size: .82rem; font-weight: 720; }.drawer-actions { display: flex; justify-content: flex-end; gap: var(--space-3); }.detail-loading { color: var(--text-secondary); }
@media (max-width: 30rem) { .drawer-actions { display: grid; grid-template-columns: 1fr 1fr; }.invitations li { align-items: flex-start; flex-direction: column; } }
</style>
