import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Shared singleton state so every consumer (e.g. the Menu toggle) stays in sync.
const isDark = ref(false)
let initialized = false

/**
 * Dark/light mode controller.
 *
 * Source of truth is the server-shared `theme` prop (persisted per-user in
 * v_user_settings, so it follows the user across devices). The root blade
 * already applies the `.dark` class server-side to avoid a flash; this just
 * mirrors that into reactive state and keeps it in sync on toggle.
 */
export function useTheme() {
    if (!initialized) {
        const page = usePage()
        isDark.value = (page.props.theme ?? 'light') === 'dark'
        apply()
        initialized = true
    }

    function apply() {
        document.documentElement.classList.toggle('dark', isDark.value)
    }

    async function toggle() {
        isDark.value = !isDark.value
        apply()

        try {
            await axios.post('/user/theme', { theme: isDark.value ? 'dark' : 'light' })
        } catch (error) {
            // Revert optimistic change if the save failed.
            isDark.value = !isDark.value
            apply()
            console.error('Failed to save theme preference', error)
        }
    }

    return { isDark, toggle }
}
