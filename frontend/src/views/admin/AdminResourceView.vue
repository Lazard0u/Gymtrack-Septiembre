<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminFilterBar from '../../components/admin/AdminFilterBar.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppCard from '../../components/ui/AppCard.vue'
import {
  useAdminClassesStore, useAdminMembersStore, useAdminMembershipsStore, useAdminPaymentsStore,
  useAdminReservationsStore, useAdminStaffStore,
} from '../../stores/adminResources'
import { useAdminStore } from '../../stores/admin'

const props = defineProps({ resource: { type: String, required: true }, title: { type: String, required: true }, description: { type: String, required: true }, columns: { type: Array, required: true }, filters: { type: Array, default: () => [] }, emptyTitle: { type: String, default: 'No hay registros' }, emptyDescription: { type: String, default: 'No encontramos resultados con estos filtros.' }, phase: { type: String, default: '' } })
const route = useRoute()
const router = useRouter()
const admin = useAdminStore()
const stores = {
  members: useAdminMembersStore(), staff: useAdminStaffStore(), classes: useAdminClassesStore(),
  reservations: useAdminReservationsStore(), memberships: useAdminMembershipsStore(), payments: useAdminPaymentsStore(),
}
const store = stores[props.resource]
const query = computed(() => ({ q: String(route.query.q || ''), status: String(route.query.status || ''), day: String(route.query.day || ''), method: String(route.query.method || ''), from: String(route.query.from || ''), to: String(route.query.to || ''), sort: String(route.query.sort || ''), direction: route.query.direction === 'asc' ? 'asc' : 'desc', page: Math.max(1, Number(route.query.page || 1)), per_page: 20 }))

function update(next) {
  const cleaned = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined && !(value === 1 && !route.query.page)))
  router.replace({ query: cleaned })
}
function clear() { router.replace({ query: {} }) }
function sort(key) { update({ ...query.value, sort: key, direction: query.value.sort === key && query.value.direction === 'asc' ? 'desc' : 'asc', page: 1 }) }
function page(value) { update({ ...query.value, page: value }) }

watch([() => route.query, () => admin.version], () => store.load(query.value), { immediate: true, deep: true })
onBeforeUnmount(() => store.cancel())
</script>

<template>
  <section>
    <AdminPageHeading :title="title" :description="description"><template v-if="phase" #actions><AppBadge tone="info">{{ phase }}</AppBadge></template></AdminPageHeading>
    <AppCard :padded="false" class="resource-card">
      <AdminFilterBar :model-value="query" :filters="filters" :loading="store.status === 'loading'" @apply="update" @clear="clear" />
      <AdminDataTable :columns="columns" :items="store.items" :pagination="store.pagination" :status="store.status" :error="store.error" :sort="query.sort" :direction="query.direction" :empty-title="emptyTitle" :empty-description="emptyDescription" :request-id="store.requestId" @sort="sort" @page="page" @retry="store.load(query.value)" />
    </AppCard>
  </section>
</template>

<style scoped>.resource-card { overflow: clip; }</style>
