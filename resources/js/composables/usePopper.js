import { ref, watchEffect } from 'vue'
import { createPopper } from '@popperjs/core'

/**
 * Headless UI + Popper helper.
 *
 * Returns a [reference, popper] pair of template refs. Bind `reference` to the
 * trigger element and `popper` to the floating element (e.g. a teleported
 * MenuItems). Popper keeps the floating element anchored to the trigger using a
 * `fixed` strategy so it can escape `overflow` clipping on ancestor containers
 * (such as a table's horizontal-scroll wrapper).
 *
 * @param {import('@popperjs/core').Options} [options]
 */
export function usePopper(options = {}) {
    const reference = ref(null)
    const popper = ref(null)

    watchEffect((onInvalidate) => {
        if (!popper.value) return
        if (!reference.value) return

        // Headless UI components expose their DOM node via `.el`.
        const popperEl = popper.value.el || popper.value
        const referenceEl = reference.value.el || reference.value

        if (!(referenceEl instanceof HTMLElement)) return
        if (!(popperEl instanceof HTMLElement)) return

        const { destroy } = createPopper(referenceEl, popperEl, options)
        onInvalidate(destroy)
    })

    return [reference, popper]
}
