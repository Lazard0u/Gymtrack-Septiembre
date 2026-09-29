import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[contenteditable="true"]',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const modalStack = []
let scrollLockDepth = 0
let previousBodyOverflow = ''

function getFocusable(container) {
  return [...(container?.querySelectorAll(FOCUSABLE) ?? [])]
    .filter((element) => !element.closest('[hidden], [inert], [aria-hidden="true"]'))
}

function focusLayer(layer, backwards = false) {
  const focusable = getFocusable(layer.containerRef.value)
  const target = backwards ? focusable.at(-1) : focusable[0]
  ;(target ?? layer.containerRef.value)?.focus?.()
}

function refreshStack() {
  modalStack.forEach((layer, index) => {
    layer.stackIndex.value = index
    layer.isTopLayer.value = index === modalStack.length - 1
  })
}

function lockBodyScroll() {
  if (typeof document === 'undefined') return
  if (scrollLockDepth === 0) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', handleKeydown)
  }
  scrollLockDepth += 1
}

function unlockBodyScroll() {
  if (typeof document === 'undefined' || scrollLockDepth === 0) return
  scrollLockDepth -= 1
  if (scrollLockDepth === 0) {
    document.body.style.overflow = previousBodyOverflow
    previousBodyOverflow = ''
    document.removeEventListener('keydown', handleKeydown)
  }
}

function handleKeydown(event) {
  const layer = modalStack.at(-1)
  if (!layer) return

  if (event.key === 'Escape') {
    event.preventDefault()
    layer.onClose?.()
    return
  }

  if (event.key !== 'Tab') return

  const container = layer.containerRef.value
  const focusable = getFocusable(container)
  if (!focusable.length) {
    event.preventDefault()
    container?.focus?.()
    return
  }

  const first = focusable[0]
  const last = focusable.at(-1)
  const activeElement = document.activeElement
  const focusIsInside = container?.contains(activeElement)

  if (!focusIsInside || activeElement === container) {
    event.preventDefault()
    ;(event.shiftKey ? last : first).focus()
  } else if (event.shiftKey && activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function activateLayer(layer) {
  if (layer.active || typeof document === 'undefined') return
  layer.active = true
  layer.previousFocus = document.activeElement
  modalStack.push(layer)
  lockBodyScroll()
  refreshStack()
}

function deactivateLayer(layer) {
  if (!layer.active) return

  const index = modalStack.indexOf(layer)
  const wasTopLayer = index === modalStack.length - 1
  const closingContainer = layer.containerRef.value

  modalStack.splice(index, 1)
  layer.active = false

  // Preserve a valid return target if a parent modal disappears underneath
  // another layer that is still open.
  if (closingContainer) {
    modalStack.forEach((openLayer) => {
      if (closingContainer.contains(openLayer.previousFocus)) {
        openLayer.previousFocus = layer.previousFocus
      }
    })
  }

  unlockBodyScroll()
  refreshStack()

  if (wasTopLayer) {
    const nextLayer = modalStack.at(-1)
    if (nextLayer && nextLayer.containerRef.value?.contains(layer.previousFocus)) {
      layer.previousFocus.focus?.()
    } else if (nextLayer) {
      focusLayer(nextLayer)
    } else if (layer.previousFocus?.isConnected) {
      layer.previousFocus.focus?.()
    }
  }

  layer.previousFocus = null
}

export function useFocusTrap(isOpen, containerRef, onClose) {
  const layer = {
    active: false,
    containerRef,
    isTopLayer: ref(false),
    onClose,
    previousFocus: null,
    stackIndex: ref(0),
  }
  let activation = 0

  watch(isOpen, async (open) => {
    activation += 1
    const currentActivation = activation

    if (!open) {
      deactivateLayer(layer)
      return
    }

    activateLayer(layer)
    await nextTick()
    if (layer.active && currentActivation === activation && modalStack.at(-1) === layer) {
      focusLayer(layer)
    }
  }, { immediate: true })

  onBeforeUnmount(() => {
    activation += 1
    deactivateLayer(layer)
  })

  return {
    isTopLayer: layer.isTopLayer,
    stackIndex: layer.stackIndex,
  }
}
