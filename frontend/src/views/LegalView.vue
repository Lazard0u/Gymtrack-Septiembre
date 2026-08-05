<script setup>
import { computed } from 'vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'

const props = defineProps({ kind: { type: String, required: true } })

const content = {
  privacy: { eyebrow: 'Privacidad', title: 'Privacidad y uso de datos', description: 'Resumen provisional de los principios que guían GymTrack.', sections: [['Datos necesarios', 'GymTrack debe solicitar únicamente la información necesaria para prestar cada función y explicar para qué se usa.'], ['Control del usuario', 'Las preferencias, el consentimiento comercial y la opción de dejar de recibir publicidad formarán parte del perfil.'], ['Seguridad', 'Los secretos no se exponen al frontend y los datos operativos deben quedar separados por gimnasio.']] },
  terms: { eyebrow: 'Términos', title: 'Condiciones de uso', description: 'Base informativa pendiente de revisión legal antes de producción.', sections: [['Alcance', 'GymTrack conecta a socios y gimnasios. Cada sede será responsable de la exactitud de sus horarios, planes y condiciones publicadas.'], ['Pagos', 'Una membresía solo podrá activarse después de confirmar el pago mediante el proveedor y su webhook validado.'], ['Disponibilidad', 'Las funciones se habilitan por fases y la interfaz indica de forma honesta cuando un módulo aún no está conectado.']] },
  accessibility: { eyebrow: 'Accesibilidad', title: 'Una experiencia utilizable por más personas', description: 'Criterios aplicados en la interfaz pública de GymTrack.', sections: [['Teclado y foco', 'La navegación, los menús y los diálogos deben funcionar sin mouse y conservar un foco visible.'], ['Movimiento', 'Las transiciones respetan la preferencia de movimiento reducido del sistema.'], ['Lectura', 'La jerarquía semántica, el contraste y los mensajes de estado se revisan en los anchos principales.']] },
  contact: { eyebrow: 'Contacto', title: 'Hablemos cuando el canal esté listo', description: 'El canal definitivo de soporte todavía debe configurarse.', sections: [['Soporte', 'No publicamos una dirección de correo o un teléfono de demostración. El canal verificado se incorporará antes de producción.'], ['Incidencias', 'Los errores deben registrarse de forma segura, sin mostrar secretos ni detalles internos a quien usa la plataforma.']] },
}

const page = computed(() => content[props.kind] ?? content.privacy)
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container legal"><AppPageHeader :eyebrow="page.eyebrow" :title="page.title" :description="page.description" /><AppAlert v-if="kind === 'terms'" tone="warning" title="Documento provisional"><p>Este contenido no sustituye una revisión jurídica para el lanzamiento.</p></AppAlert><div class="legal__content"><section v-for="section in page.sections" :key="section[0]"><h2>{{ section[0] }}</h2><p>{{ section[1] }}</p></section></div></main><PublicFooter /></div>
</template>

<style scoped>
.legal { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-20); }.legal > :deep(.alert) { max-width: 50rem; margin-bottom: var(--space-10); }.legal__content { max-width: 50rem; border-top: 1px solid var(--border-subtle); }.legal__content section { padding-block: var(--space-8); border-bottom: 1px solid var(--border-subtle); }.legal__content h2 { margin-bottom: var(--space-3); font-size: 1.25rem; }.legal__content p { margin: 0; color: var(--text-secondary); }
</style>
