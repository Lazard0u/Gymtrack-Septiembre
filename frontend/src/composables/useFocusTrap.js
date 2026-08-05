import { nextTick, onBeforeUnmount, watch } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

export function useFocusTrap(isOpen, containerRef, onClose) {
  let previousFocus = null

  function getFocusable() {
    return [...(containerRef.value?.querySelectorAll(FOCUSABLE) ?? [])]
      .filter((element) => !element.hasAttribute('hidden'))
  }

  function handleKeydown(event) {
    if (event.key === 'Escape') {
      event.preventDefault()
      onClose?.()
      return
    }

    if (event.key !== 'Tab') return
    const focusable = getFocusable()
    if (!focusable.length) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  watch(isOpen, async (open) => {
    if (open) {
      previousFocus = document.activeElement
      document.body.style.overflow = 'hidden'
      await nextTick()
      const [first] = getFocusable()
      ;(first ?? containerRef.value)?.focus()
      document.addEventListener('keydown', handleKeydown)
    } else {
      document.body.style.overflow = ''
      document.removeEventListener('keydown', handleKeydown)
      previousFocus?.focus?.()
      previousFocus = null
    }
  })

  onBeforeUnmount(() => {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', handleKeydown)
  })
}
